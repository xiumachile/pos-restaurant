import { useState, useEffect } from "react";
import {
  X,
  Loader2,
  AlertCircle,
  Lock,
  RefreshCw,
  FolderOpen,
  Save,
} from "lucide-react";
import {
  useSubstitutionPolicies,
  useUpdateSubstitutionPolicy,
  useDeleteSubstitutionPolicy,
} from "@/hooks/useComboSubstitutions";
import { comboService, type SubstitutionPolicy, type SubstitutionMode } from "@/services/comboService";
import type { Category } from "@/types/catalog";
import { getTranslatedName } from "@/types/catalog";

interface ComboSubstitutionModalProps {
  menuItemUuid: string;
  comboName: string;
  onClose: () => void;
}

type ModeOption = {
  value: SubstitutionMode;
  label: string;
  icon: typeof Lock;
  description: string;
};

const MODE_OPTIONS: ModeOption[] = [
  {
    value: "no_substitution",
    label: "No sustituir",
    icon: Lock,
    description: "El producto no puede ser reemplazado",
  },
  {
    value: "any_product",
    label: "Cualquier producto",
    icon: RefreshCw,
    description: "Permite sustituir por cualquier producto del menú",
  },
  {
    value: "allowed_category",
    label: "Categoría permitida",
    icon: FolderOpen,
    description: "Solo permite productos de una categoría específica",
  },
];

