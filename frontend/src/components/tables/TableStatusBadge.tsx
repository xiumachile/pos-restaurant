import { useTranslation } from 'react-i18next';
import type { TableStatus } from "@/types/tables";
import { TABLE_STATUS_STYLES } from "@/types/tables";

interface TableStatusBadgeProps {
  status: TableStatus;
}

export function TableStatusBadge({ status }: TableStatusBadgeProps) {
  const { t } = useTranslation();
  
  const style = TABLE_STATUS_STYLES[status] || {
    bg: "bg-slate-700",
    text: "text-slate-300"
  };

  return (
    <span className={`inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium ${style.bg} ${style.text}`}>
      {t(`tables.${status}`)}
    </span>
  );
}
