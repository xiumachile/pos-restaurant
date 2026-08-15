import { useCallback } from "react";

interface PinKeypadProps {
  pin: string;
  onPinChange: (pin: string) => void;
  maxLength?: number;
  disabled?: boolean;
}

/**
 * Teclado numérico para login POS con PIN.
 * Diseñado para pantalla táctil en terminales de restaurante.
 */
export function PinKeypad({
  pin,
  onPinChange,
  maxLength = 6,
  disabled = false,
}: PinKeypadProps) {
  const handleDigit = useCallback(
    (digit: string) => {
      if (disabled) return;
      if (pin.length < maxLength) {
        onPinChange(pin + digit);
      }
    },
    [pin, maxLength, onPinChange, disabled]
  );

  const handleClear = useCallback(() => {
    if (disabled) return;
    onPinChange("");
  }, [onPinChange, disabled]);

  const handleBackspace = useCallback(() => {
    if (disabled) return;
    onPinChange(pin.slice(0, -1));
  }, [pin, onPinChange, disabled]);

  const digits = ["1", "2", "3", "4", "5", "6", "7", "8", "9"];

  return (
    <div className="w-full max-w-xs mx-auto" role="group" aria-label="Teclado PIN">
      {/* Indicador de PIN ingresado */}
      <div
        className="flex justify-center gap-3 mb-6"
        role="group"
        aria-label={`PIN: ${pin.length} de ${maxLength} dígitos ingresados`}
      >
        {Array.from({ length: maxLength }).map((_, i) => (
          <div
            key={i}
            data-testid="pin-dot"
            role="img"
            aria-label={i < pin.length ? "Dígito ingresado" : "Dígito vacío"}
            className={`w-4 h-4 rounded-full border-2 transition-all ${
              i < pin.length
                ? "bg-orange-500 border-orange-500"
                : "bg-transparent border-slate-600"
            }`}
          />
        ))}
      </div>

      {/* Teclado numérico */}
      <div className="grid grid-cols-3 gap-3">
        {digits.map((digit) => (
          <button
            key={digit}
            type="button"
            disabled={disabled}
            onClick={() => handleDigit(digit)}
            aria-label={`Dígito ${digit}`}
            className="aspect-square text-2xl font-semibold rounded-lg bg-slate-800 hover:bg-slate-700 active:bg-slate-600 text-white transition-colors disabled:opacity-50 disabled:cursor-not-allowed"
          >
            {digit}
          </button>
        ))}

        <button
          type="button"
          disabled={disabled}
          onClick={handleClear}
          aria-label="Limpiar todo"
          className="aspect-square text-sm font-semibold rounded-lg bg-red-900/50 hover:bg-red-900 active:bg-red-800 text-red-300 transition-colors disabled:opacity-50"
        >
          C
        </button>

        <button
          type="button"
          disabled={disabled}
          onClick={() => handleDigit("0")}
          aria-label="Dígito 0"
          className="aspect-square text-2xl font-semibold rounded-lg bg-slate-800 hover:bg-slate-700 active:bg-slate-600 text-white transition-colors disabled:opacity-50 disabled:cursor-not-allowed"
        >
          0
        </button>

        <button
          type="button"
          disabled={disabled}
          onClick={handleBackspace}
          aria-label="Borrar último dígito"
          className="aspect-square text-2xl rounded-lg bg-slate-800 hover:bg-slate-700 active:bg-slate-600 text-white transition-colors disabled:opacity-50"
        >
          ⌫
        </button>
      </div>
    </div>
  );
}
