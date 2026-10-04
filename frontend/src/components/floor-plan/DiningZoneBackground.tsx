import { useTranslation } from "react-i18next";
import type { DiningZone, TablePosition } from "@/types/floorPlan";
import { getTranslation } from "@/utils/floorPlanHelpers";

interface Props {
  zone: DiningZone;
  tables: TablePosition[];
}

/**
 * Renderiza el fondo visual de una zona calculando automáticamente
 * el rectángulo que contiene todas sus mesas (con padding).
 */
export function DiningZoneBackground({ zone, tables }: Props) {
  const { t } = useTranslation();
  const zoneTables = tables.filter((t) => t.zone_uuid === zone.uuid);

  if (zoneTables.length === 0) {
    // Zona vacía: renderizar un placeholder pequeño
    return (
      <div
        className="absolute border-2 border-dashed rounded-lg flex items-center justify-center pointer-events-none"
        style={{
          left: 20,
          top: 20,
          width: 200,
          height: 100,
          backgroundColor: zone.color + "15",
          borderColor: zone.color + "60",
        }}
      >
        <div className="text-center">
          <p className="text-xs font-medium" style={{ color: zone.color }}>
            {getTranslation(zone.name_translations, zone.code)}
          </p>
          <p className="text-xs opacity-60 mt-1">
            {t("floor_plan.zone_panel.empty_zone")}
          </p>
        </div>
      </div>
    );
  }

  // Calcular bounding box de las mesas de la zona
  const padding = 30;
  const minX = Math.min(...zoneTables.map((t) => t.position_x)) - padding;
  const minY = Math.min(...zoneTables.map((t) => t.position_y)) - padding;
  const maxX =
    Math.max(
      ...zoneTables.map((t) => {
        const w = t.shape === "rectangle" ? (t.width ?? 120) : 80;
        return t.position_x + w;
      })
    ) + padding;
  const maxY =
    Math.max(
      ...zoneTables.map((t) => {
        const h = t.shape === "rectangle" ? (t.height ?? 80) : 80;
        return t.position_y + h;
      })
    ) + padding;

  const width = Math.max(maxX - minX, 200);
  const height = Math.max(maxY - minY, 120);

  return (
    <div
      className="absolute border-2 border-dashed rounded-xl pointer-events-none transition-all"
      style={{
        left: minX,
        top: minY,
        width,
        height,
        backgroundColor: zone.color + "12",
        borderColor: zone.color + "50",
      }}
    >
      <div
        className="absolute top-1 left-2 px-2 py-0.5 rounded text-xs font-semibold"
        style={{ backgroundColor: zone.color, color: "white" }}
      >
        {getTranslation(zone.name_translations, zone.code)}
      </div>
    </div>
  );
}
