import { describe, it, expect, vi, beforeEach } from "vitest";

// 1. Mockear el módulo COMPLETO antes de cualquier importación
vi.mock("../../services/authContext", () => ({
  getAuthContextSafe: vi.fn(),
  getCashierContextSafe: vi.fn(),
}));

// 2. Importar el repositorio y la función mockeada
import { PaymentRepository } from "../../db/repositories/PaymentRepository";
import { getAuthContextSafe } from "../../services/authContext";

describe("AUD-03: Validación estricta de identidad del operador", () => {
  beforeEach(() => {
    vi.clearAllMocks();
  });

  it("DEBE rechazar la creación de pago INMEDIATAMENTE si falta el usuario autenticado", async () => {
    const dummyOrderUuid = "dummy-order-aud03-" + Date.now();
    const payload = {
      company_id: "test-company",
      branch_id: "test-branch",
      order_local_uuid: dummyOrderUuid,
      amount: 10000,
      tip_amount: 0,
      payment_method: "cash" as const,
    };

    // Configurar el mock para devolver undefined (sin usuario)
    vi.mocked(getAuthContextSafe).mockReturnValue(null);

    console.log("🔍 DEBUG: getAuthContextSafe() devuelve:", getAuthContextSafe());
    console.log("🔍 DEBUG: getAuthContextSafe()?.user_id es:", getAuthContextSafe()?.user_id);

    let thrownError: any = null;
    try {
      await PaymentRepository.create(payload);
      console.log("🔍 DEBUG: PaymentRepository.create() se resolvió SIN lanzar error.");
    } catch (e: any) {
      thrownError = e;
      console.log("🔍 DEBUG: PaymentRepository.create() lanzó error:", e.message);
    }

    // Ahora hacemos las aserciones con la información real
    expect(thrownError).toBeTruthy();
    expect(thrownError.message).toContain("AUD-03: Usuario autenticado requerido");
  });
});
