import { X, Copy, Check } from "lucide-react";
import { useState } from "react";
import type { SyncQueueItem } from "@/db/repositories/SyncQueueRepository";
import { enrichSyncQueueItem } from "@/services/sync/SyncQueueEnrichment";

interface Props {
  item: SyncQueueItem;
  onClose: () => void;
}

export function SyncItemDetailModal({ item, onClose }: Props) {
  const [copied, setCopied] = useState(false);

  const handleCopy = async () => {
    await navigator.clipboard.writeText(item.payload);
    setCopied(true);
    setTimeout(() => setCopied(false), 2000);
  };

  const parsedPayload = (() => {
    try {
      return JSON.parse(item.payload);
    } catch {
      return item.payload;
    }
  })();

  const enriched = enrichSyncQueueItem(item.payload, item.entity_type);

  return (
    <div className="fixed inset-0 bg-black/50 flex items-center justify-center z-50 p-4">
      <div className="bg-slate-800 rounded-lg max-w-2xl w-full max-h-[80vh] overflow-hidden flex flex-col">
        {/* Header */}
        <div className="flex items-center justify-between p-4 border-b border-slate-700">
          <h2 className="text-lg font-bold text-white">
            Detalle del Item
          </h2>
          <button
            onClick={onClose}
            className="p-1 hover:bg-slate-700 rounded transition-colors"
            aria-label="Cerrar modal"
          >
            <X className="w-5 h-5 text-slate-400" />
          </button>
        </div>

        {/* Content */}
        <div className="flex-1 overflow-y-auto p-4 space-y-4">
          {/* Info básica */}
          <div className="grid grid-cols-2 gap-4">
            <div>
              <label className="text-xs text-slate-400 block mb-1">ID</label>
              <code className="text-sm text-white font-mono bg-slate-900 px-2 py-1 rounded break-all">
                {item.id}
              </code>
            </div>
            <div>
              <label className="text-xs text-slate-400 block mb-1">Entidad</label>
              <div className="text-sm text-white">{item.entity_type}</div>
            </div>
            <div>
              <label className="text-xs text-slate-400 block mb-1">Acción</label>
              <div className="text-sm text-white">{item.action}</div>
            </div>
            <div>
              <label className="text-xs text-slate-400 block mb-1">Estado</label>
              <div className="text-sm text-white">{item.sync_status}</div>
            </div>
            <div>
              <label className="text-xs text-slate-400 block mb-1">Intentos</label>
              <div className="text-sm text-white">
                {item.attempts} / {item.max_attempts}
              </div>
            </div>
            <div>
              <label className="text-xs text-slate-400 block mb-1">Creado</label>
              <div className="text-sm text-white">
                {new Date(item.created_at).toLocaleString("es-CL")}
              </div>
            </div>
          </div>

          {/* Campos enriquecidos */}
          {(enriched.payment_uuid || enriched.idempotency_key || enriched.terminal_id || enriched.cash_session_uuid) && (
            <div className="border-t border-slate-700 pt-4">
              <h3 className="text-sm font-semibold text-white mb-3">
                🔍 Campos de Diagnóstico
              </h3>
              <div className="grid grid-cols-2 gap-3">
                {enriched.payment_uuid && (
                  <div>
                    <label className="text-xs text-slate-400 block mb-1">
                      Payment UUID
                    </label>
                    <code className="text-xs text-orange-300 font-mono bg-slate-900 px-2 py-1 rounded block break-all">
                      {enriched.payment_uuid}
                    </code>
                  </div>
                )}
                {enriched.cash_session_uuid && (
                  <div>
                    <label className="text-xs text-slate-400 block mb-1">
                      Cash Session
                    </label>
                    <code className="text-xs text-blue-300 font-mono bg-slate-900 px-2 py-1 rounded block break-all">
                      {enriched.cash_session_uuid}
                    </code>
                  </div>
                )}
                {enriched.terminal_id && (
                  <div>
                    <label className="text-xs text-slate-400 block mb-1">
                      Terminal
                    </label>
                    <code className="text-xs text-green-300 font-mono bg-slate-900 px-2 py-1 rounded block break-all">
                      {enriched.terminal_id}
                    </code>
                  </div>
                )}
                {enriched.idempotency_key && (
                  <div>
                    <label className="text-xs text-slate-400 block mb-1">
                      Idempotency Key
                    </label>
                    <code className="text-xs text-purple-300 font-mono bg-slate-900 px-2 py-1 rounded block break-all">
                      {enriched.idempotency_key}
                    </code>
                  </div>
                )}
              </div>
            </div>
          )}

          {/* Error (si existe) */}
          {item.last_error && (
            <div>
              <label className="text-xs text-slate-400 block mb-1">
                Último Error
              </label>
              <div className="bg-red-500/10 border border-red-500/30 rounded p-3">
                <p className="text-sm text-red-300 font-mono break-all">
                  {item.last_error}
                </p>
              </div>
            </div>
          )}

          {/* Payload */}
          <div>
            <div className="flex items-center justify-between mb-1">
              <label className="text-xs text-slate-400">Payload Completo</label>
              <button
                onClick={handleCopy}
                className="flex items-center gap-1 text-xs text-slate-400 hover:text-white transition-colors"
              >
                {copied ? (
                  <>
                    <Check className="w-3 h-3" />
                    Copiado
                  </>
                ) : (
                  <>
                    <Copy className="w-3 h-3" />
                    Copiar
                  </>
                )}
              </button>
            </div>
            <pre className="bg-slate-900 border border-slate-700 rounded p-3 overflow-x-auto text-xs text-slate-300 font-mono max-h-64 overflow-y-auto">
              {JSON.stringify(parsedPayload, null, 2)}
            </pre>
          </div>
        </div>
      </div>
    </div>
  );
}
