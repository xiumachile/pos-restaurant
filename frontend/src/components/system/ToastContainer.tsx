import { CheckCircle2, XCircle, AlertTriangle, Info, X } from "lucide-react";
import { useToastStore, type ToastType } from "../../store/useToastStore";

const toastStyles: Record<ToastType, { bg: string; border: string; icon: any; color: string }> = {
  success: {
    bg: "bg-green-900/90",
    border: "border-green-500",
    icon: CheckCircle2,
    color: "text-green-400",
  },
  error: {
    bg: "bg-red-900/90",
    border: "border-red-500",
    icon: XCircle,
    color: "text-red-400",
  },
  warning: {
    bg: "bg-amber-900/90",
    border: "border-amber-500",
    icon: AlertTriangle,
    color: "text-amber-400",
  },
  info: {
    bg: "bg-blue-900/90",
    border: "border-blue-500",
    icon: Info,
    color: "text-blue-400",
  },
};

export function ToastContainer() {
  const toasts = useToastStore((s) => s.toasts);
  const remove = useToastStore((s) => s.remove);

  return (
    <div className="fixed top-4 right-4 z-[100] flex flex-col gap-2 max-w-sm">
      {toasts.map((toast) => {
        const style = toastStyles[toast.type];
        const Icon = style.icon;

        return (
          <div
            key={toast.id}
            className={`${style.bg} border-2 ${style.border} rounded-lg p-4 shadow-xl backdrop-blur-sm flex items-start gap-3 animate-in slide-in-from-right-4`}
          >
            <Icon size={20} className={`${style.color} flex-shrink-0 mt-0.5`} />
            <div className="flex-1 text-white text-sm">{toast.message}</div>
            <button
              onClick={() => remove(toast.id)}
              className="text-white/60 hover:text-white transition-colors flex-shrink-0"
            >
              <X size={16} />
            </button>
          </div>
        );
      })}
    </div>
  );
}
