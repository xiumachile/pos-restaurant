import { create } from 'zustand';

interface ConfirmState {
  isOpen: boolean;
  title: string;
  message: string;
  confirmText: string;
  cancelText: string;
  variant: 'warning' | 'danger' | 'info';
  onConfirm: () => void;
  onCancel: () => void;
  open: (options: {
    title: string;
    message: string;
    confirmText?: string;
    cancelText?: string;
    variant?: 'warning' | 'danger' | 'info';
    onConfirm: () => void;
    onCancel?: () => void;
  }) => void;
  close: () => void;
}

export const useConfirmStore = create<ConfirmState>((set) => ({
  isOpen: false,
  title: '',
  message: '',
  confirmText: 'common.confirm',
  cancelText: 'common.cancel',
  variant: 'warning',
  onConfirm: () => {},
  onCancel: () => {},
  open: ({ title, message, confirmText = 'common.confirm', cancelText = 'common.cancel', variant = 'warning', onConfirm, onCancel }) =>
    set({ isOpen: true, title, message, confirmText, cancelText, variant, onConfirm, onCancel: onCancel || (() => {}) }),
  close: () => set({ isOpen: false }),
}));
