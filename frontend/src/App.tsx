import { useState } from "react";

function App() {
  const [count, setCount] = useState(0);

  return (
    <div className="min-h-screen bg-gradient-to-br from-slate-900 to-slate-800 text-white p-8">
      <div className="max-w-2xl mx-auto">
        <h1 className="text-5xl font-bold mb-4 text-center bg-gradient-to-r from-orange-400 to-red-500 bg-clip-text text-transparent">
          🍜 Wok & Mesa POS
        </h1>
        <p className="text-center text-slate-300 mb-8">
          Aplicación de escritorio POS - Setup inicial Tauri v2 + React
        </p>

        <div className="bg-slate-800/50 rounded-lg p-6 backdrop-blur-sm border border-slate-700">
          <h2 className="text-xl font-semibold mb-4 text-orange-400">
            ✅ Setup completado
          </h2>
          <ul className="space-y-2 text-sm text-slate-300">
            <li>✓ Tauri v2 (Rust + WebView)</li>
            <li>✓ React 18 + TypeScript</li>
            <li>✓ Vite + Tailwind CSS v4</li>
            <li>✓ Alias @/ configurado</li>
          </ul>

          <div className="mt-6 pt-6 border-t border-slate-700 text-center">
            <button
              onClick={() => setCount((c) => c + 1)}
              className="px-6 py-3 bg-orange-500 hover:bg-orange-600 text-white font-semibold rounded-lg transition-colors"
            >
              Clicks: {count}
            </button>
          </div>
        </div>

        <p className="text-center text-xs text-slate-500 mt-8">
          F13.1 - Setup inicial completo · Próximo: F13.2 Autenticación
        </p>
      </div>
    </div>
  );
}

export default App;
