import type { TablesArea } from "@/types/tables";
import { flattenAreas } from "@/types/tables";
import { CheckCircle, Users, DollarSign, Wrench } from "lucide-react";

interface TablesStatsProps {
  areas: TablesArea[];
}

export function TablesStats({ areas }: TablesStatsProps) {
  const tables = flattenAreas(areas);

  const stats = {
    available: tables.filter((t) => t.status === "available").length,
    occupied: tables.filter((t) => t.status === "occupied").length,
    billing: tables.filter((t) => t.status === "billing").length,
    maintenance: tables.filter((t) => t.status === "maintenance").length,
  };

  const items = [
    { label: "Disponibles", value: stats.available, icon: CheckCircle, color: "text-green-400" },
    { label: "Ocupadas", value: stats.occupied, icon: Users, color: "text-red-400" },
    { label: "Por cobrar", value: stats.billing, icon: DollarSign, color: "text-yellow-400" },
    { label: "Mantenimiento", value: stats.maintenance, icon: Wrench, color: "text-slate-400" },
  ];

  return (
    <div className="grid grid-cols-2 md:grid-cols-4 gap-4 mb-6">
      {items.map((item) => {
        const Icon = item.icon;
        return (
          <div
            key={item.label}
            className="bg-slate-800/50 border border-slate-700 rounded-lg p-4"
          >
            <div className="flex items-center justify-between">
              <div>
                <p className="text-sm text-slate-400">{item.label}</p>
                <p className="text-2xl font-bold text-white mt-1">{item.value}</p>
              </div>
              <Icon size={32} className={item.color} />
            </div>
          </div>
        );
      })}
    </div>
  );
}
