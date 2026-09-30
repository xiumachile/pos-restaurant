import { create } from 'zustand';

export interface Popup {
  id: string;
  type: 'change'; // Extensible: 'success', 'error', etc.
  amount: number;
  duration: number;
}

interface PopupState {
  currentPopup: Popup | null;
  showPopup: (type: Popup['type'], amount: number, duration?: number) => void;
  hidePopup: () => void;
}

export const usePopupStore = create<PopupState>((set) => ({
  currentPopup: null,
  
  showPopup: (type, amount, duration = 5) => {
    const id = crypto.randomUUID();
    set({ currentPopup: { id, type, amount, duration } });
  },
  
  hidePopup: () => {
    set({ currentPopup: null });
  },
}));
