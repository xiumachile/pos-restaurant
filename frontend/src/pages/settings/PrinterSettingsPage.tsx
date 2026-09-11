import { useState } from "react";
import {
  Printer,
  Plus,
  Trash2,
  Star,
  StarOff,
  Wifi,
  WifiOff,
  RefreshCw,
  ArrowLeft,
  Check,
  X,
} from "lucide-react";
import {
  usePrinterConfigs,
  useCreatePrinter,
  useUpdatePrinter,
  useDeletePrinter,
} from "@/hooks/usePrinterConfig";
import type {
  PrinterConfig,
  PrinterType,
  CreatePrinterConfigPayload,
} from "@/db/repositories/PrinterConfigRepository";
import { TauriNetworkPrinterAdapter } from "@/services/printing/adapters/TauriNetworkPrinterAdapter";
import { useToastStore } from "@/store/useToastStore";

const PRINTER_TYPES: { key: PrinterType; label: string; emoji: string }[] = [
  { key: "receipt", label: "Boletas / Tickets", emoji: "🧾" },
  { key: "kitchen", label: "Cocina", emoji: "🍳" },
  { key: "bar", label: "Bar", emoji: "🍺" },
];

function isValidIp(ip: string): boolean {
  const parts = ip.split(".");
  if (parts.length !== 4) return false;
  return parts.every((p) => {
    const n = parseInt(p, 10);
    return !isNaN(n) && n >= 0 && n <= 255 && String(n) === p;
  });
}

