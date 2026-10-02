import { useState } from "react";
import { useNavigate } from "react-router-dom";
import { ArrowLeft, Plus } from "lucide-react";
import { useTranslation } from "react-i18next";
import { useCartStore } from "@/stores/useCartStore";
import { ChannelSelectionModal } from "@/components/orders/ChannelSelectionModal";
import type { ChannelType } from "@/stores/useActiveChannelStore";

/**
 * Página de entrada para iniciar un pedido nuevo fuera de mesa.
 *
 * Flujo:
 *   1. Usuario llega a esta página desde el botón "+ Pedido nuevo" en TablesPage
 *   2. Se abre automáticamente el modal de selección de canal
 *   3. Al elegir un canal, se crea el cart con initOrder() y se navega al flujo
 *      de toma de pedido en /orders/takeaway/:cartKey
 *
 * Si el usuario cancela, vuelve a la página de mesas.
 */
export function NewOrderPage() {
  const { t } = useTranslation();
  const navigate = useNavigate();
  const initOrder = useCartStore((s) => s.initOrder);

  const [modalOpen, setModalOpen] = useState(true);

  const handleSelectChannel = (channel: ChannelType) => {
    const cartKey = initOrder({
      tableUuid: null,
      channel,
    });
    setModalOpen(false);
    navigate(`/orders/takeaway/${cartKey}`);
  };

  const handleClose = () => {
    setModalOpen(false);
    navigate("/");
  };

  return (
    <div className="flex flex-col h-full">
      <div className="flex items-center gap-4 mb-6">
        <button
          onClick={() => navigate("/")}
          className="p-2 bg-slate-800 hover:bg-slate-700 rounded-lg transition-colors"
          title={t("tables.back_to_tables")}
        >
          <ArrowLeft size={20} />
        </button>
        <div>
          <h1 className="text-3xl font-bold flex items-center gap-3">
            <Plus className="text-orange-500" />
            {t("orders.new_order_title")}
          </h1>
          <p className="text-slate-400 mt-1">
            {t("orders.select_channel")}
          </p>
        </div>
      </div>

      <div className="flex-1 flex items-center justify-center p-6">
        <div className="text-center max-w-md">
          <p className="text-slate-300 mb-6">
            {t("orders.new_order_hint", {
              defaultValue: "Elige el canal de venta para iniciar el pedido.",
            })}
          </p>
          <button
            onClick={() => setModalOpen(true)}
            className="px-6 py-3 bg-orange-500 hover:bg-orange-600 text-white rounded-lg font-medium transition-colors"
          >
            {t("orders.select_channel")}
          </button>
        </div>
      </div>

      <ChannelSelectionModal
        open={modalOpen}
        onSelect={handleSelectChannel}
        onClose={handleClose}
      />
    </div>
  );
}
