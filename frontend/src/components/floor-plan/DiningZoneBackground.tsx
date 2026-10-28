// src/components/floor-plan/DiningZoneBackground.tsx
import React from "react";
import type { FloorPlanZone } from "@/types/floorPlan";

interface Props {
  zone: FloorPlanZone;
}

export const DiningZoneBackground: React.FC<Props> = ({ zone }) => {
  return (
    <div
      className="absolute border-2 border-dashed pointer-events-none"
      style={{
        left: zone.position_x || 0,
        top: zone.position_y || 0,
        width: zone.width || 400,
        height: zone.height || 300,
        backgroundColor: `${zone.color || '#cccccc'}15`,
        borderColor: zone.color || '#cccccc',
      }}
    >
      <div className="absolute top-2 left-2 bg-black/60 px-2 py-1 rounded text-xs font-bold text-white">
        {zone.code} ({zone.tables_count || 0} mesas)
      </div>
    </div>
  );
};
