import { useState } from 'react';
import { useNavigate } from 'react-router-dom';
import { FloorPlanView } from '@/components/floor-plan/FloorPlanView';
import { useAuthStore } from '@/store/useAuthStore';

/**
 * Página principal de Mesas (ruta /).
 * Ahora usa el plano diseñado como vista operativa para garzones.
 * Los administradores pueden alternar entre modo operativo y modo edición.
 */
export function TablesPage() {
  const navigate = useNavigate();
  const user = useAuthStore((state) => state.user);
  const [isEditMode, setIsEditMode] = useState(false);

  // Solo admin/manager pueden editar
  const canEdit = user?.role === 'admin' || user?.role === 'manager';

  const handleToggleEditMode = () => {
    if (!canEdit) {
      alert('Solo administradores pueden editar el plano');
      return;
    }
    setIsEditMode(!isEditMode);
  };

  const handleTableClick = (tableUuid: string, tableNumber: string) => {
    // En modo operativo, al hacer click en una mesa vinculada
    // redirige a la vista de toma de pedidos
    // TODO: Integrar con el flujo real de Orders en F6
    console.log('Click en mesa:', { tableUuid, tableNumber });

    // Por ahora mostramos alerta, en F6 se integrará con el modal de pedidos
    // navigate(`/orders/new?table_uuid=${tableUuid}`);
  };

  return (
    <FloorPlanView
      isEditMode={isEditMode}
      onToggleEditMode={canEdit ? handleToggleEditMode : undefined}
      onTableClick={handleTableClick}
    />
  );
}
