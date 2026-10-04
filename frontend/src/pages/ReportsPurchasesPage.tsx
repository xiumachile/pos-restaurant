import { useTranslation } from "react-i18next";
import {
  TrendingUp,
  DollarSign,
  ShoppingCart,
  FileText,
  Truck,
  Package,
  Loader2,
} from "lucide-react";
import {
  usePurchaseKPIs,
  usePurchasesByDocumentType,
  useTopSuppliers,
  useTopPurchasedIngredients,
} from "@/hooks/usePurchaseReports";
import type { DateFilter } from "@/services/reportsService";
import { PrintButton } from "@/components/ui/PrintButton";
import {
  BarChart,
  Bar,
  XAxis,
  YAxis,
  CartesianGrid,
  Tooltip,
  ResponsiveContainer,
  Cell,
  Legend,
} from "recharts";

function KPICard({
  title,
  value,
  icon: Icon,
  color,
  format = "currency",
}: {
  title: string;
  value: number;
  icon: React.ComponentType<{ size?: number; className?: string }>;
  color: string;
  format?: "currency" | "number" | "percentage";
}) {
  let formattedValue: string;

  if (format === "currency") {
    formattedValue = value.toLocaleString("es-CL", {
      style: "currency",
      currency: "CLP",
      minimumFractionDigits: 0,
    });
  } else if (format === "percentage") {
    formattedValue = `${value.toFixed(1)}%`;
  } else {
    formattedValue = value.toLocaleString("es-CL");
  }

  return (
    <div className="bg-slate-800/50 border border-slate-700 rounded-lg p-6">
      <div className="flex items-center justify-between mb-2">
        <span className="text-sm text-slate-400">{title}</span>
        <Icon size={20} className={color} />
      </div>
      <p className={`text-3xl font-bold ${color}`}>{formattedValue}</p>
    </div>
  );
}

const DOCUMENT_COLORS: Record<string, string> = {
  boleta: "#3b82f6",
  factura: "#f97316",
  factura_exenta: "#10b981",
  nota_entrada: "#8b5cf6",
  otro: "#ec4899",
};

const CHART_COLORS = ["#f97316", "#3b82f6", "#10b981", "#8b5cf6", "#ec4899", "#f59e0b"];

interface ReportsPurchasesPageProps {
  dateFilter: DateFilter;
  dateRangeLabel?: string;
}

