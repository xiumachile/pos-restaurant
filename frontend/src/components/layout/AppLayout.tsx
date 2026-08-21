import { Outlet } from 'react-router-dom';
import { Sidebar } from './Sidebar';
import { Header } from './Header';
import { useSyncWorker } from '../../hooks/useSyncWorker';
import { useAutoSync } from '../../hooks/useAutoSync';
import { ToastContainer } from '../system/ToastContainer';

export function AppLayout() {
  useSyncWorker();          // Worker cada 15s (push de eventos locales)
  useAutoSync({             // Sync al autenticarse + periódica cada 5min
    syncOnAuth: true,
    intervalMinutes: 5,
  });

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
