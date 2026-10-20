import { create } from 'zustand';
import { persist, createJSONStorage } from 'zustand/middleware';

/**
 * Store de configuraciones locales del usuario.
 * Se persiste en localStorage (no requiere backend).
 * Útil para preferencias UI que no afectan datos de negocio.
 */
interface SettingsState {
  // Popup de vuelto
  showChangePopup: boolean;
  changePopupDuration: number;
  requirePaymentReference: boolean; // Nueva capacidad: exigir código de autorización // segundos
  
  // Actions
  setShowChangePopup: (enabled: boolean) => void;
  setChangePopupDuration: (seconds: number) => void;
  setRequirePaymentReference: (enabled: boolean) => void;
}

export const useSettingsStore = create<SettingsState>()(
  persist(
    (set) => ({
      // Defaults
      showChangePopup: true,
      changePopupDuration: 5,
      requirePaymentReference: false, // Por defecto: NO exigir (envía REF-AUTO)
      
      setShowChangePopup: (enabled) => set({ showChangePopup: enabled }),
      setChangePopupDuration: (seconds) => 
        set({ changePopupDuration: Math.max(1, Math.min(30, seconds)) }),
      setRequirePaymentReference: (enabled) => set({ requirePaymentReference: enabled }),
    }),
    {
      name: 'pos-settings',
      storage: createJSONStorage(() => localStorage),
    }
  )
);
