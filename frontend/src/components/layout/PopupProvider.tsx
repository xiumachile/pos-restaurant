import { useEffect } from 'react';
import { usePopupStore } from '@/store/usePopupStore';
import { ChangePopup } from '@/components/ui/ChangePopup';

export function PopupProvider() {
  const currentPopup = usePopupStore((state) => state.currentPopup);
  const hidePopup = usePopupStore((state) => state.hidePopup);

  if (!currentPopup) return null;

  return (
    <ChangePopup
      amount={currentPopup.amount}
      duration={currentPopup.duration}
      onClose={hidePopup}
    />
  );
}
