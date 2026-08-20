import { Loader2, AlertCircle, Database } from "lucide-react";

interface DatabaseLoaderProps {
  isInitializing: boolean;
  error: string | null;
  children: React.ReactNode;
}

/**
 * Componente que muestra un splash screen mientras la base de datos
 * local se inicializa. Si hay error, muestra un mensaje de error.
 */
export function DatabaseLoader({
  isInitializing,
  error,
  children,
}: DatabaseLoaderProps) {
  if (isInitializing) {
    return (
      <div className="fixed inset-0 bg-slate-950 flex items-center justify-center">
        <div className="text-center">
          <div className="inline-flex items-center justify-center w-20 h-20 rounded-full bg-orange-500/10 mb-6">
            <Database className="w-10 h-10 text-orange-400 animate-pulse" />
          </div>
          <h2 className="text-2xl font-bold text-white mb-2">
            Inicializando sistema
          </h2>
          <div className="flex items-center justify-center gap-2 text-slate-400">
            <Loader2 className="w-4 h-4 animate-spin" />
            <span>Preparando base de datos local...</span>
          </div>
        </div>
      </div>
    );
  }

  if (error) {
    return (
      <div className="fixed inset-0 bg-slate-950 flex items-center justify-center p-6">
        <div className="max-w-md w-full bg-red-950/30 border border-red-700/50 rounded-xl p-6">
          <div className="flex items-start gap-4">
            <div className="flex-shrink-0 w-12 h-12 rounded-full bg-red-500/20 flex items-center justify-center">
              <AlertCircle className="w-6 h-6 text-red-400" />
            </div>
            <div className="flex-1">
              <h2 className="text-xl font-bold text-white mb-2">
                Error al inicializar base de datos
              </h2>
              <p className="text-red-200 text-sm mb-4">{error}</p>
              <div className="bg-red-900/20 border border-red-800/30 rounded-lg p-3 text-xs text-red-300">
                <strong>Posibles soluciones:</strong>
                <ul className="list-disc list-inside mt-2 space-y-1">
                  <li>Reiniciar la aplicación</li>
                  <li>Verificar permisos de escritura en disco</li>
                  <li>Contactar soporte técnico</li>
                </ul>
              </div>
            </div>
          </div>
        </div>
      </div>
    );
  }

  return <>{children}</>;
}
