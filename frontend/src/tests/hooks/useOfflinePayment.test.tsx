import { describe, it, expect, beforeEach, vi } from "vitest";
import { renderHook, waitFor } from "@testing-library/react";
import type { ReactNode } from "react";
import { QueryClient, QueryClientProvider } from "@tanstack/react-query";
import { useOfflinePayment } from "@/hooks/useOfflinePayment";
import { BillRepository } from "@/db/repositories/BillRepository";
import { offlinePaymentService } from "@/services/offlinePaymentService";
import type { LocalBill } from "@/types/bills";

// Mocks
vi.mock("@/db/repositories/BillRepository");
vi.mock("@/services/offlinePaymentService");
vi.mock("@/hooks/usePayments", async () => {
  const actual = await vi.importActual("@/hooks/usePayments");
  return {
    ...(actual as any),
    usePaymentMethods: () => ({
      data: [
        { uuid: "pm-cash-uuid", type: "cash", code: "CASH" },
        { uuid: "pm-card-uuid", type: "card", code: "CARD" },
        { uuid: "pm-other-uuid", type: "other", code: "OTHER" },
      ],
    }),
  };
});

const createWrapper = () => {
  const queryClient = new QueryClient({
    defaultOptions: { queries: { retry: false } },
  });
  return ({ children }: { children: ReactNode }) => (
    <QueryClientProvider client={queryClient}>{children}</QueryClientProvider>
  );
};

const mockBill: Partial<LocalBill> = {
  local_uuid: "bill-local-uuid",
  cloud_id: "bill-cloud-uuid",
  order_local_uuid: "order-local-uuid",
  remaining_amount: 10000,
  status: "open",
};

