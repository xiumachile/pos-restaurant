import { useTranslation } from 'react-i18next';
import { Pencil, Eye } from 'lucide-react';

interface EditModeFABProps {
  isEditMode: boolean;
  onToggleEditMode?: () => void;
}

/**
 * Floating Action Button para alternar entre modo edición y operativo.
 * Posición: esquina inferior derecha, flotando sobre el canvas.
 */
export function EditModeFAB({ isEditMode, onToggleEditMode }: EditModeFABProps) {
  const { t } = useTranslation();

  if (!onToggleEditMode) return null;

  return (
    <button
      onClick={onToggleEditMode}
      className={`
        fixed bottom-6 right-6 z-40
        flex items-center gap-2 px-4 py-3 rounded-full shadow-2xl
        font-semibold text-sm
        transition-all duration-300
        hover:scale-105 active:scale-95
        ${isEditMode
          ? 'bg-amber-500 hover:bg-amber-600 text-white'
          : 'bg-blue-500 hover:bg-blue-600 text-white'
        }
      `}
      title={isEditMode
        ? t('floor_plan.toolbar.switch_to_operational', 'Cambiar a vista operativa')
        : t('floor_plan.toolbar.switch_to_editor', 'Cambiar a modo edición')}
    >
      {isEditMode ? <Eye size={20} /> : <Pencil size={20} />}
      <span>
        {isEditMode
          ? t('floor_plan.toolbar.view_mode_short', 'Ver')
          : t('floor_plan.toolbar.edit_mode_short', 'Editar')
        }
      </span>
    </button>
  );
}
