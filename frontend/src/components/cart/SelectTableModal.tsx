import { useTables } from "@/hooks/useTables";
import { X } from "lucide-react";
import { flattenAreas, TABLE_STATUS_STYLES } from "@/types/tables";

interface SelectTableModalProps {
  isOpen: boolean;
  onClose: () => void;
  onSelect: (tableId: string, tableNumber: string) => void;
}

export function SelectTableModal({ isOpen, onClose, onSelect }: SelectTableModalProps) {
  const { data: areas = [] } = useTables();
  const allTables = flattenAreas(areas);
  const availableTables = allTables.filter((t) => t.status === "available");

  if (!isOpen) return null;

  return (
    <div
      className="fixed inset-0 bg-black/70 z-50 flex items-center justify-center p-4"
      onClick={onClose}
    >
      <div
        className="bg-slate-900 rounded-xl shadow-2xl max-w-2xl w-full max-h-[80vh] overflow-hidden flex flex-col"
        onClick={(e) => e.stopPropagation()}
      >
        <div className="flex items-center justify-between p-4 border-b border-slate-700">
          <h2 className="text-xl font-bold">Seleccionar Mesa</h2>
          <button onClick={onClose} className="p-2 hover:bg-slate-800 rounded-lg">
            <X size={20} />
          </button>
        </div>

        <div className="flex-1 overflow-y-auto p-4">
          {availableTables.length === 0 ? (
            <div className="text-center py-12 text-slate-500">
              <p>No hay mesas disponibles</p>
            </div>
          ) : (
            <div className="grid grid-cols-3 md:grid-cols-4 gap-3">
              {availableTables.map((table) => {
                const style = TABLE_STATUS_STYLES[table.status];
                return (
                  <button
                    key={table.uuid}
                    onClick={() => {
                      onSelect(table.uuid, table.table_number);
                      onClose();
                    }}
                    className={`p-4 rounded-xl border-2 transition-all hover:scale-105 ${style.bg} ${style.border}`}
                  >
                    <div className="text-2xl font-bold text-white mb-1">
                      {table.table_number}
                    </div>
                    <div className="text-xs text-slate-300">{table.capacity} personas</div>
                    <div className="text-xs text-slate-400 mt-1">{table.area_name}</div>
                  </button>
                );
              })}
            </div>
          )}
        </div>
      </div>
    </div>
  );
}
