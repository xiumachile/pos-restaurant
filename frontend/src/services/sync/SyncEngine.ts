import { localDb } from "../../db/localDb";
import { SyncQueueRepository, type SyncQueueItem } from "../../db/repositories/SyncQueueRepository";
import { syncApi } from "../syncApi";
import { pullEngine } from "./PullEngine";
import { useSyncStore } from "../../store/useSyncStore";
import { useAuthStore } from "../../store/useAuthStore";
import { useToastStore } from "../../store/useToastStore";
import { validateContext } from "../authContext";
import { SyncStrategies } from "./strategies/SyncStrategies";

/**
 * SyncEngine: Orquesta la sincronización bidireccional entre
 * SQLite local y PostgreSQL cloud.
 */
export class SyncEngine {
  private isProcessing = false;

  /**
   * Procesa batch de eventos pendientes en sync_queue (solo push).
   * Retorna estadísticas para compatibilidad con tests.
   */
  async processBatch(): Promise<{
    processed: number;
    success: number;
    failed: number;
    skipped: number;
  }> {
    if (!useAuthStore.getState().isAuthenticated) return { processed: 0, success: 0, failed: 0, skipped: 0 };
    if (this.isProcessing) {
      console.log("[SyncEngine] Ya procesando, saltando batch");
      return { processed: 0, success: 0, failed: 0, skipped: 1 };
    }

    this.isProcessing = true;
    const store = useSyncStore.getState();
    const stats = { processed: 0, success: 0, failed: 0, skipped: 0 };

    try {
      store.setStatus("syncing");
      
      // P1-009: Claim atómico para evitar colisiones entre múltiples procesos
      const pendingItems = await SyncQueueRepository.claimPending(10);

      if (pendingItems.length === 0) {
        store.setStatus("online");
        this.isProcessing = false;
        return stats;
      }

      store.setProgress({
        phase: "push-processing",
        message: `Subiendo ${pendingItems.length} eventos...`,
        current: 0,
        total: pendingItems.length,
        percentage: 0,
      });

      console.log(`[SyncEngine] Procesando ${pendingItems.length} eventos pendientes`);

      for (let i = 0; i < pendingItems.length; i++) {
        const item = pendingItems[i];
        try {
          store.updateProgress({
            current: i + 1,
            percentage: Math.round(((i + 1) / pendingItems.length) * 100),
            message: `Subiendo ${item.entity_type} (${i + 1}/${pendingItems.length})...`,
          });

          await this.processItem(item);
          
          // ADR-014: Verificar si el item fue rechazado permanentemente (multi-tenant)
          const updatedItem = await SyncQueueRepository.findById(item.id);
          if (updatedItem?.sync_status === "failed" && updatedItem.attempts === updatedItem.max_attempts) {
            // Rechazo permanente: contar como failed sin handleFailure()
            stats.processed++;
            stats.failed++;
          } else {
            stats.processed++;
            stats.success++;
          }
        } catch (error: any) {
          console.error(`[SyncEngine] Error procesando ${item.id}:`, error);
          
          // Logging detallado para errores 422 (validación)
          if (error?.response?.status === 422) {
            console.error("[SyncEngine] ❌ Error de validación (422):");
            console.error("[SyncEngine] Response data:", error.response.data);
            console.error("[SyncEngine] Request payload:", item.payload);
          }
          
          await SyncStrategies.handleFailure(item, error?.message || "Unknown error");
          stats.processed++;
          stats.failed++;
        }
      }

      store.updateProgress({
        phase: "push-completing",
        message: "Finalizando...",
        percentage: 100,
      });

      store.setStatus(stats.failed > 0 && stats.success === 0 ? "error" : "online");
      store.setProgress(null);
      await store.refreshPendingCount();
    } catch (error: any) {
      console.error("[SyncEngine] Error crítico en batch:", error);
      store.setStatus("error");
      store.setLastError(error?.message || "Error crítico");
      store.setProgress(null);
    } finally {
      this.isProcessing = false;
    }

    return stats;
  }

