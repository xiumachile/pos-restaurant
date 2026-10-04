import { create } from 'zustand';
import type {
  FloorPlan,
  FloorPlanObject,
  FloorPlanObjectType,
  EditorState,
  CanvasViewport,
  HistoryState,
  HistoryAction,
} from '@/types/floor-plan/floorPlan.types';

interface FloorPlanStore {
  // Estado del plano
  currentPlan: FloorPlan | null;
  objects: FloorPlanObject[];

  // Estado del editor
  editor: EditorState;
  viewport: CanvasViewport;
  history: HistoryState;

  // Acciones del plano
  setCurrentPlan: (plan: FloorPlan | null) => void;
  setObjects: (objects: FloorPlanObject[]) => void;
  addObject: (object: FloorPlanObject) => void;
  updateObject: (objectId: string, changes: Partial<FloorPlanObject>) => void;
  deleteObject: (objectId: string) => void;
  deleteObjects: (objectIds: string[]) => void;
  duplicateObject: (objectId: string) => FloorPlanObject | null;
  duplicateSelected: () => void;
  moveObject: (objectId: string, x: number, y: number) => void;

  // Acciones del editor
  selectObject: (objectId: string, addToSelection?: boolean) => void;
  selectObjects: (objectIds: string[]) => void;
  clearSelection: () => void;
  setEditorMode: (mode: EditorState['mode']) => void;
  setCreatingObjectType: (type: FloorPlanObjectType | null) => void;

  // Acciones del viewport
  setViewport: (viewport: Partial<CanvasViewport>) => void;
  zoomIn: () => void;
  zoomOut: () => void;
  resetViewport: () => void;

  // Acciones de historial
  pushHistory: (action: HistoryAction) => void;
  undo: () => void;
  redo: () => void;
  canUndo: () => boolean;
  canRedo: () => boolean;

  // Reset
  reset: () => void;
}

const MAX_HISTORY = 50;

const initialEditorState: EditorState = {
  selectedObjectIds: [],
  isDragging: false,
  isResizing: false,
  isRotating: false,
  mode: 'select',
  creatingObjectType: null,
};

const initialViewport: CanvasViewport = {
  x: 0,
  y: 0,
  scale: 1,
};

const initialHistory: HistoryState = {
  past: [],
  future: [],
};

