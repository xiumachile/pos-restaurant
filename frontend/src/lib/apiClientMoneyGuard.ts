import type { AxiosResponse } from 'axios';
import { validateMoneyFields, detectEntityType, type EntityValidationResult } from '@/schemas/entities';

/**
 * Money Contract Guard para apiClient (ADR-018).
 * 
 * Intercepta respuestas y valida que los campos monetarios
 * cumplan el contrato ADR-018 (integer CLP).
 * 
 * Implementación SIN Zod - validación nativa.
 */

const isDev = import.meta.env.DEV;

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
 * - En DEV: loguea error detallado y acumula en historial.
 * - En PROD: loguea warning silencioso (enviar a monitoreo).
 * - NUNCA rompe el flujo (el API ya respondió, el problema es del backend).
 */
export function validateMoneyContract(response: AxiosResponse): AxiosResponse {
  const url = response.config?.url || '';
  const entityType = detectEntityType(url);
  
  // Si no es una entidad con campos monetarios, saltar
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
  
  if (allErrors.length > 0) {
    handleViolation({
      url,
      entityType,
      errors: allErrors,
      timestamp: new Date().toISOString(),
    });
  }
  
  return response;
}

/**
 * Maneja una violación del Money Contract.
 */
function handleViolation(report: ViolationReport): void {
  const formatted = `[Money Contract] ❌ ${report.entityType} @ ${report.url}\n` +
    report.errors.map((e) => `  • ${e}`).join('\n');
  
  if (isDev) {
    console.error(formatted);
    console.group('[Money Contract] Detalles');
    console.log('Reporte completo:', report);
    console.groupEnd();
    
    // Acumular en historial para debug
    violationHistory.push(report);
    if (violationHistory.length > 100) {
      violationHistory.shift();
    }
  } else {
    // En producción: loguear warning + enviar a monitoreo
    console.warn(formatted);
    
    // TODO: Integrar con Sentry, LogRocket, Datadog, etc.
    // Sentry.captureMessage(`Money Contract violation: ${report.entityType}`, {
    //   level: 'warning',
    //   extra: report,
    // });
  }
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