  /**
   * Procesa un item individual de la cola.
   */
  private async processItem(item: SyncQueueItem): Promise<void> {
    // P1-009: El item ya está en estado 'syncing' gracias a claimPending()

    // 🔒 VALIDACIÓN MULTI-TENANT: rechazar items que no pertenecen al usuario actual
    // Esto previene que un terminal procese datos de otra company/branch
    const isAuthorized = validateContext({
      company_id: item.company_id,
      branch_id: item.branch_id,
    });

    if (!isAuthorized) {
      const error = `[MultiTenant] Item ${item.id} (${item.entity_type}/${item.action}) ` +
        `pertenece a company=${item.company_id}, branch=${item.branch_id} ` +
        `pero el usuario actual tiene diferente contexto. Rechazado por seguridad.`;
      console.error("[SyncEngine]", error);
      // Rechazo PERMANENTE: no reintentar (datos maliciosos/de otro tenant)
      // NO lanzar error para evitar que handleFailure() incremente attempts
      await SyncQueueRepository.markAsPermanentlyFailed(item.id, error);
      return; // Salir silenciosamente, el item ya está marcado como failed
    }

    const payload = SyncStrategies.safeParseJson(item.payload);
    if (!payload) {
      throw new Error("Payload inválido");
    }

    let cloudId: string | null = null;

    switch (item.entity_type) {
      case "order":
        cloudId = await SyncStrategies.processOrder(item, payload);
        break;
      case "payment":
        cloudId = await SyncStrategies.processPayment(item, payload);
        break;
      case "table_status":
        await SyncStrategies.processTableStatus(item, payload);
        break;
      case "cash_session":
        cloudId = await SyncStrategies.processCashSession(item, payload);
        break;
      case "cash_movement":
        cloudId = await SyncStrategies.processCashMovement(item, payload);
        break;
      case "bill":
        cloudId = await SyncStrategies.processBill(item, payload);
        break;
      default:
        throw new Error(`Entity type no soportado: ${item.entity_type}`);
    }

    await SyncQueueRepository.markAsSynced(item.id, cloudId || undefined);
    console.log(`[SyncEngine] ✓ ${item.entity_type}/${item.action} synced${cloudId ? ` (cloud: ${cloudId})` : ""}`);
  }





  /**
   * Procesa sincronización de bill offline → backend (ADR-020).
   *
   * Flujo:
   * 1. Resolver order_uuid desde order_local_uuid (order debe estar ya sincronizado)
   * 2. Construir payload con mapeo de campos frontend→backend:
   *    - frontend: tax_total, discount_total, grand_total, amount_due
   *    - backend:  tax_amount, discount_amount, total (≈ amount_due)
   * 3. POST /api/v1/bills con Idempotency-Key
   * 4. Guardar cloud_id (uuid backend) en local_bills
   */




  /**
   * Procesa movimientos de caja offline (withdrawal/deposit/adjustment).
   * 
   * IMPORTANTE: Solo estos 3 tipos llegan al backend.
   * - opening/closing: se sincronizan como parte de cash_session
   * - payment: se sincroniza como billing/payments
   * 
   * El backend espera amount positivo (el signo lo da el type).
   */




  private safeParseJson(str: string): any {
    try {
      return JSON.parse(str);
    } catch {
      return null;
    }
  }

  /**
   * Dispara sincronización completa: push + pull.
   * Incluye notificaciones toast de progreso.
   */
  async triggerFullSync(): Promise<void> {
    if (!useAuthStore.getState().isAuthenticated) return;
    if (this.isProcessing) {
      console.log("[SyncEngine] Ya hay una sincronización en progreso");
      return;
    }

    const store = useSyncStore.getState();
    const toastStore = useToastStore.getState();

    try {
      // Fase 1: Push
      toastStore.addToast("info", "Guardando cambios en el servidor...");
      const pushStats = await this.processBatch();

      if (pushStats.failed > 0) {
        toastStore.addToast(
          "warning",
          `Push completado con errores: ${pushStats.success} exitosos, ${pushStats.failed} fallidos`
        );
      } else if (pushStats.success > 0) {
        toastStore.addToast("success", `Push completado: ${pushStats.success} eventos subidos`);
      }

      // Fase 2: Pull
      store.setProgress({
        phase: "pull-downloading",
        message: "Descargando catálogo...",
        current: 0,
        total: 4,
        percentage: 0,
      });

      toastStore.addToast("info", "Actualizando información...");
      const pullStats = await pullEngine.pullAll();

      if (!pullStats.success) {
        throw new Error("No se pudo actualizar la información. Reintentando...");
      }

      store.updateProgress({
        phase: "pull-applying",
        message: "Aplicando cambios locales...",
        percentage: 90,
      });

      store.updateProgress({
        phase: "completed",
        message: "Sincronización completada",
        percentage: 100,
      });

      const totalItems =
        pullStats.categories +
        pullStats.products +
        pullStats.tables +
        pullStats.paymentMethods;

      toastStore.addToast(
        "success",
        `Sincronización completada: ${totalItems} elementos actualizados`
      );

      store.setLastSyncAt(new Date().toISOString());
      store.setStatus("online");

      setTimeout(() => {
        store.setProgress(null);
      }, 2000);
    } catch (error: any) {
      console.error("[SyncEngine] Error en triggerFullSync:", error);
      store.setStatus("error");
      store.setLastError(error.message);
      toastStore.addToast("error", `Error de sincronización: ${error.message}`);
      store.setProgress(null);
    }
  }
  /**
   * Reset del estado interno (solo para tests).
   * Garantiza que el flag isProcessing no quede bloqueado.
   */
  __resetForTests(): void {
    this.isProcessing = false;
  }

  /**
   * Procesa sesiones de caja offline (open/close).
   * 
   * IMPORTANTE: El backend solo tiene endpoints para:
   * - POST /cash-sessions/open (crear sesión)
   * - POST /cash-sessions/{uuid}/close (cerrar sesión)
   * 
   * No hay endpoint para actualizar sesión abierta (solo close).
   */


}

export const syncEngine = new SyncEngine();
