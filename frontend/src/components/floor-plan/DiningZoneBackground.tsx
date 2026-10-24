import { useTranslation } from "react-i18next";
import type { DiningZone } from "@/types/floorPlan";
import { getTranslation } from "@/utils/floorPlanHelpers";

interface Props {
  zone: DiningZone;
}

export function DiningZoneBackground({ zone }: Props) {
  const { t } = useTranslation();
  
  // Usar dimensiones de la zona, con fallbacks por si hay datos antiguos
  const x = zone.position_x ?? 20;
  const y = zone.position_y ?? 20;
  const width = zone.width ?? 400;
  const height = zone.height ?? 300;

  return (
    <div
      className="absolute rounded-2xl pointer-events-none transition-all duration-300 border border-white/10 flex flex-col"
      style={{ 
        left: x, 
        top: y, 
        width, 
        height, 
        backgroundColor: `${zone.color}15`, 
        boxShadow: `inset 0 0 30px ${zone.color}10` 
      }}
    >
      {/* Etiqueta de la zona */}
      <div 
        className="absolute -top-3 left-4 px-3 py-1 rounded-full text-[10px] font-black uppercase tracking-widest shadow-lg border border-white/20 flex items-center gap-1.5" 
        style={{ backgroundColor: zone.color, color: "white" }}
      >
        <span className="w-1.5 h-1.5 rounded-full bg-white/50" />
        {getTranslation(zone.name_translations, zone.code)}
        <span className="opacity-70 ml-1">({zone.tables_count || 0} {t("floor_plan.zone_panel.tables", "mesas")})</span>
      </div>
      
      {/* Indicador visual de que es una zona vacía (opcional, solo en modo edición) */}
      {zone.tables_count === 0 && (
        <div className="flex-1 flex items-center justify-center opacity-30">
          <span className="text-xs font-medium" style={{ color: zone.color }}>
            {t("floor_plan.zone_panel.empty_zone", "Zona vacía")}
          </span>
        </div>
      )}
    </div>
  );
}
