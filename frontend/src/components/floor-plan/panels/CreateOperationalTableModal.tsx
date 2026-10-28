import { useState } from "react";
import { X } from "lucide-react";

interface Props {
  isOpen: boolean;
  onClose: () => void;
  onCreate: (data: { table_number: string; capacity: number }) => Promise<void>;
  isSubmitting: boolean;
}

export function CreateOperationalTableModal({ isOpen, onClose, onCreate, isSubmitting }: Props) {
  const [tableNumber, setTableNumber] = useState("");
  const [capacity, setCapacity] = useState(2);

  if (!isOpen) return null;

  const handleSubmit = async (e: React.FormEvent) => {
    e.preventDefault();
    await onCreate({ table_number: tableNumber, capacity });
    setTableNumber("");
    setCapacity(2);
  };

  return (
    <div className="fixed inset-0 bg-black/70 flex items-center justify-center z-50">
      <div className="bg-slate-800 rounded-lg border border-slate-700 p-6 w-96 shadow-2xl">
        <div className="flex items-center justify-between mb-4">
          <h3 className="text-lg font-bold text-white">Crear Mesa Operativa</h3>
          <button onClick={onClose} disabled={isSubmitting} className="p-1 hover:bg-slate-700 rounded transition-colors">
            <X size={20} className="text-slate-400" />
          </button>
        </div>
        
        <form onSubmit={handleSubmit} className="space-y-4">
          <div>
            <label className="text-xs font-semibold text-slate-400 uppercase block mb-1">Número de Mesa</label>
            <input
              type="text"
              required
              value={tableNumber}
              onChange={(e) => setTableNumber(e.target.value)}
              className="w-full px-3 py-2 bg-slate-700 border border-slate-600 rounded text-white focus:ring-1 focus:ring-orange-500 outline-none"
              placeholder="Ej: M-01"
              disabled={isSubmitting}
            />
          </div>
          
          <div>
            <label className="text-xs font-semibold text-slate-400 uppercase block mb-1">Capacidad (pax)</label>
            <input
              type="number"
              required
              min="1"
              max="20"
              value={capacity}
              onChange={(e) => setCapacity(parseInt(e.target.value) || 1)}
              className="w-full px-3 py-2 bg-slate-700 border border-slate-600 rounded text-white focus:ring-1 focus:ring-orange-500 outline-none"
              disabled={isSubmitting}
            />
          </div>

          <div className="flex gap-2 mt-6">
            <button
              type="button"
              onClick={onClose}
              disabled={isSubmitting}
              className="flex-1 px-4 py-2 bg-slate-700 hover:bg-slate-600 text-white rounded transition-colors disabled:opacity-50"
            >
              Cancelar
            </button>
            <button
              type="submit"
              disabled={isSubmitting}
              className="flex-1 px-4 py-2 bg-emerald-600 hover:bg-emerald-700 text-white rounded transition-colors font-medium disabled:opacity-50 flex items-center justify-center gap-2"
            >
              {isSubmitting ? (
                <>
                  <div className="w-4 h-4 border-2 border-white border-t-transparent rounded-full animate-spin" />
                  Creando...
                </>
              ) : (
                "Crear y Asignar"
              )}
            </button>
          </div>
        </form>
      </div>
    </div>
  );
}
