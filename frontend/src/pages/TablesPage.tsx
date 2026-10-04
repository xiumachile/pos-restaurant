import { useState } from 'react';
import { useNavigate } from 'react-router-dom';
import { FloorPlanView } from '@/components/floor-plan/FloorPlanView';
import { useAuthStore } from '@/store/useAuthStore';
import { useTranslation } from 'react-i18next';
import { useToastStore } from '@/store/useToastStore';

function isValidUUID(uuid: string): boolean {
  const uuidRegex = /^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/i;
  return uuidRegex.test(uuid);
}

export function TablesPage() {
  const { t } = useTranslation();
  const navigate = useNavigate();
  const user = useAuthStore((state) => state.user);
  const addToast = useToastStore((s) => s.addToast);
  const [isEditMode, setIsEditMode] = useState(false);

  const canEdit = user?.role === 'admin' || user?.role === 'manager';

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
      navigate(`/tables/${tableUuid}`);
    }
  };

  return (
    <div className="h-full min-h-0 -m-4 md:-m-6">
      <FloorPlanView
        isEditMode={isEditMode}
        onToggleEditMode={canEdit ? handleToggleEditMode : undefined}
        onTableClick={handleTableClick}
        showModeToggle={true}
      />
    </div>
  );
}