describe("useOfflinePayment", () => {
  beforeEach(() => {
    vi.clearAllMocks();
    
    (BillRepository.findByCloudId as any).mockResolvedValue(null);
    (BillRepository.findByLocalUuid as any).mockResolvedValue(null);
    (offlinePaymentService.createPaymentOffline as any).mockResolvedValue({
      payment: { amount: 5000, tip_amount: 500 },
      bill: { ...mockBill, status: "partial", paid_amount: 5000, remaining_amount: 5000 },
      orderPaid: false,
      orderStatusUpdated: false,
      tableReleased: false,
    });
  });

  it("busca bill por cloud_id primero", async () => {
    (BillRepository.findByCloudId as any).mockResolvedValue(mockBill);
    
    const { result } = renderHook(() => useOfflinePayment(), { wrapper: createWrapper() });
    
    await waitFor(() => {
      result.current.mutate({
        billUuid: "bill-cloud-uuid",
        payload: {
          payment_method_uuid: "pm-cash-uuid",
          amount: 5000,
          idempotency_key: "test-key",
        },
      });
    });

    expect(BillRepository.findByCloudId).toHaveBeenCalledWith("bill-cloud-uuid");
    expect(BillRepository.findByLocalUuid).not.toHaveBeenCalled();
  });

  it("hace fallback a findByLocalUuid si cloud_id no encuentra", async () => {
    (BillRepository.findByLocalUuid as any).mockResolvedValue(mockBill);
    
    const { result } = renderHook(() => useOfflinePayment(), { wrapper: createWrapper() });
    
    await waitFor(() => {
      result.current.mutate({
        billUuid: "bill-local-uuid",
        payload: {
          payment_method_uuid: "pm-cash-uuid",
          amount: 5000,
          idempotency_key: "test-key",
        },
      });
    });

    expect(BillRepository.findByCloudId).toHaveBeenCalledWith("bill-local-uuid");
    expect(BillRepository.findByLocalUuid).toHaveBeenCalledWith("bill-local-uuid");
  });

  it("falla si bill no se encuentra", async () => {
    const { result } = renderHook(() => useOfflinePayment(), { wrapper: createWrapper() });
    
    result.current.mutate({
      billUuid: "non-existent",
      payload: {
        payment_method_uuid: "pm-cash-uuid",
        amount: 5000,
        idempotency_key: "test-key",
      },
    });

    await waitFor(() => {
      expect(result.current.isError).toBe(true);
    });

    expect(result.current.error?.message).toContain("Bill no encontrada");
  });

  it("falla si bill no tiene order_local_uuid", async () => {
    (BillRepository.findByCloudId as any).mockResolvedValue({
      ...mockBill,
      order_local_uuid: null,
    });
    
    const { result } = renderHook(() => useOfflinePayment(), { wrapper: createWrapper() });
    
    result.current.mutate({
      billUuid: "bill-cloud-uuid",
      payload: {
        payment_method_uuid: "pm-cash-uuid",
        amount: 5000,
        idempotency_key: "test-key",
      },
    });

    await waitFor(() => {
      expect(result.current.isError).toBe(true);
    });

    expect(result.current.error?.message).toContain("sin order asociado");
  });

  it("falla si método de pago no existe", async () => {
    (BillRepository.findByCloudId as any).mockResolvedValue(mockBill);
    
    const { result } = renderHook(() => useOfflinePayment(), { wrapper: createWrapper() });
    
    result.current.mutate({
      billUuid: "bill-cloud-uuid",
      payload: {
        payment_method_uuid: "pm-non-existent",
        amount: 5000,
        idempotency_key: "test-key",
      },
    });

    await waitFor(() => {
      expect(result.current.isError).toBe(true);
    });

    expect(result.current.error?.message).toContain("Método de pago no encontrado");
  });

  it("rechaza métodos de pago no soportados (ej: 'other')", async () => {
    (BillRepository.findByCloudId as any).mockResolvedValue(mockBill);
    
    const { result } = renderHook(() => useOfflinePayment(), { wrapper: createWrapper() });
    
    result.current.mutate({
      billUuid: "bill-cloud-uuid",
      payload: {
        payment_method_uuid: "pm-other-uuid", // type: "other"
        amount: 5000,
        idempotency_key: "test-key",
      },
    });

    await waitFor(() => {
      expect(result.current.isError).toBe(true);
    });

    expect(result.current.error?.message).toContain("no soportado en modo offline");
  });

  it("llama a offlinePaymentService con parámetros correctos", async () => {
    (BillRepository.findByCloudId as any).mockResolvedValue(mockBill);
    
    const { result } = renderHook(() => useOfflinePayment(), { wrapper: createWrapper() });
    
    await waitFor(() => {
      result.current.mutate({
        billUuid: "bill-cloud-uuid",
        payload: {
          payment_method_uuid: "pm-cash-uuid",
          amount: 5000,
          tip_amount: 500,
          reference_code: "REF-123",
          notes: "Pago parcial",
          idempotency_key: "test-key",
        },
      });
    });

    expect(offlinePaymentService.createPaymentOffline).toHaveBeenCalledWith({
      orderLocalUuid: "order-local-uuid",
      paymentMethod: "cash",
      amount: 5000,
      tipAmount: 500,
      referenceCode: "REF-123",
      notes: "Pago parcial",
      autoCreateBill: false,
    });
  });

  it("usa remaining_amount si no se especifica amount", async () => {
    (BillRepository.findByCloudId as any).mockResolvedValue(mockBill);
    
    const { result } = renderHook(() => useOfflinePayment(), { wrapper: createWrapper() });
    
    await waitFor(() => {
      result.current.mutate({
        billUuid: "bill-cloud-uuid",
        payload: {
          payment_method_uuid: "pm-cash-uuid",
          idempotency_key: "test-key",
        },
      });
    });

    const call = (offlinePaymentService.createPaymentOffline as any).mock.calls[0][0];
    expect(call.amount).toBe(10000); // remaining_amount de la bill
  });

  it("transforma resultado a PayBillResponse correctamente", async () => {
    (BillRepository.findByCloudId as any).mockResolvedValue(mockBill);
    (offlinePaymentService.createPaymentOffline as any).mockResolvedValue({
      payment: { amount: 10000, tip_amount: 1000 },
      bill: { 
        ...mockBill, 
        local_uuid: "bill-local-uuid",
        status: "paid", 
        paid_amount: 10000, 
        remaining_amount: 0 
      },
      orderPaid: true,
      orderStatusUpdated: true,
      tableReleased: true,
    });
    
    const { result } = renderHook(() => useOfflinePayment(), { wrapper: createWrapper() });
    
    await waitFor(() => {
      result.current.mutateAsync({
        billUuid: "bill-cloud-uuid",
        payload: {
          payment_method_uuid: "pm-cash-uuid",
          amount: 10000,
          idempotency_key: "test-key",
        },
      }).then((response) => {
        expect(response).toEqual({
          success: true,
          bill_uuid: "bill-local-uuid",
          bill_paid: true,
          paid_amount: 10000,
          remaining_amount: 0,
          order_transitioned_to_paid: true,
          amount_paid: 10000,
          tip_amount: 1000,
        });
      });
    });
  });
});
