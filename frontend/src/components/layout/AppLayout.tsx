import { useTranslation } from 'react-i18next';
import { Outlet } from 'react-router-dom';
import { Sidebar } from './Sidebar';
import { Header } from './Header';
// useSyncWorker eliminado: se monta una sola vez en App.tsx
import { useAutoSync } from '../../hooks/useAutoSync';
import { ToastContainer } from '../system/ToastContainer';

export function AppLayout() {
  const { t } = useTranslation();
// useSyncWorker() movido a App.tsx (único punto de montaje)
  // Sync periódico cada 5min (el sync inicial lo hace useSyncWorker en App.tsx)
  useAutoSync({ intervalMinutes: 5 });

  return (
    <div className="flex h-screen bg-gray-50 dark:bg-slate-900 overflow-hidden transition-colors duration-200">
      <Sidebar />
      <div className="flex-1 flex flex-col overflow-hidden">
        <Header />
        <main className="flex-1 overflow-y-auto bg-white dark:bg-slate-800 transition-colors duration-200">
          <Outlet />
        </main>
      </div>
      <ToastContainer />
    </div>
  );
}
