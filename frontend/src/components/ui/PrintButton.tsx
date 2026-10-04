import { useState } from "react";
import { useTranslation } from "react-i18next";
import { Printer, Loader2 } from "lucide-react";

interface PrintButtonProps {
  title: string;
  subtitle?: string;
  dateRangeLabel?: string;
  className?: string;
}

/**
 * Botón que dispara window.print() con optimizaciones para impresión.
 * Agrega una clase temporal al <html> para que los componentes puedan
 * reaccionar (ej: desactivar animaciones de recharts).
 */
export function PrintButton({ title, subtitle, dateRangeLabel, className = "" }: PrintButtonProps) {
  const { t } = useTranslation();
  const [isPrinting, setIsPrinting] = useState(false);

  const handlePrint = async () => {
    setIsPrinting(true);

    // Actualizar header de impresión con datos actuales
    const headerEl = document.querySelector('[data-print-header="true"]');
    if (headerEl) {
      const now = new Date();
      const formattedDate = now.toLocaleString("es-CL", {
        year: "numeric",
        month: "long",
        day: "numeric",
        hour: "2-digit",
        minute: "2-digit",
      });
      headerEl.innerHTML = `
        <h1 style="font-size: 22pt; margin: 0 0 8px 0; color: #000;">${title}</h1>
        ${subtitle ? `<p style="margin: 0 0 4px 0; color: #666; font-size: 10pt;">${subtitle}</p>` : ""}
        ${dateRangeLabel ? `<p style="margin: 0 0 4px 0; color: #666; font-size: 10pt;">${t("reports.print.date_range")}: ${dateRangeLabel}</p>` : ""}
        <p style="margin: 0; color: #666; font-size: 10pt;">${t("reports.print.generated_on")}: ${formattedDate}</p>
      `;
    }

    // Dar tiempo a React para re-renderizar sin animaciones
    await new Promise((resolve) => setTimeout(resolve, 100));

    // Disparar diálogo de impresión
    window.print();

    // Restaurar estado después
    setTimeout(() => setIsPrinting(false), 500);
  };

  return (
    <button
      onClick={handlePrint}
      disabled={isPrinting}
      className={`flex items-center gap-2 px-4 py-2 bg-slate-700 hover:bg-slate-600 disabled:bg-slate-800 disabled:text-slate-500 text-white rounded-lg transition-colors ${className}`}
      title={t("reports.print.button")}
    >
      {isPrinting ? (
        <Loader2 size={16} className="animate-spin" />
      ) : (
        <Printer size={16} />
      )}
      <span className="text-sm font-medium">{t("reports.print.button")}</span>
    </button>
  );
}
