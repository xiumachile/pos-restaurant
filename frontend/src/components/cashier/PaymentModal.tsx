import { TableBillModal } from "./TableBillModal";
import { OrderPaymentModal } from "./OrderPaymentModal";
import type { Order } from "@/types/orders";

export type PaymentEntityType = "table" | "order";

interface PaymentModalProps {
  entityUuid: string;
  entityType: PaymentEntityType;
  order?: Order; // Solo necesario cuando entityType es "order"
  isOpen: boolean;
  onClose: () => void;
  onSuccess: () => void;
}

/**
 * Modal de pago universal para el sistema POS.
 * 
 * Maneja transparentemente dos tipos de entidades:
 * - "table": Mesa con múltiples pedidos (flujo: precuenta → preparar bills → cobrar)
 * - "order": Pedido individual delivery/takeout (flujo: cobrar directamente)
 * 
 * Este componente actúa como un router que delega al modal específico
 * según el tipo de entidad, manteniendo una interfaz unificada para CashierPage.
 */
export function PaymentModal({
  entityUuid,
  entityType,
  order,
  isOpen,
  onClose,
  onSuccess,
}: PaymentModalProps) {
  // Delegar al modal específico según el tipo de entidad
  if (entityType === "table") {
    return (
      <TableBillModal
        tableUuid={entityUuid}
        isOpen={isOpen}
        onClose={onClose}
        onSuccess={onSuccess}
      />
    );
  }

  if (entityType === "order") {
    if (!order) {
      console.error("[PaymentModal] entityType es 'order' pero no se proporcionó el objeto order");
      onClose();
      return null;
    }

    return (
      <OrderPaymentModal
        order={order}
        isOpen={isOpen}
        onClose={onClose}
        onSuccess={onSuccess}
      />
    );
  }

  // Tipo desconocido
  console.error(`[PaymentModal] Tipo de entidad desconocido: ${entityType}`);
  return null;
}
