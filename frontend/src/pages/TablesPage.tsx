import { useState, useEffect } from 'react';
import { useNavigate, useLocation } from 'react-router-dom';
import { FloorPlanView } from '@/components/floor-plan/FloorPlanView';
import { useAuthStore } from '@/store/useAuthStore';
import { useTranslation } from 'react-i18next';
import { useToastStore } from '@/store/useToastStore';
import { useFloorPlanStore } from '@/stores/floor-plan/floorPlanStore';

function isValidUUID(uuid: string): boolean {
  const uuidRegex = /^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/i;
  return uuidRegex.test(uuid);
}

export function TablesPage() {
  const { t } = useTranslation();
  const navigate = useNavigate();
  const location = useLocation();
  const user = useAuthStore((state) => state.user);
  const addToast = useToastStore((s) => s.addToast);
  const reset = useFloorPlanStore((s) => s.reset);
  const [isEditMode, setIsEditMode] = useState(false);

  const canEdit = user?.role === 'admin' || user?.role === 'manager';

  // Resetear estado del plano cuando se desmonta (navega a otra ruta)
  useEffect(() => {
    return () => {
      console.log('[TablesPage] Desmontando - reseteando estado del plano');
      reset();
      setIsEditMode(false);
    };
  }, [reset]);

  // Si la ruta cambia (ej: navegar a /tables/:uuid), forzar desmontaje
  useEffect(() => {
    if (location.pathname !== '/' && location.pathname !== '/tables') {
      reset();
      setIsEditMode(false);
    }
  }, [location.pathname, reset]);

  const handleToggleEditMode = () => {
    if (!canEdit) {
      addToast('error', t('tables.only_admin_can_edit', 'Solo administradores pueden editar el plano'));
      return;
    }
    setIsEditMode(!isEditMode);
  };

  const handleTableClick = (tableUuid: string, tableNumber: string) => {
    console.log('[TablesPage] Click en mesa:', { tableUuid, tableNumber, isEditMode });
    if (!isEditMode) {
      if (!tableUuid || tableUuid.trim() === '') {
        addToast('warning', t('tables.not_linked_message', 'Esta mesa del plano no está vinculada con una mesa real. Edita el plano para vincularla.'));
        return;
      }
      if (!isValidUUID(tableUuid)) {
        addToast('error', t('tables.invalid_link_message', 'Esta mesa tiene un vínculo corrupto. Edita el plano y vuelve a vincularla.'));
        return;
      }
      // Resetear estado ANTES de navegar (previene residuos)
      reset();
      setIsEditMode(false);
      navigate(`/tables/${tableUuid}`);
    }
  };

  return (
    <div className="h-[calc(100vh-8rem)]">
      <FloorPlanView
        isEditMode={isEditMode}
        onToggleEditMode={canEdit ? handleToggleEditMode : undefined}
        onTableClick={handleTableClick}
        showModeToggle={true}
      />
    </div>
  );
}
