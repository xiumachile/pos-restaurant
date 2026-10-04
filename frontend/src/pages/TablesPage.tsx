import { useState } from 'react';
import { useNavigate } from 'react-router-dom';
import { FloorPlanView } from '@/components/floor-plan/FloorPlanView';
import { useAuthStore } from '@/store/useAuthStore';
import { useTranslation } from 'react-i18next';
import { useToastStore } from '@/store/useToastStore';

/**
 * Página principal de Mesas (ruta /).
 * Vista operativa por defecto para garzones.
 * Admin/manager pueden alternar a modo edición.
 */
export function TablesPage() {
  const { t } = useTranslation();
  const navigate = useNavigate();
  const user = useAuthStore((state) => state.user);
  const addToast = useToastStore((s) => s.addToast);
  
  // IMPORTANTE: Siempre inicia en modo operativo (false)
  const [isEditMode, setIsEditMode] = useState(false);

  // Solo admin/manager pueden editar
  const canEdit = user?.role === 'admin' || user?.role === 'manager';

  const handleToggleEditMode = () => {
    if (!canEdit) {
      addToast(
        'error',
        t('tables.only_admin_can_edit', 'Solo administradores pueden editar el plano')
      );
      return;
    }
    setIsEditMode(!isEditMode);
  };

  const handleTableClick = (tableUuid: string, tableNumber: string) => {
    console.log('[TablesPage] Click en mesa:', { tableUuid, tableNumber, isEditMode });
    
    if (!isEditMode) {
      // VALIDACIÓN CRÍTICA: Solo navegar si object_key existe (mesa vinculada)
      if (!tableUuid || tableUuid === '') {
        addToast(
          'warning',
          t('tables.not_linked_message', 'Esta mesa del plano no está vinculada con una mesa real. Edita el plano para vincularla.')
        );
        return;
      }
      
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
