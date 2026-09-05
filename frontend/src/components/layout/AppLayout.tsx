import { Outlet } from 'react-router-dom';
import { Sidebar } from './Sidebar';
import { Header } from './Header';
// useSyncWorker eliminado: se monta una sola vez en App.tsx
import { useAutoSync } from '../../hooks/useAutoSync';
import { ToastContainer } from '../system/ToastContainer';

export function AppLayout() {
// useSyncWorker() movido a App.tsx (único punto de montaje)
  // Sync periódico cada 5min (el sync inicial lo hace useSyncWorker en App.tsx)
  useAutoSync({ intervalMinutes: 5 });

  return (
    <div className="flex h-screen bg-slate-900 overflow-hidden">
      <Sidebar />
      <div className="flex-1 flex flex-col overflow-hidden">
        <Header />
        <main className="flex-1 overflow-y-auto bg-slate-800">
          <Outlet />
        </main>
      </div>
      <ToastContainer />
    </div>
  );
}
