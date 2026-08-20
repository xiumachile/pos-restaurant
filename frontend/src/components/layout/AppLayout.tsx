import { Outlet } from "react-router-dom";
import { Sidebar } from "./Sidebar";
import { Header } from "./Header";
import { useAutoSync } from "../../hooks/useAutoSync";
import { ToastContainer } from "../system/ToastContainer";

export function AppLayout() {
  useAutoSync({ syncOnMount: true, intervalMinutes: 5 });

  return (
    <div className="flex h-screen bg-slate-900 text-white overflow-hidden">
      <Sidebar />
      <div className="flex-1 flex flex-col overflow-hidden">
        <Header />
        <main className="flex-1 overflow-y-auto p-6">
          <Outlet />
        </main>
      </div>
      <ToastContainer />
    </div>
  );
}
