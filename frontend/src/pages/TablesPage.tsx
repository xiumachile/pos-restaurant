import { useState } from 'react';
import { useNavigate } from 'react-router-dom';
import { FloorPlanView } from '@/components/floor-plan/FloorPlanView';
import { useAuthStore } from '@/store/useAuthStore';
import { useTranslation } from 'react-i18next';
import { useToastStore } from '@/store/useToastStore';

/**
 * Valida que un string sea un UUID válido
 */
function isValidUUID(uuid: string): boolean {
  const uuidRegex = /^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/i;
  return uuidRegex.test(uuid);
}

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
      // VALIDACIÓN CRÍTICA #1: Verificar que tableUuid no esté vacío
      if (!tableUuid || tableUuid.trim() === '') {
        console.warn('[TablesPage] Mesa sin object_key (no vinculada)');
        addToast(
          'warning',
          t('tables.not_linked_message', 'Esta mesa del plano no está vinculada con una mesa real. Edita el plano para vincularla.')
        );
        return;
      }
      
      // VALIDACIÓN CRÍTICA #2: Verificar que tableUuid sea un UUID válido
      if (!isValidUUID(tableUuid)) {
        console.error('[TablesPage] object_key inválido (no es UUID):', tableUuid);
        addToast(
          'error',
          t('tables.invalid_link_message', 'Esta mesa tiene un vínculo corrupto. Edita el plano y vuelve a vincularla con una mesa real.')
        );
        return;
      }
      
      // Navegar a la vista de toma de pedidos
      console.log('[TablesPage] Navegando a pedido con UUID válido:', tableUuid);
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