export const useFloorPlanStore = create<FloorPlanStore>((set, get) => ({
  currentPlan: null,
  objects: [],
  editor: initialEditorState,
  viewport: initialViewport,
  history: initialHistory,

  // Acciones del plano
  setCurrentPlan: (plan) => set({ currentPlan: plan }),

  setObjects: (objects) => set({ objects }),

  addObject: (object) =>
    set((state) => ({
      objects: [...state.objects, object],
    })),

  updateObject: (objectId, changes) =>
    set((state) => ({
      objects: state.objects.map((obj) =>
        obj.uuid === objectId ? { ...obj, ...changes } : obj
      ),
    })),

  moveObject: (objectId, x, y) =>
    set((state) => ({
      objects: state.objects.map((obj) =>
        obj.uuid === objectId ? { ...obj, x, y } : obj
      ),
    })),

  deleteObject: (objectId) =>
    set((state) => ({
      objects: state.objects.filter((obj) => obj.uuid !== objectId),
      editor: {
        ...state.editor,
        selectedObjectIds: state.editor.selectedObjectIds.filter((id) => id !== objectId),
      },
    })),

  deleteObjects: (objectIds) =>
    set((state) => ({
      objects: state.objects.filter((obj) => !objectIds.includes(obj.uuid)),
      editor: {
        ...state.editor,
        selectedObjectIds: [],
      },
    })),

  duplicateObject: (objectId) => {
    const state = get();
    const original = state.objects.find((obj) => obj.uuid === objectId);
    if (!original || !state.currentPlan) return null;

    const duplicate: FloorPlanObject = {
      ...original,
      id: Date.now(),
      uuid: crypto.randomUUID(),
      x: original.x + 40,
      y: original.y + 40,
      created_at: new Date().toISOString(),
      updated_at: new Date().toISOString(),
    };

    set({ objects: [...state.objects, duplicate] });
    state.pushHistory({ type: 'CREATE_OBJECT', object: duplicate });
    return duplicate;
  },

  duplicateSelected: () => {
    const state = get();
    const newSelection: string[] = [];

    state.editor.selectedObjectIds.forEach((id) => {
      const original = state.objects.find((obj) => obj.uuid === id);
      if (original && state.currentPlan) {
        const duplicate: FloorPlanObject = {
          ...original,
          id: Date.now() + newSelection.length,
          uuid: crypto.randomUUID(),
          x: original.x + 40,
          y: original.y + 40,
          created_at: new Date().toISOString(),
          updated_at: new Date().toISOString(),
        };
        set({ objects: [...get().objects, duplicate] });
        state.pushHistory({ type: 'CREATE_OBJECT', object: duplicate });
        newSelection.push(duplicate.uuid);
      }
    });

    if (newSelection.length > 0) {
      state.selectObjects(newSelection);
    }
  },

  // Acciones del editor
  selectObject: (objectId, addToSelection = false) =>
    set((state) => ({
      editor: {
        ...state.editor,
        selectedObjectIds: addToSelection
          ? state.editor.selectedObjectIds.includes(objectId)
            ? state.editor.selectedObjectIds.filter((id) => id !== objectId)
            : [...state.editor.selectedObjectIds, objectId]
          : [objectId],
      },
    })),

  selectObjects: (objectIds) =>
    set((state) => ({
      editor: {
        ...state.editor,
        selectedObjectIds: objectIds,
      },
    })),

  clearSelection: () =>
    set((state) => ({
      editor: {
        ...state.editor,
        selectedObjectIds: [],
      },
    })),

  setEditorMode: (mode) =>
    set((state) => ({
      editor: {
        ...state.editor,
        mode,
      },
    })),

  setCreatingObjectType: (type) =>
    set((state) => ({
      editor: {
        ...state.editor,
        creatingObjectType: type,
        mode: type ? 'create' : 'select',
      },
    })),

  // Acciones del viewport
  setViewport: (viewport) =>
    set((state) => ({
      viewport: { ...state.viewport, ...viewport },
    })),

  zoomIn: () =>
    set((state) => ({
      viewport: {
        ...state.viewport,
        scale: Math.min(state.viewport.scale * 1.2, 3),
      },
    })),

  zoomOut: () =>
    set((state) => ({
      viewport: {
        ...state.viewport,
        scale: Math.max(state.viewport.scale / 1.2, 0.2),
      },
    })),

  resetViewport: () => set({ viewport: initialViewport }),

  // Acciones de historial
  pushHistory: (action) =>
    set((state) => {
      const past = [...state.history.past, action].slice(-MAX_HISTORY);
      return {
        history: {
          past,
          future: [],
        },
      };
    }),

  undo: () =>
    set((state) => {
      if (state.history.past.length === 0) return state;

      const action = state.history.past[state.history.past.length - 1];
      const past = state.history.past.slice(0, -1);
      const future = [action, ...state.history.future];

      // Revertir la acción
      let newObjects = state.objects;

      switch (action.type) {
        case 'CREATE_OBJECT':
          newObjects = state.objects.filter((obj) => obj.uuid !== action.object.uuid);
          break;
        case 'UPDATE_OBJECT':
          newObjects = state.objects.map((obj) =>
            obj.uuid === action.objectId
              ? { ...obj, ...action.changes }
              : obj
          );
          break;
        case 'DELETE_OBJECT':
          newObjects = [...state.objects, action.object];
          break;
        case 'MOVE_OBJECTS':
          newObjects = state.objects.map((obj) => {
            const originalPos = action.positions[obj.uuid];
            return originalPos ? { ...obj, x: originalPos.x, y: originalPos.y } : obj;
          });
          break;
      }

      return {
        objects: newObjects,
        history: { past, future },
      };
    }),

  redo: () =>
    set((state) => {
      if (state.history.future.length === 0) return state;

      const action = state.history.future[0];
      const past = [...state.history.past, action];
      const future = state.history.future.slice(1);

      // Aplicar la acción
      let newObjects = state.objects;

      switch (action.type) {
        case 'CREATE_OBJECT':
          newObjects = [...state.objects, action.object];
          break;
        case 'UPDATE_OBJECT':
          newObjects = state.objects.map((obj) =>
            obj.uuid === action.objectId
              ? { ...obj, ...action.changes }
              : obj
          );
          break;
        case 'DELETE_OBJECT':
          newObjects = state.objects.filter((obj) => obj.uuid !== action.objectId);
          break;
        case 'MOVE_OBJECTS':
          newObjects = state.objects.map((obj) => {
            const newPos = action.positions[obj.uuid];
            return newPos ? { ...obj, x: newPos.x, y: newPos.y } : obj;
          });
          break;
      }

      return {
        objects: newObjects,
        history: { past, future },
      };
    }),

  canUndo: () => get().history.past.length > 0,
  canRedo: () => get().history.future.length > 0,

  // Reset
  reset: () =>
    set({
      currentPlan: null,
      objects: [],
      editor: initialEditorState,
      viewport: initialViewport,
      history: initialHistory,
    }),
}));