export function ReportsPurchasesPage({ dateFilter, dateRangeLabel }: ReportsPurchasesPageProps) {
  const { t } = useTranslation();

  const { data: kpis, isLoading: loadingKPIs } = usePurchaseKPIs(dateFilter);
  const { data: byDocType, isLoading: loadingDoc } = usePurchasesByDocumentType(dateFilter);
  const { data: topSuppliers, isLoading: loadingSuppliers } = useTopSuppliers(dateFilter, 10);
  const { data: topIngredients, isLoading: loadingIngredients } = useTopPurchasedIngredients(dateFilter, 10);

  const isLoading = loadingKPIs || loadingDoc || loadingSuppliers || loadingIngredients;

  const documentPercentage =
    kpis && kpis.purchases_count > 0
      ? (kpis.with_document_count / kpis.purchases_count) * 100
      : 0;

  if (isLoading) {
    return (
      <div className="flex items-center justify-center py-8">
        <Loader2 className="animate-spin text-orange-500" size={32} />
        <span className="ml-2 text-slate-400">{t("reports.loading")}</span>
      </div>
    );
  }

  return (
    <div className="space-y-6">
      {/* Header solo visible al imprimir */}
      <div data-print-header="true" className="print-only" />

      {/* Botón de impresión */}
      <div className="flex justify-end no-print" data-print-hide="true">
        <PrintButton
          title={t("reports.title")}
          subtitle={t("reports.tabs.purchases")}
          dateRangeLabel={dateRangeLabel}
        />
      </div>

      {/* KPIs */}
      <div className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4">
        <KPICard
          title={t("reports.purchases.kpis.total_amount")}
          value={kpis?.total_amount ?? 0}
          icon={DollarSign}
          color="text-emerald-400"
          format="currency"
        />
        <KPICard
          title={t("reports.purchases.kpis.purchases_count")}
          value={kpis?.purchases_count ?? 0}
          icon={ShoppingCart}
          color="text-blue-400"
          format="number"
        />
        <KPICard
          title={t("reports.purchases.kpis.average_ticket")}
          value={kpis?.average_ticket ?? 0}
          icon={TrendingUp}
          color="text-purple-400"
          format="currency"
        />
        <KPICard
          title={t("reports.purchases.kpis.with_document")}
          value={documentPercentage}
          icon={FileText}
          color="text-pink-400"
          format="percentage"
        />
      </div>

      {/* Gráficos */}
      <div className="grid grid-cols-1 lg:grid-cols-2 gap-6">
        {/* Distribución por tipo de documento */}
        <div className="bg-slate-800/30 border border-slate-700 rounded-lg p-6">
          <div className="flex items-center gap-2 mb-4">
            <FileText size={20} className="text-orange-400" />
            <h2 className="text-xl font-bold text-white">{t("reports.purchases.by_document_type")}</h2>
          </div>
          {byDocType && byDocType.length > 0 ? (
            <ResponsiveContainer width="100%" height={300}>
              <BarChart data={byDocType}>
                <CartesianGrid strokeDasharray="3 3" stroke="#334155" />
                <XAxis
                  dataKey="document_type"
                  stroke="#94a3b8"
                  tick={{ fill: "#94a3b8", fontSize: 11 }}
                  tickFormatter={(v) => String(t(`reports.purchases.doc_types.${String(v)}`, { defaultValue: String(v) }))}
                />
                <YAxis stroke="#94a3b8" tick={{ fill: "#94a3b8", fontSize: 12 }} />
                <Tooltip
                  contentStyle={{
                    backgroundColor: "#1e293b",
                    border: "1px solid #475569",
                    borderRadius: "8px",
                  }}
                  labelStyle={{ color: "#fff" }}
                  labelFormatter={(label) =>
                    String(t(`reports.purchases.doc_types.${String(label)}`, { defaultValue: String(label) }))
                  }
                  formatter={(value: any, name: any) => {
                    if (name === "total_amount") {
                      return [
                        Number(value).toLocaleString("es-CL", {
                          style: "currency",
                          currency: "CLP",
                        }),
                        t("reports.total"),
                      ];
                    }
                    return [value, t("reports.purchases.count")];
                  }}
                />
                <Legend />
                <Bar dataKey="total_amount" name={t("reports.total")} radius={[4, 4, 0, 0]}>
                  {byDocType.map((entry, index) => (
                    <Cell
                      key={`cell-${index}`}
                      fill={DOCUMENT_COLORS[entry.document_type] || CHART_COLORS[index % CHART_COLORS.length]}
                    />
                  ))}
                </Bar>
              </BarChart>
            </ResponsiveContainer>
          ) : (
            <div className="flex items-center justify-center h-[300px] text-slate-400 text-sm">
              {t("reports.empty.no_data")}
            </div>
          )}
        </div>

        {/* Top proveedores */}
        <div className="bg-slate-800/30 border border-slate-700 rounded-lg p-6">
          <div className="flex items-center gap-2 mb-4">
            <Truck size={20} className="text-orange-400" />
            <h2 className="text-xl font-bold text-white">{t("reports.purchases.top_suppliers")}</h2>
          </div>
          <div className="space-y-3 max-h-[300px] overflow-y-auto">
            {topSuppliers && topSuppliers.length > 0 ? (
              topSuppliers.map((supplier, idx) => (
                <div
                  key={idx}
                  className="flex items-center justify-between p-3 bg-slate-800/50 rounded-lg"
                >
                  <div className="flex items-center gap-3 min-w-0 flex-1">
                    <span className="text-2xl font-bold text-slate-600">#{idx + 1}</span>
                    <div className="min-w-0 flex-1">
                      <p className="font-medium text-white truncate">{supplier.supplier_name}</p>
                      <p className="text-sm text-slate-400 font-mono">
                        {supplier.supplier_rut ?? t("reports.purchases.no_rut")} · {supplier.purchases_count} {t("reports.purchases.purchases_label")}
                      </p>
                    </div>
                  </div>
                  <p className="font-bold text-emerald-400 ml-2">
                    {supplier.total_amount.toLocaleString("es-CL", {
                      style: "currency",
                      currency: "CLP",
                    })}
                  </p>
                </div>
              ))
            ) : (
              <div className="text-center py-8 text-slate-400 text-sm">
                {t("reports.empty.no_data")}
              </div>
            )}
          </div>
        </div>

        {/* Top insumos comprados */}
        <div className="bg-slate-800/30 border border-slate-700 rounded-lg p-6 lg:col-span-2">
          <div className="flex items-center gap-2 mb-4">
            <Package size={20} className="text-orange-400" />
            <h2 className="text-xl font-bold text-white">{t("reports.purchases.top_ingredients")}</h2>
          </div>
          {topIngredients && topIngredients.length > 0 ? (
            <div className="overflow-x-auto">
              <table className="w-full">
                <thead className="bg-slate-800/50 border-b border-slate-700">
                  <tr className="text-xs text-slate-400 uppercase tracking-wider">
                    <th className="py-2 px-4 text-left">#</th>
                    <th className="py-2 px-4 text-left">{t("reports.purchases.ingredient")}</th>
                    <th className="py-2 px-4 text-left">SKU</th>
                    <th className="py-2 px-4 text-right">{t("reports.purchases.purchases_label")}</th>
                    <th className="py-2 px-4 text-right">{t("reports.purchases.total_quantity")}</th>
                    <th className="py-2 px-4 text-right">{t("reports.total")}</th>
                  </tr>
                </thead>
                <tbody>
                  {topIngredients.map((ing, idx) => (
                    <tr
                      key={idx}
                      className="border-b border-slate-800 hover:bg-slate-800/30 transition-colors"
                    >
                      <td className="py-3 px-4 text-slate-500 font-bold">#{idx + 1}</td>
                      <td className="py-3 px-4">
                        <p className="font-medium text-white">{ing.ingredient_name}</p>
                      </td>
                      <td className="py-3 px-4 text-sm text-slate-400 font-mono">
                        {ing.ingredient_sku}
                      </td>
                      <td className="py-3 px-4 text-right text-slate-300">
                        {ing.purchases_count}
                      </td>
                      <td className="py-3 px-4 text-right font-mono text-slate-300">
                        {ing.total_quantity.toLocaleString("es-CL", {
                          minimumFractionDigits: 0,
                          maximumFractionDigits: 2,
                        })}{" "}
                        {ing.base_unit}
                      </td>
                      <td className="py-3 px-4 text-right font-bold text-emerald-400">
                        {ing.total_amount.toLocaleString("es-CL", {
                          style: "currency",
                          currency: "CLP",
                        })}
                      </td>
                    </tr>
                  ))}
                </tbody>
              </table>
            </div>
          ) : (
            <div className="text-center py-8 text-slate-400 text-sm">
              {t("reports.empty.no_data")}
            </div>
          )}
        </div>
      </div>

      {/* Footer solo visible al imprimir */}
      <div data-print-footer="true" className="print-only">
        <p style={{ textAlign: "center", margin: 0 }}>
          {t("reports.print.footer")}
        </p>
      </div>
    </div>
  );
}
