import React from "react";
import type { FloorPlanObject } from "@/types/floorPlan";
import { TableRenderer } from "./TableRenderer";
import { WallRenderer } from "./WallRenderer";
import { WindowRenderer } from "./WindowRenderer";
import { DoorRenderer } from "./DoorRenderer";
import { DoubleDoorRenderer } from "./DoubleDoorRenderer";
import { PlantRenderer } from "./PlantRenderer";
import { BoothRenderer } from "./BoothRenderer";
import { CounterRenderer } from "./CounterRenderer";
import { ColumnRenderer } from "./ColumnRenderer";
import { StoolRenderer } from "./StoolRenderer";
import { ServiceStationRenderer } from "./ServiceStationRenderer";
import { calculateSeatLayout } from "../catalog/furnitureCatalog";

interface ObjectRendererProps {
  object: FloorPlanObject;
  statusColor?: string;
  showChairs?: boolean;
}

const DEFAULT_STATUS_COLOR = "#10b981";

export const ObjectRenderer: React.FC<ObjectRendererProps> = ({ object, statusColor, showChairs = true }) => {
  const width = object.width || 80;
  const height = object.height || 80;
  const color = statusColor || DEFAULT_STATUS_COLOR;
  const type = (object.object_type || "").toLowerCase();

  const rawLayout = object.properties?.seat_layout;
  const capacity = object.properties?.capacity || object.operational_data?.capacity || 4;
  const shape = object.properties?.shape || "square";

  const seatLayout = (rawLayout && typeof rawLayout === "object")
    ? (rawLayout as any)
    : calculateSeatLayout(capacity, shape as any);

  if (type.includes("table")) {
    let tableShape: "round" | "square" | "rectangle" = "square";
    if (type.includes("round")) tableShape = "round";
    else if (type.includes("rect")) tableShape = "rectangle";

    return (
      <TableRenderer
        shape={tableShape}
        width={width}
        height={height}
        seatLayout={seatLayout}
        statusColor={color}
        showChairs={showChairs}
      />
    );
  }

  if (type === "wall") return <WallRenderer width={width} height={height} />;
  if (type === "column" || type === "pillar") return <ColumnRenderer width={width} height={height} />;
  if (type === "door" || type === "door_single") return <DoorRenderer width={width} height={height} swingDirection={object.properties?.swing_direction || "right"} />;
  if (type === "door_double") return <DoubleDoorRenderer width={width} height={height} />;
  if (type === "window") return <WindowRenderer width={width} height={height} />;
  if (type === "booth" || type === "sofa") return <BoothRenderer width={width} height={height} />;
  if (type === "stool" || type === "chair") return <StoolRenderer size={Math.min(width, height)} />;
  if (type === "counter" || type === "bar") return <CounterRenderer width={width} height={height} />;
  if (type === "service_station" || type === "station") return <ServiceStationRenderer width={width} height={height} />;
  if (type === "plant") return <PlantRenderer diameter={Math.min(width, height)} />;

  return (
    <div className="w-full h-full bg-slate-500/50 rounded flex items-center justify-center text-white text-[10px] border border-slate-400 border-dashed">
      {object.object_type || "unknown"}
    </div>
  );
};
