import { vi } from 'vitest';
import { SyncQueueRepository } from '../../db/repositories/SyncQueueRepository';

// Este mock simula el comportamiento atómico de Rust:
// 1. Devuelve el local_uuid del pago.
// 2. Encola automáticamente el evento en sync_queue con el payload completo.
export function mockTauriInvokeForPayments() {
  vi.mock('@tauri-apps/api/core', async (importOriginal) => {
    const actual = await importOriginal<typeof import('@tauri-apps/api/core')>();
    return {
      ...actual,
      invoke: vi.fn(async (command: string, payload: any) => {
        if (command === 'register_local_payment') {
          // Simular la inserción atómica en sync_queue que ahora hace Rust
          if (payload?.local_uuid && payload?.order_local_uuid) {
            await SyncQueueRepository.enqueue({
              company_id: payload.company_id || 'test-company',
              branch_id: payload.branch_id || 'test-branch',
              entity_type: 'payment',
              entity_local_uuid: payload.local_uuid,
              action: 'create',
              payload: {
                local_uuid: payload.local_uuid,
                order_local_uuid: payload.order_local_uuid,
                payment_method_uuid: payload.payment_method_uuid || 'mock-payment-method-uuid',
                amount: payload.amount || 0,
                tip_amount: payload.tip_amount || 0,
                total_amount: payload.total_amount || 0,
                idempotency_key: payload.idempotency_key || 'mock-idempotency-key',
                reference_code: payload.reference_code || null,
                notes: payload.notes || null,
              },
            });
          }
          return { local_uuid: payload.local_uuid, idempotent: false };
        }
        // Para otros comandos, usar el comportamiento original o un mock genérico
        return Promise.resolve({});
      }),
    };
  });
}
