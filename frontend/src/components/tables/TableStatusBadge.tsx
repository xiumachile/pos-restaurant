import type { TableStatus } from "@/types/tables";
import { TABLE_STATUS_LABELS, TABLE_STATUS_STYLES } from "@/types/tables";

interface TableStatusBadgeProps {
  status: TableStatus;
}

export function TableStatusBadge({ status }: TableStatusBadgeProps) {
  const style = TABLE_STATUS_STYLES[status];
  const label = TABLE_STATUS_LABELS[status];

  return (
    <span
      className={`inline-flex items-center gap-1 px-2 py-1 rounded-full text-xs font-medium ${style.bg} ${style.text} border ${style.border}`}
    >
      <span>{style.icon}</span>
      <span>{label}</span>
    </span>
  );
}
