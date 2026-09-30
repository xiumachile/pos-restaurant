import { useConfirmStore } from '@/store/useConfirmStore';
import { AlertTriangle, Info, Trash2, X } from 'lucide-react';
import { useTranslation } from 'react-i18next';

export function ConfirmDialog() {
  const { t } = useTranslation();
  const { isOpen, title, message, confirmText, cancelText, variant, onConfirm, onCancel, close } = useConfirmStore();

  if (!isOpen) return null;

  const handleConfirm = () => {
    onConfirm();
    close();
  };

  const handleCancel = () => {
    onCancel();
    close();
  };

  const getIcon = () => {
    switch (variant) {
      case 'danger': return <Trash2 className="w-6 h-6 text-red-500" />;
      case 'info': return <Info className="w-6 h-6 text-blue-500" />;
      default: return <AlertTriangle className="w-6 h-6 text-orange-500" />;
    }
  };

  const getBg = () => {
    switch (variant) {
      case 'danger': return 'bg-red-500/10';
      case 'info': return 'bg-blue-500/10';
      default: return 'bg-orange-500/10';
    }
  };

  const getConfirmBtn = () => {
    switch (variant) {
      case 'danger': return 'bg-red-600 hover:bg-red-700 text-white';
      case 'info': return 'bg-blue-600 hover:bg-blue-700 text-white';
      default: return 'bg-orange-500 hover:bg-orange-600 text-white';
    }
  };

  return (
    <div className="fixed inset-0 z-50 flex items-center justify-center bg-black/60 backdrop-blur-sm p-4">
      <div className="bg-slate-800 border border-slate-700 rounded-xl shadow-2xl w-full max-w-md overflow-hidden animate-in fade-in zoom-in-95 duration-200">
        <div className="p-6">
          <div className="flex items-start gap-4">
            <div className={`p-3 rounded-full ${getBg()}`}>
              {getIcon()}
            </div>
            <div className="flex-1">
              <h3 className="text-lg font-semibold text-white mb-2">{title}</h3>
              <p className="text-slate-300 text-sm leading-relaxed whitespace-pre-line">{message}</p>
            </div>
            <button
              onClick={handleCancel}
              className="text-slate-400 hover:text-white transition-colors"
            >
              <X size={20} />
            </button>
          </div>
        </div>
        <div className="px-6 py-4 bg-slate-900/50 border-t border-slate-700 flex justify-end gap-3">
          <button
            onClick={handleCancel}
            className="px-4 py-2 rounded-lg text-sm font-medium text-slate-300 hover:bg-slate-700 transition-colors"
          >
            {cancelText}
          </button>
          <button
            onClick={handleConfirm}
            className={`px-4 py-2 rounded-lg text-sm font-medium transition-colors ${getConfirmBtn()}`}
          >
            {confirmText}
          </button>
        </div>
      </div>
    </div>
  );
}
