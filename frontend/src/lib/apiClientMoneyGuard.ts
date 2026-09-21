import type { AxiosResponse, InternalAxiosRequestConfig } from 'axios';
import { validateMoneyFields, detectEntityType, type EntityValidationResult } from '@/schemas/entities';

/**
 * Money Contract Guard para apiClient (ADR-018).
 * 
 * Intercepta requests Y responses para validar que los campos monetarios
 * cumplan el contrato ADR-018 (integer CLP).
 * 
 * ESTRATEGIA BIDIRECCIONAL:
 * 
 * REQUEST (Frontend → Backend):
 *   - Valida payload antes de enviar
 *   - Fail-fast local (no envía requests inválidos)
 *   - Previene corrupción en backend
 * 
 * RESPONSE (Backend → Frontend):
 *   - Valida respuesta antes de usar
 *   - Modo strict: throw Error (dev/test)
 *   - Modo telemetry: reportar + throw (prod)
 * 
 * NUNCA permite que datos monetarios inválidos crucen el boundary.
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
    public readonly direction: 'request' | 'response',
    public readonly entityType: string,
    public readonly url: string,
    public readonly errors: string[]
  ) {
    super(
      `[Money Contract] ❌ ${direction.toUpperCase()} ${entityType} @ ${url}\n` +
      errors.map((e) => `  • ${e}`).join('\n')
    );
    this.name = 'MoneyContractViolation';
  }
}

/**
 * Reporte de violación del Money Contract.
 */
interface ViolationReport {
  direction: 'request' | 'response';
  url: string;
  entityType: string;
  errors: string[];
  timestamp: string;
}

/**
 * Historial de violaciones (solo en DEV para debug).
 */
const violationHistory: ViolationReport[] = [];

// ═══════════════════════════════════════════════════════════════
// VALIDACIÓN DE REQUESTS (Frontend → Backend)
// ═══════════════════════════════════════════════════════════════

/**
 * Valida el payload del request antes de enviar.
 * Si hay violación, lanza error ANTES de que el request salga.
 */
export function validateRequestMoney<T extends InternalAxiosRequestConfig>(config: T): T {
  const url = config.url || '';
  const method = (config.method || 'GET').toUpperCase();
  
  // Solo validar requests con body (POST, PUT, PATCH)
  if (!['POST', 'PUT', 'PATCH'].includes(method)) {
    return config;
  }
  
  const entityType = detectEntityType(url);
  
  // Si no es una entidad con campos monetarios, pasar
  if (!entityType) {
    return config;
  }
  
  // Validar el body del request
  const data = config.data;
  if (!data) {
    return config;
  }
  
  const results: EntityValidationResult[] = [];
  
  if (Array.isArray(data)) {
    data.forEach((item: unknown, idx: number) => {
      results.push(validateMoneyFields(item, entityType, `[${idx}]`));
    });
  } else {
    results.push(validateMoneyFields(data, entityType, 'root'));
  }
  
  // Consolidar errores
  const allErrors: string[] = [];
  for (const result of results) {
    if (!result.valid) {
      allErrors.push(...result.errors);
    }
  }
  
  // Si no hay errores, pasar
  if (allErrors.length === 0) {
    return config;
  }
  
  // HAY VIOLACIÓN EN REQUEST: fail-fast
  const report: ViolationReport = {
    direction: 'request',
    url,
    entityType,
    errors: allErrors,
    timestamp: new Date().toISOString(),
  };
  
  handleViolation(report);
  
  // NUNCA enviar request con datos monetarios inválidos
  throw new MoneyContractViolation('request', entityType, url, allErrors);
}

// ═══════════════════════════════════════════════════════════════
// VALIDACIÓN DE RESPONSES (Backend → Frontend)
// ═══════════════════════════════════════════════════════════════

/**
 * Valida la respuesta del backend antes de usar.
 * Si hay violación, lanza error (nunca retorna datos inválidos).
 */
export function validateResponseMoney(response: AxiosResponse): AxiosResponse {
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
  
  // HAY VIOLACIÓN EN RESPONSE
  const report: ViolationReport = {
    direction: 'response',
    url,
    entityType,
    errors: allErrors,
    timestamp: new Date().toISOString(),
  };
  
  handleViolation(report);
  
  // NUNCA retornar response con datos monetarios inválidos
  throw new MoneyContractViolation('response', entityType, url, allErrors);
}

// ═══════════════════════════════════════════════════════════════
// MANEJO DE VIOLACIONES
// ═══════════════════════════════════════════════════════════════

/**
 * Maneja una violación del Money Contract según el modo.
 */
function handleViolation(report: ViolationReport): void {
  const formatted = `[Money Contract] ❌ ${report.direction.toUpperCase()} ${report.entityType} @ ${report.url}\n` +
    report.errors.map((e) => `  • ${e}`).join('\n');
  
  if (isDev) {
    console.error(formatted);
    console.group('[Money Contract] Detalles');
    console.log('Modo:', MONEY_CONTRACT_MODE);
    console.log('Dirección:', report.direction);
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
  console.error('[Money Contract] Reportado a Sentry:', report);
}

// ═══════════════════════════════════════════════════════════════
// DEBUG HELPERS
// ═══════════════════════════════════════════════════════════════

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
