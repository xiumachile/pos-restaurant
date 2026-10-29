import { useState } from 'react';
import { useNavigate } from 'react-router-dom';
import { useTables } from '@/hooks/useTables';
import { flattenAreas } from '@/types/tables';
import { Search, ArrowRight, Loader2 } from 'lucide-react';
import { useTranslation } from 'react-i18next';

export function FreeOrderPage() {
  const { t } = useTranslation();
  const navigate = useNavigate();
  const { data: areas = [], isLoading } = useTables();
  const [tableNumber, setTableNumber] = useState('');
  const [error, setError] = useState('');
  const [isSearching, setIsSearching] = useState(false);

  const allTables = flattenAreas(areas);

  const handleSubmit = (e: React.FormEvent) => {
    e.preventDefault();
    setError('');
    setIsSearching(true);

    // Simular un pequeño delay para feedback visual
    setTimeout(() => {
      const foundTable = allTables.find(
        (t) => t.table_number.toLowerCase() === tableNumber.trim().toLowerCase()
      );

      if (foundTable) {
        navigate(`/tables/${foundTable.uuid}`);
      } else {
        setError(t('tables.not_found', 'Mesa no encontrada. Verifica el número.'));
      }
      setIsSearching(false);
    }, 300);
  };

  if (isLoading) {
    return (
      <div className="flex items-center justify-center h-full">
        <Loader2 className="animate-spin text-orange-500" size={48} />
      </div>
    );
  }

  return (
    <div className="flex flex-col items-center justify-center h-full p-8 bg-slate-50 dark:bg-slate-950">
      <div className="w-full max-w-md space-y-8">
        <div className="text-center">
          <h1 className="text-3xl font-bold text-slate-900 dark:text-white mb-2">
            {t('tables.enter_table_number', 'Ingresar Número de Mesa')}
          </h1>
          <p className="text-slate-500 dark:text-slate-400">
            {t('tables.free_mode_desc', 'Escribe el número de la mesa para iniciar el pedido')}
          </p>
        </div>

        <form onSubmit={handleSubmit} className="space-y-4">
          <div className="relative">
            <Search className="absolute left-4 top-1/2 -translate-y-1/2 text-slate-400" size={24} />
            <input
              type="text"
              value={tableNumber}
              onChange={(e) => setTableNumber(e.target.value)}
              placeholder={t('tables.table_number_placeholder', 'Ej: M-01, 5, A-12')}
              className="w-full pl-12 pr-4 py-4 text-xl bg-white dark:bg-slate-800 border-2 border-slate-200 dark:border-slate-700 rounded-xl text-slate-900 dark:text-white placeholder-slate-400 focus:ring-2 focus:ring-orange-500 focus:border-orange-500 focus:outline-none transition-all"
              autoFocus
            />
          </div>
          
          {error && (
            <p className="text-red-500 text-center font-medium bg-red-50 dark:bg-red-900/20 py-2 rounded-lg">
              {error}
            </p>
          )}
          
          <button
            type="submit"
            disabled={isSearching || !tableNumber.trim()}
            className="w-full py-4 bg-orange-500 hover:bg-orange-600 disabled:bg-slate-300 dark:disabled:bg-slate-700 text-white font-bold rounded-xl flex items-center justify-center gap-2 transition-all shadow-lg hover:shadow-xl disabled:shadow-none"
          >
            {isSearching ? (
              <Loader2 className="animate-spin" size={24} />
            ) : (
              <>
                {t('tables.start_order', 'Iniciar Pedido')} 
                <ArrowRight size={24} />
              </>
            )}
          </button>
        </form>

        <div className="text-center">
          <button
            onClick={() => navigate('/')}
            className="text-sm text-slate-500 hover:text-orange-500 transition-colors"
          >
            ← {t('common.back', 'Volver')}
          </button>
        </div>
      </div>
    </div>
  );
}
