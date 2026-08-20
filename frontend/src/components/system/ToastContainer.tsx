import { useToastStore, type Toast } from '../../store/useToastStore';
import { CheckCircle2, XCircle, AlertTriangle, Info, X } from 'lucide-react';

const toastStyles: Record<Toast['type'], { bg: string; icon: any; color: string }> = {
  success: {
    bg: 'bg-green-500',
    icon: CheckCircle2,
    color: 'text-green-400',
  },
  error: {
    bg: 'bg-red-500',
    icon: XCircle,
    color: 'text-red-400',
  },
  warning: {
    bg: 'bg-amber-500',
    icon: AlertTriangle,
    color: 'text-amber-400',
  },
  info: {
    bg: 'bg-blue-500',
    icon: Info,
    color: 'text-blue-400',
  },
};

export function ToastContainer() {
  const toasts = useToastStore((s) => s.toasts);
  const removeToast = useToastStore((s) => s.removeToast);

  if (toasts.length === 0) return null;

  return (
    <div className="fixed top-4 right-4 z-[100] flex flex-col gap-2 max-w-md">
      {toasts.map((toast) => {
        const style = toastStyles[toast.type];
        const Icon = style.icon;
        
        return (
          <div
            key={toast.id}
            className={`${style.bg} text-white px-4 py-3 rounded-lg shadow-lg flex items-start gap-3 animate-in slide-in-from-right`}
          >
            <Icon size={20} className="flex-shrink-0 mt-0.5" />
            <p className="flex-1 text-sm font-medium">{toast.message}</p>
            <button
              onClick={() => removeToast(toast.id)}
              className="flex-shrink-0 hover:bg-white/20 rounded p-1 transition-colors"
            >
              <X size={16} />
            </button>
          </div>
        );
      })}
    </div>
  );
}
