import { describe, it, expect, beforeEach } from "vitest";
import { useCartStore } from "@/stores/useCartStore";

/**
 * Tests del canal como atributo del pedido (H5).
 * 
 * Caso mínimo crítico de la especificación:
 *   "Tomar un pedido de delivery, luego entrar a una mesa física
 *    sin cerrar la app: la mesa se factura con precio dine_in,
 *    sin acción manual del garzón."
 * 
 * Estos tests garantizan que el canal vive POR PEDIDO (cart),
 * no en un store global que pueda filtrarse entre pedidos.
 */
describe("useCartStore - canal por pedido", () => {
  beforeEach(() => {
    // Limpiar estado entre tests
    useCartStore.setState({ carts: {} });
  });

  describe("initOrder (reemplaza initCart)", () => {
    it("crea un cart de mesa con canal dine_in implícito", () => {
      const { initOrder } = useCartStore.getState();
      
      initOrder({
        tableUuid: "table-uuid-1",
        tableNumber: "5",
        areaName: "Salón principal",
        channel: "dine_in",
      });

      const cart = useCartStore.getState().getCart("table-uuid-1");
      expect(cart).not.toBeNull();
      expect(cart?.channel).toBe("dine_in");
      expect(cart?.tableUuid).toBe("table-uuid-1");
    });

    it("crea un cart de pedido fuera de mesa con canal delivery", () => {
      const { initOrder } = useCartStore.getState();
      
      initOrder({
        tableUuid: null,
        channel: "delivery",
      });

      // Debe existir un cart con key tipo takeaway
      const carts = useCartStore.getState().carts;
      const keys = Object.keys(carts);
      expect(keys.length).toBe(1);
      expect(keys[0]).toMatch(/^takeaway-/);
      
      const cart = carts[keys[0]];
      expect(cart.channel).toBe("delivery");
      expect(cart.tableUuid).toBeNull();
    });

    it("crea un cart de pedido fuera de mesa con canal takeout", () => {
      const { initOrder } = useCartStore.getState();
      
      initOrder({
        tableUuid: null,
        channel: "takeout",
      });

      const carts = useCartStore.getState().carts;
      const keys = Object.keys(carts);
      const cart = carts[keys[0]];
      expect(cart.channel).toBe("takeout");
    });
  });

  describe("aislamiento de canales entre pedidos", () => {
    it("el canal de un pedido de delivery NO se filtra a una mesa", () => {
      const { initOrder } = useCartStore.getState();

      // Paso 1: Crear pedido de delivery
      initOrder({ tableUuid: null, channel: "delivery" });

      // Paso 2: Crear pedido de mesa (sin cerrar la app, sin acción manual)
      initOrder({
        tableUuid: "mesa-uuid-1",
        tableNumber: "3",
        areaName: "Terraza",
        channel: "dine_in",
      });

      // Verificación: cada cart mantiene SU propio canal
      const carts = useCartStore.getState().carts;
      
      const deliveryCart = Object.values(carts).find(c => c.tableUuid === null);
      const mesaCart = carts["mesa-uuid-1"];

      expect(deliveryCart?.channel).toBe("delivery");
      expect(mesaCart?.channel).toBe("dine_in");
      expect(deliveryCart?.channel).not.toBe(mesaCart?.channel);
    });

    it("dos mesas distintas tienen canales independientes", () => {
      const { initOrder } = useCartStore.getState();

      initOrder({
        tableUuid: "mesa-uuid-1",
        tableNumber: "1",
        channel: "dine_in",
      });
      initOrder({
        tableUuid: "mesa-uuid-2",
        tableNumber: "2",
        channel: "dine_in",
      });

      const cart1 = useCartStore.getState().getCart("mesa-uuid-1");
      const cart2 = useCartStore.getState().getCart("mesa-uuid-2");

      expect(cart1?.channel).toBe("dine_in");
      expect(cart2?.channel).toBe("dine_in");
      expect(cart1).not.toBe(cart2);
    });

    it("múltiples pedidos fuera de mesa coexisten sin mezclar canales", () => {
      const { initOrder } = useCartStore.getState();

      initOrder({ tableUuid: null, channel: "delivery" });
      initOrder({ tableUuid: null, channel: "takeout" });
      initOrder({ tableUuid: null, channel: "delivery" });

      const carts = useCartStore.getState().carts;
      const keys = Object.keys(carts);
      
      expect(keys.length).toBe(3);
      keys.forEach(key => expect(key).toMatch(/^takeaway-/));

      const channels = Object.values(carts).map(c => c.channel);
      expect(channels).toContain("delivery");
      expect(channels).toContain("takeout");
    });
  });

  describe("clearCart mantiene el aislamiento", () => {
    it("vaciar un cart de mesa no afecta otros carts", () => {
      const { initOrder, addItem, clearCart } = useCartStore.getState();

      initOrder({ tableUuid: "mesa-1", tableNumber: "1", channel: "dine_in" });
      initOrder({ tableUuid: null, channel: "delivery" });

      // Agregar items a ambos
      const fakeProduct = {
        id: 1, uuid: "p-1", company_id: 1, branch_id: 1,
        category_id: 1, sku: "SKU1",
        name_translations: { es: "Producto" },
        description_translations: null,
        base_price: "1000", tax_rate: "19",
        is_combo: false, kitchen_zone_id: null,
        is_active: true, created_at: "", updated_at: "",
        deleted_at: null, tax_id: null,
      };
      addItem("mesa-1", fakeProduct);

      // Vaciar el de mesa
      clearCart("mesa-1");

      // El cart de delivery debe seguir intacto
      const carts = useCartStore.getState().carts;
      const deliveryCart = Object.values(carts).find(c => c.tableUuid === null);
      expect(deliveryCart?.channel).toBe("delivery");
    });
  });
});
