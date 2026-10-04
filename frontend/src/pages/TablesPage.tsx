import { useState } from 'react';
import { useNavigate } from 'react-router-dom';
import { FloorPlanView } from '@/components/floor-plan/FloorPlanView';
import { useAuthStore } from '@/store/useAuthStore';
import { useTranslation } from 'react-i18next';

/**
 * Página principal de Mesas (ruta /).
 * Vista operativa por defecto para garzones.
 * Admin/manager pueden alternar a modo edición.
 */
export function TablesPage() {
  const { t } = useTranslation();
  const navigate = useNavigate();
  const user = useAuthStore((state) => state.user);
  
  // IMPORTANTE: Siempre inicia en modo operativo (false)
  const [isEditMode, setIsEditMode] = useState(false);

  // Solo admin/manager pueden editar
  const canEdit = user?.role === 'admin' || user?.role === 'manager';

  const handleToggleEditMode = () => {
    if (!canEdit) {
      alert(t('tables.edit_permission_required', 'Solo administradores pueden editar el plano'));
      return;
    }
    setIsEditMode(!isEditMode);
  };

  const handleTableClick = (tableUuid: string, tableNumber: string) => {
    // En modo operativo, al hacer click en una mesa vinculada
    // redirige a la vista de toma de pedidos
    console.log('[TablesPage] Click en mesa:', { tableUuid, tableNumber, isEditMode });
    
    if (!isEditMode) {
      // Navegar a la vista de toma de pedidos
      navigate(`/tables/${tableUuid}`);
    }
  };

  return (
    <FloorPlanView
      isEditMode={isEditMode}
      onToggleEditMode={canEdit ? handleToggleEditMode : undefined}
      onTableClick={handleTableClick}
      showModeToggle={true}
    />
  );
}
