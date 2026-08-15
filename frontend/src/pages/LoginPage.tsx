import { useState } from "react";
import { useAuth } from "@/hooks/useAuth";
import { EmailPasswordForm } from "@/components/auth/EmailPasswordForm";
import { PinKeypad } from "@/components/auth/PinKeypad";
import { authService } from "@/services/authService";

type LoginMode = "email" | "pin";

/**
 * Página de login con dos modos:
 * - email: Login tradicional (admin, manager, cashier)
 * - pin: Login POS con PIN (garzones, cocina)
 */
export function LoginPage() {
  const { login, loginWithPin, loading, error } = useAuth();
  const [mode, setMode] = useState<LoginMode>("email");
  const [pin, setPin] = useState("");
  const [branches, setBranches] = useState<any[]>([]);
  const [selectedBranchId, setSelectedBranchId] = useState<number | null>(null);

  // Cargar sucursales cuando se cambia a modo PIN
  const handleModeChange = async (newMode: LoginMode) => {
    setMode(newMode);
    setPin("");
    if (newMode === "pin" && branches.length === 0) {
      // TODO: Cargar sucursales desde API
      // Por ahora, usar sucursal 1 como default
      setBranches([{ id: 1, name: "Sucursal Principal", code: "MAIN" }]);
      setSelectedBranchId(1);
    }
  };

  const handlePinSubmit = async () => {
    if (pin.length < 4 || !selectedBranchId) return;
    try {
      await loginWithPin({ branch_id: selectedBranchId, pin });
    } catch {
      setPin(""); // Limpiar PIN en caso de error
    }
  };

  return (
    <div className="min-h-screen bg-gradient-to-br from-slate-900 to-slate-800 flex items-center justify-center p-4">
      <div className="w-full max-w-md">
        {/* Logo y título */}
        <div className="text-center mb-8">
          <h1 className="text-4xl font-bold mb-2 bg-gradient-to-r from-orange-400 to-red-500 bg-clip-text text-transparent">
            🍜 Wok & Mesa POS
          </h1>
          <p className="text-slate-400 text-sm">
            Sistema de punto de venta para restaurantes
          </p>
        </div>

        {/* Card de login */}
        <div className="bg-slate-800/50 backdrop-blur-sm rounded-xl border border-slate-700 p-6 shadow-2xl">
          {/* Tabs de modo */}
          <div className="grid grid-cols-2 gap-2 mb-6">
            <button
              type="button"
              onClick={() => handleModeChange("email")}
              className={`py-2 px-4 rounded-lg font-medium transition-colors ${
                mode === "email"
                  ? "bg-orange-500 text-white"
                  : "bg-slate-700 text-slate-300 hover:bg-slate-600"
              }`}
            >
              Email / Contraseña
            </button>
            <button
              type="button"
              onClick={() => handleModeChange("pin")}
              className={`py-2 px-4 rounded-lg font-medium transition-colors ${
                mode === "pin"
                  ? "bg-orange-500 text-white"
                  : "bg-slate-700 text-slate-300 hover:bg-slate-600"
              }`}
            >
              PIN POS
            </button>
          </div>

          {/* Contenido según modo */}
          {mode === "email" ? (
            <EmailPasswordForm onSubmit={login} loading={loading} error={error} />
          ) : (
            <div className="space-y-4">
              {/* Selector de sucursal */}
              {branches.length > 1 && (
                <div>
                  <label className="block text-sm font-medium text-slate-300 mb-1">
                    Sucursal
                  </label>
                  <select
                    value={selectedBranchId || ""}
                    onChange={(e) => setSelectedBranchId(Number(e.target.value))}
                    disabled={loading}
                    className="w-full px-4 py-3 rounded-lg bg-slate-800 border border-slate-700 text-white focus:outline-none focus:ring-2 focus:ring-orange-500 disabled:opacity-50"
                  >
                    {branches.map((branch) => (
                      <option key={branch.id} value={branch.id}>
                        {branch.name}
                      </option>
                    ))}
                  </select>
                </div>
              )}

              {/* Teclado PIN */}
              <PinKeypad pin={pin} onPinChange={setPin} maxLength={6} disabled={loading} />

              {error && (
                <div className="p-3 rounded-lg bg-red-900/30 border border-red-800 text-red-300 text-sm">
                  {error}
                </div>
              )}

              <button
                type="button"
                onClick={handlePinSubmit}
                disabled={loading || pin.length < 4}
                className="w-full py-3 px-4 rounded-lg bg-orange-500 hover:bg-orange-600 active:bg-orange-700 text-white font-semibold transition-colors disabled:opacity-50 disabled:cursor-not-allowed"
              >
                {loading ? "Iniciando sesión..." : "Ingresar"}
              </button>
            </div>
          )}
        </div>

        {/* Footer */}
        <p className="text-center text-xs text-slate-500 mt-6">
          v0.1.0 · Wok & Mesa POS · Tauri + React
        </p>
      </div>
    </div>
  );
}
