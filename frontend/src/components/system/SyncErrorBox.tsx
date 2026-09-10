import { AlertCircle } from "lucide-react";

interface SyncErrorBoxProps {
  errorMessage: string;
  title?: string;
  className?: string;
}

export function SyncErrorBox({ 
  errorMessage, 
  title = "Error de sincronización:",
  className = "" 
}: SyncErrorBoxProps) {
  return (
    <div 
      className={`p-3 bg-red-50 border border-red-200 rounded-lg ${className}`}
      role="alert"
      aria-live="assertive"
    >
      <div className="flex items-start gap-2">
        <AlertCircle className="w-4 h-4 text-red-600 flex-shrink-0 mt-0.5" aria-hidden="true" />
        <div className="flex-1 min-w-0">
          <p className="text-sm text-red-800 font-medium mb-1">{title}</p>
          <p className="text-xs text-red-600 font-mono break-all">
            {errorMessage}
          </p>
        </div>
      </div>
    </div>
  );
}