export function PrinterSettingsPage() {
  const { data: printers = [], isLoading } = usePrinterConfigs();
  const createPrinter = useCreatePrinter();
  const updatePrinter = useUpdatePrinter();
  const deletePrinter = useDeletePrinter();
  const addToast = useToastStore((s) => s.addToast);

  const [showForm, setShowForm] = useState(false);
  const [editingPrinter, setEditingPrinter] = useState<PrinterConfig | null>(null);
  const [formData, setFormData] = useState({
    printer_type: "receipt" as PrinterType,
    name: "",
    ip: "",
    port: "9100",
    is_default: true,
  });
  const [testing, setTesting] = useState<string | null>(null);
  const [testResults, setTestResults] = useState<Record<string, boolean | null>>({});

  const resetForm = () => {
    setFormData({ printer_type: "receipt", name: "", ip: "", port: "9100", is_default: true });
    setEditingPrinter(null);
    setShowForm(false);
  };

  const openAddForm = (type: PrinterType) => {
    setFormData({ printer_type: type, name: "", ip: "", port: "9100", is_default: true });
    setEditingPrinter(null);
    setShowForm(true);
  };

  const openEditForm = (printer: PrinterConfig) => {
    setFormData({
      printer_type: printer.printer_type,
      name: printer.name,
      ip: printer.ip,
      port: String(printer.port),
      is_default: printer.is_default,
    });
    setEditingPrinter(printer);
    setShowForm(true);
  };

  const handleSave = async () => {
    if (!formData.name.trim() || !formData.ip.trim()) {
      addToast("error", "Nombre e IP son obligatorios");
      return;
    }
    if (!isValidIp(formData.ip)) {
      addToast("error", "IP inválida. Formato: 192.168.1.100");
      return;
    }
    const port = parseInt(formData.port, 10);
    if (isNaN(port) || port < 1 || port > 65535) {
      addToast("error", "Puerto debe ser entre 1 y 65535");
      return;
    }

    try {
      if (editingPrinter) {
        await updatePrinter.mutateAsync({
          localUuid: editingPrinter.local_uuid,
          payload: {
            name: formData.name,
            ip: formData.ip,
            port,
            is_default: formData.is_default,
          },
        });
        addToast("success", `Impresora "${formData.name}" actualizada`);
      } else {
        await createPrinter.mutateAsync({
          printer_type: formData.printer_type,
          name: formData.name,
          ip: formData.ip,
          port,
          is_default: formData.is_default,
        });
        addToast("success", `Impresora "${formData.name}" agregada`);
      }
      resetForm();
    } catch (err: any) {
      addToast("error", err?.message || "Error al guardar");
    }
  };

  const handleDelete = async (printer: PrinterConfig) => {
    if (!confirm(`¿Eliminar impresora "${printer.name}"?`)) return;
    try {
      await deletePrinter.mutateAsync(printer.local_uuid);
      addToast("success", `Impresora "${printer.name}" eliminada`);
    } catch (err: any) {
      addToast("error", err?.message || "Error al eliminar");
    }
  };

  const handleSetDefault = async (printer: PrinterConfig) => {
    try {
      await updatePrinter.mutateAsync({
        localUuid: printer.local_uuid,
        payload: { is_default: true },
      });
      addToast("success", `"${printer.name}" es ahora la predeterminada`);
    } catch (err: any) {
      addToast("error", err?.message || "Error");
    }
  };

  const handleTestConnection = async (printer: PrinterConfig) => {
    setTesting(printer.local_uuid);
    setTestResults((prev) => ({ ...prev, [printer.local_uuid]: null }));

    try {
      const adapter = new TauriNetworkPrinterAdapter();
      const reachable = await adapter.ping(printer.ip, printer.port);
      setTestResults((prev) => ({ ...prev, [printer.local_uuid]: reachable }));
      if (reachable) {
        addToast("success", `✅ ${printer.name} responde en ${printer.ip}:${printer.port}`);
      } else {
        addToast("error", `❌ ${printer.name} no responde en ${printer.ip}:${printer.port}`);
      }
    } catch {
      setTestResults((prev) => ({ ...prev, [printer.local_uuid]: false }));
      addToast("error", `❌ Error conectando a ${printer.ip}:${printer.port}`);
    } finally {
      setTesting(null);
    }
  };

  const printersByType = (type: PrinterType) =>
    printers.filter((p) => p.printer_type === type);

  if (isLoading) {
    return (
      <div className="flex items-center justify-center h-64">
        <div className="animate-spin rounded-full h-12 w-12 border-b-2 border-orange-500" />
      </div>
    );
  }

  return (
    <div>
      <div className="flex items-center gap-4 mb-6">
        <a href="/settings" className="text-slate-400 hover:text-white transition-colors">
          <ArrowLeft size={24} />
        </a>
        <div>
          <h1 className="text-3xl font-bold flex items-center gap-2">
            <Printer size={28} /> Impresoras
          </h1>
          <p className="text-sm text-slate-400 mt-1">
            Configura las impresoras térmicas de tu local
          </p>
        </div>
      </div>

      {/* Formulario de agregar/editar */}
      {showForm && (
        <div className="bg-slate-800 border border-orange-500/50 rounded-lg p-6 mb-6">
          <h3 className="text-lg font-bold mb-4">
            {editingPrinter ? "Editar impresora" : "Agregar impresora"}
          </h3>
          <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
            <div>
              <label className="block text-sm text-slate-400 mb-1">Nombre</label>
              <input
                type="text"
                value={formData.name}
                onChange={(e) => setFormData((f) => ({ ...f, name: e.target.value }))}
                placeholder="Ej: Caja Principal"
                className="w-full bg-slate-700 border border-slate-600 rounded px-3 py-2 text-white placeholder-slate-500 focus:border-orange-500 focus:outline-none"
              />
            </div>
            <div>
              <label className="block text-sm text-slate-400 mb-1">Tipo</label>
              <select
                value={formData.printer_type}
                onChange={(e) => setFormData((f) => ({ ...f, printer_type: e.target.value as PrinterType }))}
                disabled={!!editingPrinter}
                className="w-full bg-slate-700 border border-slate-600 rounded px-3 py-2 text-white focus:border-orange-500 focus:outline-none disabled:opacity-50"
              >
                {PRINTER_TYPES.map((t) => (
                  <option key={t.key} value={t.key}>
                    {t.emoji} {t.label}
                  </option>
                ))}
              </select>
            </div>
            <div>
              <label className="block text-sm text-slate-400 mb-1">Dirección IP</label>
              <input
                type="text"
                value={formData.ip}
                onChange={(e) => setFormData((f) => ({ ...f, ip: e.target.value }))}
                placeholder="192.168.1.100"
                className="w-full bg-slate-700 border border-slate-600 rounded px-3 py-2 text-white placeholder-slate-500 focus:border-orange-500 focus:outline-none"
              />
            </div>
            <div>
              <label className="block text-sm text-slate-400 mb-1">Puerto</label>
              <input
                type="number"
                value={formData.port}
                onChange={(e) => setFormData((f) => ({ ...f, port: e.target.value }))}
                placeholder="9100"
                className="w-full bg-slate-700 border border-slate-600 rounded px-3 py-2 text-white placeholder-slate-500 focus:border-orange-500 focus:outline-none"
              />
            </div>
          </div>
          <div className="flex items-center gap-2 mt-4">
            <input
              type="checkbox"
              id="is_default"
              checked={formData.is_default}
              onChange={(e) => setFormData((f) => ({ ...f, is_default: e.target.checked }))}
              className="rounded border-slate-600"
            />
            <label htmlFor="is_default" className="text-sm text-slate-400">
              Usar como predeterminada para este tipo
            </label>
          </div>
          <div className="flex gap-2 mt-4">
            <button
              onClick={handleSave}
              disabled={createPrinter.isPending || updatePrinter.isPending}
              className="bg-orange-600 hover:bg-orange-700 text-white px-4 py-2 rounded font-medium disabled:opacity-50"
            >
              {editingPrinter ? "Guardar cambios" : "Agregar impresora"}
            </button>
            <button
              onClick={resetForm}
              className="bg-slate-600 hover:bg-slate-500 text-white px-4 py-2 rounded"
            >
              Cancelar
            </button>
          </div>
        </div>
      )}

      {/* Secciones por tipo */}
      {PRINTER_TYPES.map((type) => {
        const typePrinters = printersByType(type.key);
        return (
          <div key={type.key} className="mb-6">
            <div className="flex items-center justify-between mb-3">
              <h2 className="text-xl font-bold flex items-center gap-2">
                <span>{type.emoji}</span> {type.label}
                <span className="text-sm font-normal text-slate-500">({typePrinters.length})</span>
              </h2>
              {!showForm && (
                <button
                  onClick={() => openAddForm(type.key)}
                  className="flex items-center gap-1 text-sm bg-slate-700 hover:bg-slate-600 text-orange-400 px-3 py-1.5 rounded transition-colors"
                >
                  <Plus size={16} /> Agregar
                </button>
              )}
            </div>

            {typePrinters.length === 0 ? (
              <div className="bg-slate-800 rounded-lg p-8 text-center border border-slate-700">
                <Printer className="mx-auto mb-2 text-slate-600" size={32} />
                <p className="text-slate-500 text-sm">Sin impresoras configuradas</p>
              </div>
            ) : (
              <div className="space-y-2">
                {typePrinters.map((printer) => {
                  const testResult = testResults[printer.local_uuid];
                  return (
                    <div
                      key={printer.local_uuid}
                      className={`bg-slate-800 rounded-lg p-4 border flex items-center justify-between ${
                        printer.is_default ? "border-orange-500/50" : "border-slate-700"
                      }`}
                    >
                      <div className="flex items-center gap-3">
                        <div className="text-2xl">
                          {testResult === true ? (
                            <Wifi className="text-green-400" size={24} />
                          ) : testResult === false ? (
                            <WifiOff className="text-red-400" size={24} />
                          ) : (
                            <Printer className="text-slate-400" size={24} />
                          )}
                        </div>
                        <div>
                          <div className="flex items-center gap-2">
                            <span className="font-bold">{printer.name}</span>
                            {printer.is_default && (
                              <span className="text-xs bg-orange-600/20 text-orange-400 px-2 py-0.5 rounded-full">
                                Predeterminada
                              </span>
                            )}
                          </div>
                          <p className="text-sm text-slate-400">
                            {printer.ip}:{printer.port}
                          </p>
                        </div>
                      </div>

                      <div className="flex items-center gap-1">
                        <button
                          onClick={() => handleTestConnection(printer)}
                          disabled={testing === printer.local_uuid}
                          title="Probar conexión"
                          className="p-2 text-slate-400 hover:text-green-400 hover:bg-slate-700 rounded transition-colors disabled:animate-spin"
                        >
                          <RefreshCw size={16} />
                        </button>
                        {!printer.is_default && (
                          <button
                            onClick={() => handleSetDefault(printer)}
                            title="Hacer predeterminada"
                            className="p-2 text-slate-400 hover:text-orange-400 hover:bg-slate-700 rounded transition-colors"
                          >
                            <Star size={16} />
                          </button>
                        )}
                        <button
                          onClick={() => openEditForm(printer)}
                          title="Editar"
                          className="p-2 text-slate-400 hover:text-blue-400 hover:bg-slate-700 rounded transition-colors text-sm"
                        >
                          ✏️
                        </button>
                        <button
                          onClick={() => handleDelete(printer)}
                          title="Eliminar"
                          className="p-2 text-slate-400 hover:text-red-400 hover:bg-slate-700 rounded transition-colors"
                        >
                          <Trash2 size={16} />
                        </button>
                      </div>
                    </div>
                  );
                })}
              </div>
            )}
          </div>
        );
      })}

      {/* Nota informativa */}
      <div className="bg-slate-800/50 rounded-lg p-4 border border-slate-700 mt-8">
        <h3 className="font-bold text-sm text-slate-300 mb-2">💡 ¿Cómo funciona?</h3>
        <ul className="text-sm text-slate-500 space-y-1">
          <li>• Las impresoras se conectan por red (TCP puerto 9100)</li>
          <li>• Compatible con Epson, Star, Bixolon, Xprinter y similares</li>
          <li>• La impresora <strong>predeterminada</strong> de cada tipo se usa automáticamente</li>
          <li>• Si no hay impresora configurada, los tickets se guardan para impresión posterior</li>
        </ul>
      </div>
    </div>
  );
}
