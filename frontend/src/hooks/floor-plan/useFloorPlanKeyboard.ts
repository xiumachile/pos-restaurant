import { useEffect } from 'react';
import { useFloorPlanStore } from '@/stores/floor-plan/floorPlanStore';

/**
 * Hook para manejar atajos de teclado del editor.
 * - Delete/Backspace: eliminar objetos seleccionados
 * - Ctrl+Z / Cmd+Z: deshacer
 * - Ctrl+Y / Cmd+Shift+Z: rehacer
 * - Ctrl+D / Cmd+D: duplicar selección
 * - Escape: limpiar selección
 */
export function useFloorPlanKeyboard() {
  const {
    editor,
    objects,
    deleteObjects,
    duplicateSelected,
    undo,
    redo,
    clearSelection,
    pushHistory,
    canUndo,
    canRedo,
  } = useFloorPlanStore();

  useEffect(() => {
    const handleKeyDown = (e: KeyboardEvent) => {
      const target = e.target as HTMLElement;
      const isInput =
        target.tagName === 'INPUT' ||
        target.tagName === 'TEXTAREA' ||
        target.isContentEditable;

      if (isInput) return;

      const isCtrl = e.ctrlKey || e.metaKey;

      // Delete / Backspace: eliminar selección
      if ((e.key === 'Delete' || e.key === 'Backspace') && !isCtrl) {
        if (editor.selectedObjectIds.length > 0) {
          e.preventDefault();
          // Registrar cada objeto en historial para poder deshacer
          editor.selectedObjectIds.forEach((id) => {
            const obj = objects.find((o) => o.uuid === id);
            if (obj) pushHistory({ type: 'DELETE_OBJECT', objectId: id, object: obj });
          });
          deleteObjects(editor.selectedObjectIds);
        }
        return;
      }

      // Ctrl+D / Cmd+D: duplicar
      if (isCtrl && e.key.toLowerCase() === 'd') {
        if (editor.selectedObjectIds.length > 0) {
          e.preventDefault();
          duplicateSelected();
        }
        return;
      }

      // Ctrl+Z / Cmd+Z: deshacer
      if (isCtrl && e.key.toLowerCase() === 'z' && !e.shiftKey) {
        e.preventDefault();
        undo();
        return;
      }

      // Ctrl+Y / Cmd+Shift+Z: rehacer
      if (
        (isCtrl && e.key.toLowerCase() === 'y') ||
        (isCtrl && e.shiftKey && e.key.toLowerCase() === 'z')
      ) {
        e.preventDefault();
        redo();
        return;
      }

      // Escape: limpiar selección
      if (e.key === 'Escape') {
        clearSelection();
        return;
      }
    };

    window.addEventListener('keydown', handleKeyDown);
    return () => window.removeEventListener('keydown', handleKeyDown);
  }, [
    editor.selectedObjectIds,
    objects,
    deleteObjects,
    duplicateSelected,
    undo,
    redo,
    clearSelection,
    pushHistory,
  ]);
}
