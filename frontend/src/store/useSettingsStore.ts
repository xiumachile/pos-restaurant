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
  changePopupDuration: number; // segundos
  
  // Actions
  setShowChangePopup: (enabled: boolean) => void;
  setChangePopupDuration: (seconds: number) => void;
}

export const useSettingsStore = create<SettingsState>()(
  persist(
    (set) => ({
      // Defaults
      showChangePopup: true,
      changePopupDuration: 5,
      
      setShowChangePopup: (enabled) => set({ showChangePopup: enabled }),
      setChangePopupDuration: (seconds) => 
        set({ changePopupDuration: Math.max(1, Math.min(30, seconds)) }),
    }),
    {
      name: 'pos-settings',
      storage: createJSONStorage(() => localStorage),
    }
  )
);
