import { useState, useEffect } from "react";
import { useTranslation } from "react-i18next";
import { X, User, Phone, MapPin, FileText, Search, Plus, Loader2 } from "lucide-react";
import { customerService, type LocalCustomer } from "@/services/customerService";

interface CustomerModalProps {
  open: boolean;
  initialPhone?: string;
  onSave: (customer: { name: string; phone: string; address: string; reference: string }) => void;
  onClose: () => void;
}

type Step = "search" | "existing" | "create";

/**
 * Modal de cliente con flujo de 2 pasos para Delivery:
 *   1. Ingresar teléfono → buscar
 *   2a. Si existe: mostrar datos (nombre, dirección, referencia) → confirmar
 *   2b. Si no existe: formulario completo (nombre, teléfono, dirección, referencia) → crear
 */
export function CustomerModal({ open, initialPhone, onSave, onClose }: CustomerModalProps) {
  const { t } = useTranslation();

  const [step, setStep] = useState<Step>("search");
  const [phone, setPhone] = useState(initialPhone || "");
  const [searching, setSearching] = useState(false);
  const [error, setError] = useState("");

  // Datos del cliente existente
  const [existingCustomer, setExistingCustomer] = useState<LocalCustomer | null>(null);
  const [editAddress, setEditAddress] = useState("");
  const [editReference, setEditReference] = useState("");

  // Datos para nuevo cliente
  const [newName, setNewName] = useState("");
  const [newAddress, setNewAddress] = useState("");
  const [newReference, setNewReference] = useState("");

  // Reset al abrir
  useEffect(() => {
    if (open) {
      setStep("search");
      setPhone(initialPhone || "");
      setError("");
      setExistingCustomer(null);
      setNewName("");
      setNewAddress("");
      setNewReference("");
    }
  }, [open, initialPhone]);

  if (!open) return null;

  const handleSearch = async () => {
    const cleanPhone = phone.replace(/\s+/g, "").trim();
    if (!cleanPhone) {
      setError(t("orders.customer_phone_required", "El teléfono es obligatorio"));
      return;
    }
    
    setSearching(true);
    setError("");
    
    try {
      const customer = await customerService.findByPhone(cleanPhone);
      if (customer) {
        setExistingCustomer(customer);
        setEditAddress(customer.delivery_address || "");
        setEditReference(customer.delivery_reference || "");
        setStep("existing");
      } else {
        setStep("create");
      }
    } catch (err: any) {
      setError(err.message || "Error al buscar cliente");
    } finally {
      setSearching(false);
    }
  };

  const handleConfirmExisting = () => {
    if (!existingCustomer) return;
    
    // Actualizar dirección/referencia si cambió
    if (editAddress !== (existingCustomer.delivery_address || "") || 
        editReference !== (existingCustomer.delivery_reference || "")) {
      customerService.update(existingCustomer.uuid, {
        delivery_address: editAddress,
        delivery_reference: editReference,
      }).catch(err => console.error("Error actualizando cliente:", err));
    }
    
    onSave({
      name: existingCustomer.name,
      phone: existingCustomer.phone,
      address: editAddress,
      reference: editReference,
    });
  };

  const handleCreateNew = async () => {
    if (!newName.trim()) {
      setError(t("orders.customer_name_required", "El nombre es obligatorio"));
      return;
    }
    if (!phone.replace(/\s+/g, "").trim()) {
      setError(t("orders.customer_phone_required", "El teléfono es obligatorio"));
      return;
    }

    setSearching(true);
    setError("");

    try {
      await customerService.create({
        phone: phone,
        name: newName,
        delivery_address: newAddress,
        delivery_reference: newReference,
      });
      
      onSave({
        name: newName.trim(),
        phone: phone.replace(/\s+/g, "").trim(),
        address: newAddress.trim(),
        reference: newReference.trim(),
      });
    } catch (err: any) {
      setError(err.message || "Error al crear cliente");
    } finally {
      setSearching(false);
    }
  };

  const handleBackToSearch = () => {
    setStep("search");
    setError("");
    setExistingCustomer(null);
  };

  return (
    <div className="fixed inset-0 z-50 flex items-center justify-center bg-black/70 p-4">
      <div className="bg-slate-900 border border-slate-700 rounded-xl shadow-2xl max-w-lg w-full p-6">
        {/* Header */}
        <div className="flex items-center justify-between mb-6">
          <div>
            <h2 className="text-xl font-bold text-white flex items-center gap-2">
              <User size={20} className="text-orange-500" />
              {step === "search" && t("orders.customer_search_title", "Buscar Cliente")}
              {step === "existing" && t("orders.customer_existing_title", "Cliente Encontrado")}
              {step === "create" && t("orders.customer_create_title", "Nuevo Cliente")}
            </h2>
            <p className="text-sm text-slate-400 mt-1">
              {step === "search" && t("orders.customer_search_hint", "Ingresa el teléfono para buscar o crear un cliente")}
              {step === "existing" && t("orders.customer_existing_hint", "Verifica o actualiza los datos de entrega")}
              {step === "create" && t("orders.customer_create_hint", "Completa los datos del nuevo cliente")}
            </p>
          </div>
          <button
            onClick={onClose}
            className="p-2 hover:bg-slate-800 rounded-lg transition-colors"
            aria-label={t("common.close")}
          >
            <X size={20} className="text-slate-400" />
          </button>
        </div>

        {/* PASO 1: Búsqueda por teléfono */}
        {step === "search" && (
          <div className="space-y-4">
            <div>
              <label className="block text-sm font-medium text-slate-300 mb-1">
                {t("orders.customer_phone", "Teléfono / WhatsApp")} *
              </label>
              <div className="relative">
                <Phone size={18} className="absolute left-3 top-1/2 -translate-y-1/2 text-slate-500" />
                <input
                  type="tel"
                  value={phone}
                  onChange={(e) => setPhone(e.target.value)}
                  onKeyDown={(e) => e.key === "Enter" && handleSearch()}
                  className="w-full bg-slate-800 border border-slate-700 rounded-lg py-2.5 pl-10 pr-4 text-white placeholder-slate-500 focus:outline-none focus:ring-2 focus:ring-orange-500/50 focus:border-orange-500"
                  placeholder="Ej: +56 9 1234 5678"
                  autoFocus
                />
              </div>
            </div>

            {error && (
              <p className="text-sm text-red-400 bg-red-900/20 border border-red-800/50 rounded-lg p-3">
                {error}
              </p>
            )}

            <button
              onClick={handleSearch}
              disabled={searching}
              className="w-full flex items-center justify-center gap-2 px-4 py-2.5 bg-orange-500 hover:bg-orange-600 disabled:bg-slate-700 disabled:cursor-not-allowed text-white rounded-lg font-medium transition-colors"
            >
              {searching ? (
                <>
                  <Loader2 size={18} className="animate-spin" />
                  {t("common.searching", "Buscando...")}
                </>
              ) : (
                <>
                  <Search size={18} />
                  {t("common.search", "Buscar")}
                </>
              )}
            </button>
          </div>
        )}

        {/* PASO 2a: Cliente existente */}
        {step === "existing" && existingCustomer && (
          <div className="space-y-4">
            <div className="bg-slate-800/50 border border-slate-700 rounded-lg p-4 space-y-2">
              <div className="flex items-center gap-2">
                <User size={16} className="text-orange-400" />
                <span className="font-semibold text-white">{existingCustomer.name}</span>
              </div>
              <div className="flex items-center gap-2 text-sm text-slate-400">
                <Phone size={14} />
                <span>{existingCustomer.phone}</span>
              </div>
            </div>

            <div>
              <label className="block text-sm font-medium text-slate-300 mb-1">
                <MapPin size={14} className="inline mr-1" />
                {t("orders.delivery_address", "Dirección de entrega")}
              </label>
              <input
                type="text"
                value={editAddress}
                onChange={(e) => setEditAddress(e.target.value)}
                className="w-full bg-slate-800 border border-slate-700 rounded-lg py-2.5 px-4 text-white placeholder-slate-500 focus:outline-none focus:ring-2 focus:ring-orange-500/50"
                placeholder="Ej: Av. Principal 123, Depto 4B"
              />
            </div>

            <div>
              <label className="block text-sm font-medium text-slate-300 mb-1">
                <FileText size={14} className="inline mr-1" />
                {t("orders.delivery_reference", "Referencia / Indicaciones")}
              </label>
              <textarea
                value={editReference}
                onChange={(e) => setEditReference(e.target.value)}
                rows={2}
                className="w-full bg-slate-800 border border-slate-700 rounded-lg py-2.5 px-4 text-white placeholder-slate-500 focus:outline-none focus:ring-2 focus:ring-orange-500/50 resize-none"
                placeholder="Ej: Timbre no funciona, llamar al llegar"
              />
            </div>

            {error && (
              <p className="text-sm text-red-400 bg-red-900/20 border border-red-800/50 rounded-lg p-3">
                {error}
              </p>
            )}

            <div className="flex gap-3 pt-2">
              <button
                type="button"
                onClick={handleBackToSearch}
                className="flex-1 px-4 py-2.5 bg-slate-800 hover:bg-slate-700 border border-slate-700 rounded-lg text-slate-300 font-medium transition-colors"
              >
                {t("common.back", "Volver")}
              </button>
              <button
                onClick={handleConfirmExisting}
                className="flex-1 px-4 py-2.5 bg-orange-500 hover:bg-orange-600 text-white rounded-lg font-medium transition-colors"
              >
                {t("common.continue", "Continuar")}
              </button>
            </div>
          </div>
        )}

        {/* PASO 2b: Crear nuevo cliente */}
        {step === "create" && (
          <div className="space-y-4">
            <div className="bg-blue-900/20 border border-blue-800/50 rounded-lg p-3 text-sm text-blue-300 flex items-start gap-2">
              <Plus size={16} className="mt-0.5 flex-shrink-0" />
              <span>{t("orders.customer_not_found", "Cliente no encontrado. Completa los datos para registrarlo.")}</span>
            </div>

            <div>
              <label className="block text-sm font-medium text-slate-300 mb-1">
                {t("orders.customer_name", "Nombre del Cliente")} *
              </label>
              <div className="relative">
                <User size={18} className="absolute left-3 top-1/2 -translate-y-1/2 text-slate-500" />
                <input
                  type="text"
                  value={newName}
                  onChange={(e) => setNewName(e.target.value)}
                  className="w-full bg-slate-800 border border-slate-700 rounded-lg py-2.5 pl-10 pr-4 text-white placeholder-slate-500 focus:outline-none focus:ring-2 focus:ring-orange-500/50"
                  placeholder="Ej: Juan Pérez"
                  autoFocus
                />
              </div>
            </div>

            <div>
              <label className="block text-sm font-medium text-slate-300 mb-1">
                <Phone size={14} className="inline mr-1" />
                {t("orders.customer_phone", "Teléfono")} *
              </label>
              <input
                type="tel"
                value={phone}
                onChange={(e) => setPhone(e.target.value)}
                className="w-full bg-slate-800 border border-slate-700 rounded-lg py-2.5 px-4 text-white placeholder-slate-500 focus:outline-none focus:ring-2 focus:ring-orange-500/50"
                placeholder="Ej: +56 9 1234 5678"
              />
            </div>

            <div>
              <label className="block text-sm font-medium text-slate-300 mb-1">
                <MapPin size={14} className="inline mr-1" />
                {t("orders.delivery_address", "Dirección de entrega")}
              </label>
              <input
                type="text"
                value={newAddress}
                onChange={(e) => setNewAddress(e.target.value)}
                className="w-full bg-slate-800 border border-slate-700 rounded-lg py-2.5 px-4 text-white placeholder-slate-500 focus:outline-none focus:ring-2 focus:ring-orange-500/50"
                placeholder="Ej: Av. Principal 123, Depto 4B"
              />
            </div>

            <div>
              <label className="block text-sm font-medium text-slate-300 mb-1">
                <FileText size={14} className="inline mr-1" />
                {t("orders.delivery_reference", "Referencia / Indicaciones")}
              </label>
              <textarea
                value={newReference}
                onChange={(e) => setNewReference(e.target.value)}
                rows={2}
                className="w-full bg-slate-800 border border-slate-700 rounded-lg py-2.5 px-4 text-white placeholder-slate-500 focus:outline-none focus:ring-2 focus:ring-orange-500/50 resize-none"
                placeholder="Ej: Timbre no funciona, llamar al llegar"
              />
            </div>

            {error && (
              <p className="text-sm text-red-400 bg-red-900/20 border border-red-800/50 rounded-lg p-3">
                {error}
              </p>
            )}

            <div className="flex gap-3 pt-2">
              <button
                type="button"
                onClick={handleBackToSearch}
                className="flex-1 px-4 py-2.5 bg-slate-800 hover:bg-slate-700 border border-slate-700 rounded-lg text-slate-300 font-medium transition-colors"
              >
                {t("common.back", "Volver")}
              </button>
              <button
                onClick={handleCreateNew}
                disabled={searching}
                className="flex-1 flex items-center justify-center gap-2 px-4 py-2.5 bg-orange-500 hover:bg-orange-600 disabled:bg-slate-700 text-white rounded-lg font-medium transition-colors"
              >
                {searching ? (
                  <>
                    <Loader2 size={18} className="animate-spin" />
                    {t("common.saving", "Guardando...")}
                  </>
                ) : (
                  <>
                    <Plus size={18} />
                    {t("common.create", "Crear y Continuar")}
                  </>
                )}
              </button>
            </div>
          </div>
        )}
      </div>
    </div>
  );
}
