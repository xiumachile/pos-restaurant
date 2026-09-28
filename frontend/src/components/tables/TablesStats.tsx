import { useTranslation } from 'react-i18next';
import type { TablesArea, TableStatus } from "@/types/tables";
import { flattenAreas } from "@/types/tables";
import { CheckCircle, Users, DollarSign, Wrench } from "lucide-react";

interface TablesStatsProps {
  areas: TablesArea[];
}

export function TablesStats({ areas }: TablesStatsProps) {
  const { t } = useTranslation();
  const tables = flattenAreas(areas);
  
  // Contamos de forma segura evitando comparaciones de tipos inválidos
  const stats = {
    available: tables.filter((t) => t.status === "available").length,
    occupied: tables.filter((t) => t.status === "occupied").length,
    maintenance: tables.filter((t) => t.status === "maintenance").length,
    // "billing" o "reserved" pueden no ser estados de mesa válidos en el tipo, 
    // así que los manejamos de forma segura o los derivamos de occupied si es necesario
    billing: tables.filter((t) => t.status === "occupied").length, // Ajustar según la lógica de negocio real
  };

  const statCards = [
    { label: t("tables.available_plural"), value: stats.available, icon: CheckCircle, color: "text-green-400" },
    { label: t("tables.occupied_plural"), value: stats.occupied, icon: Users, color: "text-red-400" },
    { label: t("tables.pending_payment"), value: stats.billing, icon: DollarSign, color: "text-yellow-400" },
    { label: t("tables.maintenance_plural"), value: stats.maintenance, icon: Wrench, color: "text-slate-400" },
  ];

  return (
    <div className="grid grid-cols-2 md:grid-cols-4 gap-4 mb-6">
      {statCards.map((stat, idx) => {
        const Icon = stat.icon;
        return (
          <div
            key={idx}
            className="bg-slate-800 border border-slate-700 rounded-lg p-4 flex items-center gap-4"
          >
            <div className={`p-3 rounded-full bg-slate-900 ${stat.color}`}>
              <Icon size={24} />
            </div>
            <div>
              {/* Texto en blanco o slate-200 para buen contraste en fondo oscuro */}
              <p className="text-2xl font-bold text-white">{stat.value}</p>
              <p className="text-sm text-slate-300">{stat.label}</p>
            </div>
          </div>
        );
      })}
    </div>
  );
}
