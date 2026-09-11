import { describe, it, expect } from 'vitest';
import { 
  enrichSyncQueueItem, 
  formatPaymentUuid, 
  formatCashSession 
} from '@/services/sync/SyncQueueEnrichment';

describe('SyncQueueEnrichment', () => {
  describe('enrichSyncQueueItem', () => {
    it('extrae payment_uuid de payment', () => {
      const payload = JSON.stringify({
        local_uuid: 'payment-123',
        amount: 1000,
        idempotency_key: 'idem-456'
      });

      const result = enrichSyncQueueItem(payload, 'payment');

      expect(result.payment_uuid).toBe('payment-123');
      expect(result.idempotency_key).toBe('idem-456');
    });

    it('extrae terminal_id', () => {
      const payload = JSON.stringify({
        local_uuid: 'order-789',
        terminal_id: 'term-001'
      });

      const result = enrichSyncQueueItem(payload, 'order');

      expect(result.terminal_id).toBe('term-001');
    });

    it('extrae cash_session de cash_movement', () => {
      const payload = JSON.stringify({
        cash_session_local_uuid: 'session-abc'
      });

      const result = enrichSyncQueueItem(payload, 'cash_movement');

      expect(result.cash_session_uuid).toBe('session-abc');
    });

    it('retorna objeto vacío si payload inválido', () => {
      const result = enrichSyncQueueItem('invalid json', 'order');

      expect(result).toEqual({});
    });

    it('maneja payload sin campos opcionales', () => {
      const payload = JSON.stringify({
        amount: 500
      });

      const result = enrichSyncQueueItem(payload, 'payment');

      expect(result.payment_uuid).toBeUndefined();
      expect(result.idempotency_key).toBeUndefined();
      expect(result.terminal_id).toBeUndefined();
    });
  });

  describe('formatPaymentUuid', () => {
    it('formatea UUID mostrando primeros 8 chars', () => {
      const result = formatPaymentUuid('12345678-abcd-efgh-ijkl-mnopqrstuvwx');
      expect(result).toBe('12345678...');
    });

    it('retorna "-" si UUID es undefined', () => {
      expect(formatPaymentUuid(undefined)).toBe('-');
    });
  });

  describe('formatCashSession', () => {
    it('formatea UUID mostrando primeros 8 chars', () => {
      const result = formatCashSession('abcdefgh-1234-5678-9012-ijklmnopqrst');
      expect(result).toBe('abcdefgh...');
    });

    it('retorna "-" si UUID es undefined', () => {
      expect(formatCashSession(undefined)).toBe('-');
    });
  });
});