export function ComboSubstitutionModal({
  menuItemUuid,
  comboName,
  onClose,
}: ComboSubstitutionModalProps) {
  const { data: policies, isLoading, error } = useSubstitutionPolicies(menuItemUuid);
  const updateMutation = useUpdateSubstitutionPolicy();
  const deleteMutation = useDeleteSubstitutionPolicy();
  const [categories, setCategories] = useState<Category[]>([]);
  const [editingProduct, setEditingProduct] = useState<string | null>(null);

  // Cargar categorías una sola vez
  useEffect(() => {
    comboService.listCategories().then(setCategories).catch(console.error);
  }, []);

  const handleSave = async (
    policy: SubstitutionPolicy,
    mode: SubstitutionMode,
    allowedCategoryId?: string | null,
    maxPriceDelta?: number | null,
    requiresAuth?: boolean
  ) => {
    try {
      await updateMutation.mutateAsync({
        menuItemUuid,
        productUuid: policy.product_id,
        mode,
        allowed_category_id: allowedCategoryId,
        max_price_delta: maxPriceDelta,
        requires_authorization: requiresAuth,
      });
      setEditingProduct(null);
    } catch (err) {
      console.error("Error al guardar:", err);
    }
  };

  const getModeLabel = (mode: SubstitutionMode | null): string => {
    if (!mode) return "Sin configuración";
    return MODE_OPTIONS.find((m) => m.value === mode)?.label ?? mode;
  };

  const getModeBadgeClass = (mode: SubstitutionMode | null): string => {
    switch (mode) {
      case "no_substitution":
        return "bg-red-500/20 text-red-300 border-red-500/30";
      case "any_product":
        return "bg-green-500/20 text-green-300 border-green-500/30";
      case "allowed_category":
        return "bg-blue-500/20 text-blue-300 border-blue-500/30";
      default:
        return "bg-slate-500/20 text-slate-300 border-slate-500/30";
    }
  };

  return (
    <div className="fixed inset-0 bg-black/70 backdrop-blur-sm z-50 flex items-center justify-center p-4">
      <div className="bg-slate-900 border border-slate-700 rounded-xl shadow-2xl max-w-3xl w-full max-h-[90vh] flex flex-col">
        {/* Header */}
        <div className="flex items-center justify-between p-5 border-b border-slate-700">
          <div>
            <h2 className="text-xl font-bold text-white">
              Configurar sustituciones
            </h2>
            <p className="text-sm text-slate-400 mt-1">{comboName}</p>
          </div>
          <button
            onClick={onClose}
            className="text-slate-400 hover:text-white p-2 rounded-lg hover:bg-slate-800 transition-colors"
          >
            <X size={20} />
          </button>
        </div>

        {/* Body */}
        <div className="flex-1 overflow-y-auto p-5">
          {isLoading ? (
            <div className="flex items-center justify-center py-12">
              <Loader2 className="animate-spin text-orange-500" size={32} />
            </div>
          ) : error ? (
            <div className="bg-red-900/30 border border-red-800 rounded-lg p-6 text-center">
              <AlertCircle className="mx-auto text-red-400 mb-3" size={32} />
              <p className="text-red-300">Error al cargar políticas</p>
            </div>
          ) : !policies || policies.length === 0 ? (
            <div className="bg-slate-800/50 border border-slate-700 rounded-lg p-8 text-center">
              <p className="text-slate-400">
                Este combo no tiene productos asignados.
              </p>
            </div>
          ) : (
            <div className="space-y-3">
              {policies.map((policy) => (
                <div
                  key={policy.product_id}
                  className="bg-slate-800/50 border border-slate-700 rounded-lg p-4"
                >
                  <div className="flex items-start justify-between gap-3 mb-3">
                    <div className="flex-1">
                      <h3 className="font-semibold text-white">
                        {policy.product_name}
                      </h3>
                      <p className="text-xs text-slate-500 mt-1">
                        Cantidad: {policy.quantity}
                        {policy.scope !== "none" && (
                          <span className="ml-2 text-amber-400">
                            · Override {policy.scope === "branch" ? "sucursal" : "empresa"}
                          </span>
                        )}
                      </p>
                    </div>
                    <span
                      className={`inline-flex items-center gap-1 px-2.5 py-1 rounded-full text-xs font-medium border ${getModeBadgeClass(
                        policy.mode
                      )}`}
                    >
                      {getModeLabel(policy.mode)}
                    </span>
                  </div>

                  {/* Detalles de la política actual */}
                  <div className="text-xs text-slate-400 space-y-1 mb-3">
                    {policy.allowed_category && (
                      <p>
                        📂 Categoría:{" "}
                        <span className="text-slate-300">
                          {policy.allowed_category.name}
                        </span>
                      </p>
                    )}
                    {policy.max_price_delta != null &&
                      policy.max_price_delta > 0 && (
                        <p>
                          💰 Diferencia máxima:{" "}
                          <span className="text-slate-300">
                            ${policy.max_price_delta.toLocaleString("es-CL")}
                          </span>
                        </p>
                      )}
                    {policy.requires_authorization && (
                      <p>🔐 Requiere autorización de gerente</p>
                    )}
                  </div>

                  {/* Editor inline */}
                  {editingProduct === policy.product_id ? (
                    <PolicyEditor
                      policy={policy}
                      categories={categories}
                      onSave={(mode, catId, maxDelta, reqAuth) =>
                        handleSave(policy, mode, catId, maxDelta, reqAuth)
                      }
                      onCancel={() => setEditingProduct(null)}
                      isSaving={updateMutation.isPending}
                    />
                  ) : (
                    <button
                      onClick={() => setEditingProduct(policy.product_id)}
                      className="text-xs text-orange-400 hover:text-orange-300 font-medium"
                    >
                      ✏️ Editar política
                    </button>
                  )}
                </div>
              ))}
            </div>
          )}
        </div>

        {/* Footer */}
        <div className="p-4 border-t border-slate-700 flex justify-end gap-2">
          <button
            onClick={onClose}
            className="px-4 py-2 bg-slate-800 hover:bg-slate-700 text-white rounded-lg font-medium transition-colors"
          >
            Cerrar
          </button>
        </div>
      </div>
    </div>
  );
}

/* ─── Componente interno: editor de política por producto ─── */

interface PolicyEditorProps {
  policy: SubstitutionPolicy;
  categories: Category[];
  onSave: (
    mode: SubstitutionMode,
    allowedCategoryId?: string | null,
    maxPriceDelta?: number | null,
    requiresAuthorization?: boolean
  ) => void;
  onCancel: () => void;
  isSaving: boolean;
}

