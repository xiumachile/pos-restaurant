import { create } from "zustand";

export type ToastType = "success" | "error" | "warning" | "info";

interface Toast {
  id: string;
  type: ToastType;
  message: string;
  duration?: number;
}

interface ToastState {
  toasts: Toast[];
  success: (message: string, duration?: number) => void;
  error: (message: string, duration?: number) => void;
  warning: (message: string, duration?: number) => void;
  info: (message: string, duration?: number) => void;
  remove: (id: string) => void;
  clear: () => void;
}

export const useToastStore = create<ToastState>((set, get) => ({
  toasts: [],

  success: (message, duration = 4000) => {
    const id = crypto.randomUUID();
    set((state) => ({
      toasts: [...state.toasts, { id, type: "success", message, duration }],
    }));

    if (duration > 0) {
      setTimeout(() => {
        get().remove(id);
      }, duration);
    }
  },

  error: (message, duration = 6000) => {
    const id = crypto.randomUUID();
    set((state) => ({
      toasts: [...state.toasts, { id, type: "error", message, duration }],
    }));

    if (duration > 0) {
      setTimeout(() => {
        get().remove(id);
      }, duration);
    }
  },

  warning: (message, duration = 5000) => {
    const id = crypto.randomUUID();
    set((state) => ({
      toasts: [...state.toasts, { id, type: "warning", message, duration }],
    }));

    if (duration > 0) {
      setTimeout(() => {
        get().remove(id);
      }, duration);
    }
  },

  info: (message, duration = 4000) => {
    const id = crypto.randomUUID();
    set((state) => ({
      toasts: [...state.toasts, { id, type: "info", message, duration }],
    }));

    if (duration > 0) {
      setTimeout(() => {
        get().remove(id);
      }, duration);
    }
  },

  remove: (id) => {
    set((state) => ({
      toasts: state.toasts.filter((t) => t.id !== id),
    }));
  },

  clear: () => {
    set({ toasts: [] });
  },
}));
