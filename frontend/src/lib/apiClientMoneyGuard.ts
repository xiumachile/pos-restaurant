import type { AxiosResponse } from 'axios';
import { validateMoneyFields, detectEntityType, type EntityValidationResult } from '@/schemas/entities';

/**
 * Money Contract Guard para apiClient (ADR-018).
 * 
 * Intercepta respuestas y valida que los campos monetarios
 * cumplan el contrato ADR-018 (integer CLP).
 * 
 * ESTRATEGIA:
 * - strict (dev/test): throw Error (fail-fast para detectar bugs)
 * - telemetry (prod): reportar a Sentry + rechazar response
 * 
 * NUNCA permite que datos monetarios inválidos lleguen al estado de la app.
 */

type MoneyContractMode = 'strict' | 'telemetry';

const MONEY_CONTRACT_MODE: MoneyContractMode = 
  (import.meta.env.VITE_MONEY_CONTRACT_MODE as MoneyContractMode) || 'strict';

const isDev = import.meta.env.DEV;

/**
 * Error lanzado cuando el Money Contract es violado.
 */
export class MoneyContractViolation extends Error {
  constructor(
    public readonly entityType: string,
    public readonly url: string,
    public readonly errors: string[]
  ) {
    super(
      `[Money Contract] ❌ ${entityType} @ ${url}\n` +
      errors.map((e) => `  • ${e}`).join('\n')
    );
    this.name = 'MoneyContractViolation';
  }
}

/**
 * Reporte de violación del Money Contract.
 */
interface ViolationReport {
  url: string;
  entityType: string;
  errors: string[];
  timestamp: string;
}

/**
 * Historial de violaciones (solo en DEV para debug).
 */
const violationHistory: ViolationReport[] = [];

/**
 * Valida la respuesta contra el Money Contract.
 * 
 * Comportamiento según modo:
 * - strict: throw MoneyContractViolation (fail-fast)
 * - telemetry: reportar a Sentry + lanzar error (rechazar response)
 * 
 * En ambos casos, NUNCA retorna la response si hay violación.
 */
export function validateMoneyContract(response: AxiosResponse): AxiosResponse {
  const url = response.config?.url || '';
  const entityType = detectEntityType(url);
  
  // Si no es una entidad con campos monetarios, pasar
  if (!entityType) {
    return response;
  }
  
  // La respuesta puede venir como {data: ...} o directamente
  const rawData = response.data;
  const dataToValidate = rawData?.data ?? rawData;
  
  if (!dataToValidate) {
    return response;
  }
  
  const results: EntityValidationResult[] = [];
  
  if (Array.isArray(dataToValidate)) {
    dataToValidate.forEach((item: unknown, idx: number) => {
      results.push(validateMoneyFields(item, entityType, `[${idx}]`));
    });
  } else {
    results.push(validateMoneyFields(dataToValidate, entityType, 'root'));
  }
  
  // Consolidar errores
  const allErrors: string[] = [];
  for (const result of results) {
    if (!result.valid) {
      allErrors.push(...result.errors);
    }
  }
  
  // Si no hay errores, retornar response válida
  if (allErrors.length === 0) {
    return response;
  }
  
  // HAY VIOLACIÓN: manejar según modo
  const report: ViolationReport = {
    url,
    entityType,
    errors: allErrors,
    timestamp: new Date().toISOString(),
  };
  
  handleViolation(report);
  
  // NUNCA retornar la response con datos inválidos
  throw new MoneyContractViolation(entityType, url, allErrors);
}

/**
 * Maneja una violación del Money Contract según el modo.
 */
function handleViolation(report: ViolationReport): void {
  const formatted = `[Money Contract] ❌ ${report.entityType} @ ${report.url}\n` +
    report.errors.map((e) => `  • ${e}`).join('\n');
  
  if (isDev) {
    console.error(formatted);
    console.group('[Money Contract] Detalles');
    console.log('Modo:', MONEY_CONTRACT_MODE);
    console.log('Reporte completo:', report);
    console.groupEnd();
    
    // Acumular en historial para debug
    violationHistory.push(report);
    if (violationHistory.length > 100) {
      violationHistory.shift();
    }
  }
  
  // En modo telemetry (producción), reportar a Sentry
  if (MONEY_CONTRACT_MODE === 'telemetry') {
    reportToSentry(report);
  }
}

/**
 * Reporta violación a Sentry (producción).
 * TODO: Integrar con SDK real de Sentry cuando esté configurado.
 */
function reportToSentry(report: ViolationReport): void {
  // Placeholder para integración con Sentry
  // Cuando Sentry esté configurado, descomentar:
  // 
  // import * as Sentry from '@sentry/react';
  // Sentry.captureMessage(`Money Contract violation: ${report.entityType}`, {
  //   level: 'error',
  //   extra: {
  //     url: report.url,
  //     entityType: report.entityType,
  //     errors: report.errors,
  //     timestamp: report.timestamp,
  //   },
  //   tags: {
  //     component: 'money-contract',
  //     severity: 'critical',
  //   },
  // });
  
  console.error('[Money Contract] Reportado a Sentry:', report);
}

/**
 * DEBUG: Obtener historial de violaciones (solo DEV).
 */
export function getViolationHistory(): readonly ViolationReport[] {
  return violationHistory;
}

/**
 * DEBUG: Limpiar historial.
 */
export function clearViolationHistory(): void {
  violationHistory.length = 0;
}

/**
 * DEBUG: Obtener modo actual del Money Contract.
 */
export function getMoneyContractMode(): MoneyContractMode {
  return MONEY_CONTRACT_MODE;
}
