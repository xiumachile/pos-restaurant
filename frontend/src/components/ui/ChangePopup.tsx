import { useEffect, useState } from 'react';
import { useTranslation } from 'react-i18next';

interface ChangePopupProps {
  amount: number;
  duration?: number; // segundos
  onClose: () => void;
}

/**
 * Popup grande y visible para mostrar el vuelto al cajero.
 * Se cierra automáticamente después de `duration` segundos.
 */
export function ChangePopup({
  amount,
  duration = 5,
  onClose,
}: {
  amount: number;
  duration?: number;
  onClose: () => void;
}) {
  console.log('[ChangePopup] Componente renderizado, amount:', amount); amount, duration = 5, onClose }: ChangePopupProps) {
  const { t } = useTranslation();
  const [isVisible, setIsVisible] = useState(false);
  const [countdown, setCountdown] = useState(duration);

  useEffect(() => {
    // Animación de entrada
    const enterTimeout = setTimeout(() => setIsVisible(true), 10);

    // Countdown
    const countdownInterval = setInterval(() => {
      setCountdown((prev) => {
        if (prev <= 1) {
          clearInterval(countdownInterval);
          return 0;
        }
        return prev - 1;
      });
    }, 1000);

    // Auto-cerrar
    const closeTimeout = setTimeout(() => {
      setIsVisible(false);
      setTimeout(onClose, 300); // Esperar animación de salida
    }, duration * 1000);

    // Cleanup
    return () => {
      clearTimeout(enterTimeout);
      clearInterval(countdownInterval);
      clearTimeout(closeTimeout);
    };
  }, [duration, onClose]);

  // Cerrar con tecla Escape
  useEffect(() => {
    const handleEscape = (e: KeyboardEvent) => {
      if (e.key === 'Escape') {
        setIsVisible(false);
        setTimeout(onClose, 300);
      }
    };
    window.addEventListener('keydown', handleEscape);
    return () => window.removeEventListener('keydown', handleEscape);
  }, [onClose]);

  const handleClose = () => {
    setIsVisible(false);
    setTimeout(onClose, 300);
  };

  const formatPrice = (value: number) => {
    return new Intl.NumberFormat('es-CL', {
      style: 'currency',
      currency: 'CLP',
      minimumFractionDigits: 0,
    }).format(value);
  };

  return (
    <div
      className={`fixed inset-0 z-50 flex items-center justify-center bg-black/70 backdrop-blur-sm transition-opacity duration-300 ${
        isVisible ? 'opacity-100' : 'opacity-0'
      }`}
      onClick={handleClose}
      role="dialog"
      aria-modal="true"
      aria-label="Vuelto a entregar"
    >
      <div
        className={`bg-gradient-to-br from-green-500 to-green-600 rounded-3xl shadow-2xl p-12 max-w-lg w-full mx-4 transform transition-all duration-300 ${
          isVisible ? 'scale-100' : 'scale-90'
        }`}
        onClick={(e) => e.stopPropagation()}
      >
        <div className="text-center">
          <div className="text-7xl mb-6" aria-hidden="true">💰</div>
          <h2 className="text-3xl font-bold text-white mb-8">
            {t("change_popup.title")}
          </h2>
          
          <div className="bg-white/20 backdrop-blur rounded-2xl p-10 mb-8">
            <div className="text-7xl font-bold text-white" aria-live="polite">
              {formatPrice(amount)}
            </div>
          </div>

          <div className="text-white/90 text-lg mb-6" aria-live="polite">
            {t("change_popup.closes_in", { countdown: countdown, unit: countdown === 1 ? t("change_popup.second") : t("change_popup.seconds") })}
          </div>

          <button
            onClick={handleClose}
            className="px-8 py-3 bg-white/20 hover:bg-white/30 text-white font-semibold rounded-xl transition-colors"
            aria-label="Cerrar popup"
          >
            {t("change_popup.close_now")}
          </button>
        </div>
      </div>
    </div>
  );
}