function PolicyEditor({
  policy,
  categories,
  onSave,
  onCancel,
  isSaving,
}: PolicyEditorProps) {
  const [mode, setMode] = useState<SubstitutionMode>(
    policy.mode ?? "no_substitution"
  );
  const [categoryId, setCategoryId] = useState<string | null>(
    policy.allowed_category?.id ?? null
  );
  const [maxDelta, setMaxDelta] = useState<number>(
    policy.max_price_delta ?? 0
  );
  const [reqAuth, setReqAuth] = useState(policy.requires_authorization);

  return (
    <div className="border-t border-slate-700 pt-4 mt-3 space-y-4">
      {/* Selector de modo */}
      <div className="grid grid-cols-3 gap-2">
        {MODE_OPTIONS.map((opt) => {
          const Icon = opt.icon;
          const selected = mode === opt.value;
          return (
            <button
              key={opt.value}
              type="button"
              onClick={() => setMode(opt.value)}
              className={`flex flex-col items-center gap-1 p-3 rounded-lg border text-xs transition-colors ${
                selected
                  ? "bg-orange-500/20 border-orange-500 text-orange-300"
                  : "bg-slate-900/50 border-slate-700 text-slate-400 hover:border-slate-500"
              }`}
            >
              <Icon size={16} />
              <span className="font-medium">{opt.label}</span>
            </button>
          );
        })}
      </div>

      {/* Descripción del modo */}
      <p className="text-xs text-slate-500 italic">
        {MODE_OPTIONS.find((m) => m.value === mode)?.description}
      </p>

      {/* Selector de categoría (solo para allowed_category) */}
      {mode === "allowed_category" && (
        <div>
          <label className="block text-xs text-slate-400 mb-1.5">
            Categoría permitida
          </label>
          <select
            value={categoryId ?? ""}
            onChange={(e) => setCategoryId(e.target.value || null)}
            className="w-full px-3 py-2 bg-slate-900 border border-slate-700 rounded-lg text-white text-sm focus:outline-none focus:ring-2 focus:ring-orange-500"
          >
            <option value="">Selecciona una categoría...</option>
            {categories.map((cat) => (
              <option key={cat.uuid} value={cat.uuid}>
                {getTranslatedName(cat.name_translations)}
              </option>
            ))}
          </select>
        </div>
      )}

      {/* Diferencia máxima de precio */}
      {mode !== "no_substitution" && (
        <div>
          <label className="block text-xs text-slate-400 mb-1.5">
            Diferencia máxima de precio (opcional)
          </label>
          <input
            type="number"
            min="0"
            value={maxDelta || ""}
            onChange={(e) => setMaxDelta(parseFloat(e.target.value) || 0)}
            placeholder="Ej: 2000"
            className="w-full px-3 py-2 bg-slate-900 border border-slate-700 rounded-lg text-white text-sm focus:outline-none focus:ring-2 focus:ring-orange-500"
          />
          <p className="text-xs text-slate-500 mt-1">
            Máximo recargo permitido al sustituir (CLP). Deja vacío para ilimitado.
          </p>
        </div>
      )}

      {/* Requiere autorización */}
      {mode !== "no_substitution" && (
        <label className="flex items-center gap-2 cursor-pointer">
          <input
            type="checkbox"
            checked={reqAuth}
            onChange={(e) => setReqAuth(e.target.checked)}
            className="w-4 h-4 accent-orange-500"
          />
          <span className="text-sm text-slate-300">
            🔐 Requiere autorización de gerente al sustituir
          </span>
        </label>
      )}

      {/* Botones */}
      <div className="flex gap-2 pt-2">
        <button
          onClick={() => onSave(mode, categoryId, maxDelta || null, reqAuth)}
          disabled={isSaving}
          className="flex items-center gap-1.5 px-4 py-2 bg-orange-500 hover:bg-orange-600 disabled:bg-slate-700 text-white rounded-lg text-sm font-medium transition-colors"
        >
          {isSaving ? (
            <Loader2 className="animate-spin" size={14} />
          ) : (
            <Save size={14} />
          )}
          {isSaving ? "Guardando..." : "Guardar"}
        </button>
        <button
          onClick={onCancel}
          disabled={isSaving}
          className="px-4 py-2 bg-slate-800 hover:bg-slate-700 text-white rounded-lg text-sm font-medium transition-colors"
        >
          Cancelar
        </button>
      </div>
    </div>
  );
}
