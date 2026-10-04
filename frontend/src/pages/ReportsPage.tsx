import { useTranslation } from "react-i18next";
import {
  BarChart3,
  TrendingUp,
  DollarSign,
  ShoppingBag,
  Clock,
  CreditCard,
  Loader2,
} from "lucide-react";
import {
  useDashboardKPIs,
  useTopProducts,
  useSalesByHour,
  usePaymentMethods,
} from "@/hooks/useReports";
import {
  BarChart,
  Bar,
  XAxis,
  YAxis,
  CartesianGrid,
  Tooltip,
  ResponsiveContainer,
  PieChart,
  Pie,
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
  format?: "currency" | "number";
}) {
  const formattedValue =
    format === "currency"
      ? value.toLocaleString("es-CL", {
          style: "currency",
          currency: "CLP",
          minimumFractionDigits: 0,
        })
      : value.toLocaleString("es-CL");

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

export function ReportsPage() {
  const { t } = useTranslation();

  const { data: kpis, isLoading: loadingKPIs } = useDashboardKPIs();
  const { data: topProducts, isLoading: loadingProducts } = useTopProducts(7, 10);
  const { data: salesByHour, isLoading: loadingSales } = useSalesByHour(7);
  const { data: paymentMethods, isLoading: loadingPayments } = usePaymentMethods(30);

  if (loadingKPIs || loadingProducts || loadingSales || loadingPayments) {
    return (
      <div className="flex items-center justify-center h-64">
        <Loader2 className="animate-spin text-orange-500" size={48} />
      </div>
    );
  }

  const CHART_COLORS = ["#f97316", "#3b82f6", "#10b981", "#8b5cf6", "#ec4899"];

  return (
    <div className="max-w-7xl mx-auto p-6 space-y-6">
      {/* Header */}
      <div className="flex items-center gap-3">
        <BarChart3 size={32} className="text-orange-400" />
        <div>
          <h1 className="text-3xl font-bold text-white">{t("reports.title")}</h1>
          <p className="text-slate-400">{t("reports.subtitle")}</p>
        </div>
      </div>

      {/* KPIs */}
      <div className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4">
        <KPICard
          title={t("reports.kpis.today_sales")}
          value={kpis?.today_sales ?? 0}
          icon={DollarSign}
          color="text-emerald-400"
          format="currency"
        />
        <KPICard
          title={t("reports.kpis.today_orders")}
          value={kpis?.today_orders_count ?? 0}
          icon={ShoppingBag}
          color="text-blue-400"
          format="number"
        />
        <KPICard
          title={t("reports.kpis.average_ticket")}
          value={kpis?.average_ticket ?? 0}
          icon={TrendingUp}
          color="text-purple-400"
          format="currency"
        />
        <KPICard
          title={t("reports.kpis.today_tips")}
          value={kpis?.today_tips ?? 0}
          icon={CreditCard}
          color="text-pink-400"
          format="currency"
        />
      </div>

      {/* Gráficos */}
      <div className="grid grid-cols-1 lg:grid-cols-2 gap-6">
        {/* Ventas por hora */}
        <div className="bg-slate-800/30 border border-slate-700 rounded-lg p-6">
          <div className="flex items-center gap-2 mb-4">
            <Clock size={20} className="text-orange-400" />
            <h2 className="text-xl font-bold text-white">{t("reports.sales_by_hour")}</h2>
          </div>
          <ResponsiveContainer width="100%" height={300}>
            <BarChart data={salesByHour}>
              <CartesianGrid strokeDasharray="3 3" stroke="#334155" />
              <XAxis
                dataKey="hour"
                stroke="#94a3b8"
                tick={{ fill: "#94a3b8", fontSize: 12 }}
                tickFormatter={(h) => `${h}:00`}
              />
              <YAxis stroke="#94a3b8" tick={{ fill: "#94a3b8", fontSize: 12 }} />
              <Tooltip
                contentStyle={{
                  backgroundColor: "#1e293b",
                  border: "1px solid #475569",
                  borderRadius: "8px",
                }}
                labelStyle={{ color: "#fff" }}
                formatter={(value: any) => [
                  Number(value).toLocaleString("es-CL", {
                    style: "currency",
                    currency: "CLP",
                  }),
                  t("reports.total"),
                ]}
                labelFormatter={(label) => `${label}:00`}
              />
              <Bar dataKey="total" fill="#f97316" radius={[4, 4, 0, 0]} />
            </BarChart>
          </ResponsiveContainer>
        </div>

        {/* Top productos */}
        <div className="bg-slate-800/30 border border-slate-700 rounded-lg p-6">
          <div className="flex items-center gap-2 mb-4">
            <TrendingUp size={20} className="text-orange-400" />
            <h2 className="text-xl font-bold text-white">{t("reports.top_products")}</h2>
          </div>
          <div className="space-y-3 max-h-[300px] overflow-y-auto">
            {topProducts?.map((product, idx) => (
              <div
                key={idx}
                className="flex items-center justify-between p-3 bg-slate-800/50 rounded-lg"
              >
                <div className="flex items-center gap-3">
                  <span className="text-2xl font-bold text-slate-600">#{idx + 1}</span>
                  <div>
                    <p className="font-medium text-white">{product.name}</p>
                    <p className="text-sm text-slate-400">
                      {product.quantity} {t("reports.units")}
                    </p>
                  </div>
                </div>
                <p className="font-bold text-emerald-400">
                  {product.revenue.toLocaleString("es-CL", {
                    style: "currency",
                    currency: "CLP",
                  })}
                </p>
              </div>
            ))}
          </div>
        </div>

        {/* Métodos de pago */}
        <div className="bg-slate-800/30 border border-slate-700 rounded-lg p-6 lg:col-span-2">
          <div className="flex items-center gap-2 mb-4">
            <CreditCard size={20} className="text-orange-400" />
            <h2 className="text-xl font-bold text-white">{t("reports.payment_methods")}</h2>
          </div>
          <div className="grid grid-cols-1 lg:grid-cols-2 gap-6">
            <ResponsiveContainer width="100%" height={300}>
              <PieChart>
                <Pie
                  data={paymentMethods}
                  dataKey="total_amount"
                  nameKey="method_code"
                  cx="50%"
                  cy="50%"
                  outerRadius={100}
                  label={(entry: any) => entry.method_code}
                >
                  {paymentMethods?.map((entry, index) => (
                    <Cell
                      key={`cell-${index}`}
                      fill={CHART_COLORS[index % CHART_COLORS.length]}
                    />
                  ))}
                </Pie>
                <Tooltip
                  contentStyle={{
                    backgroundColor: "#1e293b",
                    border: "1px solid #475569",
                    borderRadius: "8px",
                  }}
                  formatter={(value: any) => [
                    Number(value).toLocaleString("es-CL", {
                      style: "currency",
                      currency: "CLP",
                    }),
                    t("reports.total"),
                  ]}
                />
                <Legend />
              </PieChart>
            </ResponsiveContainer>
            <div className="space-y-3">
              {paymentMethods?.map((method, idx) => (
                <div
                  key={idx}
                  className="flex items-center justify-between p-3 bg-slate-800/50 rounded-lg"
                >
                  <div className="flex items-center gap-3">
                    <div
                      className="w-4 h-4 rounded"
                      style={{ backgroundColor: CHART_COLORS[idx % CHART_COLORS.length] }}
                    />
                    <div>
                      <p className="font-medium text-white">{method.method_code}</p>
                      <p className="text-sm text-slate-400">
                        {method.count} {t("reports.payments")}
                      </p>
                    </div>
                  </div>
                  <p className="font-bold text-emerald-400">
                    {method.total_amount.toLocaleString("es-CL", {
                      style: "currency",
                      currency: "CLP",
                    })}
                  </p>
                </div>
              ))}
            </div>
          </div>
        </div>
      </div>
    </div>
  );
}
