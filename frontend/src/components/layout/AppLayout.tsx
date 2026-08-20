import { Outlet } from 'react-router-dom';
import { Sidebar } from './Sidebar';
import { Header } from './Header';
import { useSyncWorker } from '../../hooks/useSyncWorker';
import { ToastContainer } from '../system/ToastContainer';

export function AppLayout() {
  useSyncWorker();

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
