// src/components/floor-plan/PrototipoVisual.tsx
import React from "react";
import { TableRenderer } from "./renderers/TableRenderer";
import { WallRenderer } from "./renderers/WallRenderer";
import { WindowRenderer } from "./renderers/WindowRenderer";
import { DoorRenderer } from "./renderers/DoorRenderer";
import { DoubleDoorRenderer } from "./renderers/DoubleDoorRenderer";
import { PlantRenderer } from "./renderers/PlantRenderer";
import { BoothRenderer } from "./renderers/BoothRenderer";
import { CounterRenderer } from "./renderers/CounterRenderer";
import { ColumnRenderer } from "./renderers/ColumnRenderer";
import { StoolRenderer } from "./renderers/StoolRenderer";
import { ServiceStationRenderer } from "./renderers/ServiceStationRenderer";

export function PrototipoVisual() {
  return (
    <div className="p-8 bg-slate-900 min-h-screen">
      <h1 className="text-3xl font-bold text-white mb-2">
        Prototipo Visual - Catálogo Completo P0
      </h1>
      <p className="text-slate-400 mb-8">
        10 objetos arquitectónicos validados antes de integrar al canvas
      </p>

      {/* SECCIÓN 1: MESAS */}
      <h2 className="text-xl font-bold text-orange-400 mb-4 border-b border-slate-700 pb-2">
        1. Mesas Operativas (Capa 1: Mobiliario + Capa 3: Estado)
      </h2>
      <div className="grid grid-cols-2 md:grid-cols-3 lg:grid-cols-5 gap-6 mb-12">
        
        {/* Mesa Redonda 2 pax */}
        <div className="bg-slate-800 p-4 rounded-lg border border-slate-700">
          <h3 className="text-xs font-semibold text-white mb-3 text-center">Mesa Redonda (2 pax)</h3>
          <div className="flex items-center justify-center h-32 bg-slate-950 rounded">
            <TableRenderer shape="round" width={60} height={60} seatLayout={{ top: 1, bottom: 1, left: 0, right: 0 }} statusColor="#10b981" />
          </div>
          <p className="mt-3 text-xs text-slate-400 text-center">Íntima · 2 sillas enfrentadas</p>
        </div>

        {/* Mesa Cuadrada 2 pax */}
        <div className="bg-slate-800 p-4 rounded-lg border border-slate-700">
          <h3 className="text-xs font-semibold text-white mb-3 text-center">Mesa Cuadrada (2 pax)</h3>
          <div className="flex items-center justify-center h-32 bg-slate-950 rounded">
            <TableRenderer shape="square" width={60} height={60} seatLayout={{ top: 1, bottom: 1, left: 0, right: 0 }} statusColor="#10b981" />
          </div>
          <p className="mt-3 text-xs text-slate-400 text-center">Compacta · Esquinas</p>
        </div>

        {/* Mesa Cuadrada 4 pax */}
        <div className="bg-slate-800 p-4 rounded-lg border border-slate-700">
          <h3 className="text-xs font-semibold text-white mb-3 text-center">Mesa Cuadrada (4 pax)</h3>
          <div className="flex items-center justify-center h-32 bg-slate-950 rounded">
            <TableRenderer shape="square" width={80} height={80} seatLayout={{ top: 1, bottom: 1, left: 1, right: 1 }} statusColor="#f59e0b" />
          </div>
          <p className="mt-3 text-xs text-slate-400 text-center">Modular · 1 silla por lado</p>
        </div>

        {/* Mesa Redonda 4 pax */}
        <div className="bg-slate-800 p-4 rounded-lg border border-slate-700">
          <h3 className="text-xs font-semibold text-white mb-3 text-center">Mesa Redonda (4 pax)</h3>
          <div className="flex items-center justify-center h-32 bg-slate-950 rounded">
            <TableRenderer shape="round" width={80} height={80} seatLayout={{ top: 1, bottom: 1, left: 1, right: 1 }} statusColor="#ef4444" />
          </div>
          <p className="mt-3 text-xs text-slate-400 text-center">Clásica · 4 sillas radiales</p>
        </div>

        {/* Mesa Rectangular 6 pax */}
        <div className="bg-slate-800 p-4 rounded-lg border border-slate-700">
          <h3 className="text-xs font-semibold text-white mb-3 text-center">Mesa Rectangular (6 pax)</h3>
          <div className="flex items-center justify-center h-32 bg-slate-950 rounded">
            <TableRenderer shape="rectangle" width={120} height={80} seatLayout={{ top: 2, bottom: 2, left: 1, right: 1 }} statusColor="#10b981" />
          </div>
          <p className="mt-3 text-xs text-slate-400 text-center">Familiar · 6 sillas</p>
        </div>

      </div>

      {/* SECCIÓN 2: ARQUITECTURA */}
      <h2 className="text-xl font-bold text-blue-400 mb-4 border-b border-slate-700 pb-2">
        2. Elementos Arquitectónicos
      </h2>
      <div className="grid grid-cols-2 md:grid-cols-4 lg:grid-cols-5 gap-6 mb-12">
        
        {/* Muro */}
        <div className="bg-slate-800 p-4 rounded-lg border border-slate-700">
          <h3 className="text-xs font-semibold text-white mb-3 text-center">Muro</h3>
          <div className="flex items-center justify-center h-32 bg-slate-950 rounded">
            <WallRenderer width={160} height={16} />
          </div>
          <p className="mt-3 text-xs text-slate-400 text-center">Patrón de corte</p>
        </div>

        {/* Columna */}
        <div className="bg-slate-800 p-4 rounded-lg border border-slate-700">
          <h3 className="text-xs font-semibold text-white mb-3 text-center">Columna</h3>
          <div className="flex items-center justify-center h-32 bg-slate-950 rounded">
            <ColumnRenderer width={50} height={50} />
          </div>
          <p className="mt-3 text-xs text-slate-400 text-center">Volumen 3D con sombra</p>
        </div>

        {/* Puerta Simple */}
        <div className="bg-slate-800 p-4 rounded-lg border border-slate-700">
          <h3 className="text-xs font-semibold text-white mb-3 text-center">Puerta Simple</h3>
          <div className="flex items-center justify-center h-32 bg-slate-950 rounded">
            <DoorRenderer width={80} height={16} swingDirection="right" />
          </div>
          <p className="mt-3 text-xs text-slate-400 text-center">Arco de apertura</p>
        </div>

        {/* Puerta Doble */}
        <div className="bg-slate-800 p-4 rounded-lg border border-slate-700">
          <h3 className="text-xs font-semibold text-white mb-3 text-center">Puerta Doble</h3>
          <div className="flex items-center justify-center h-32 bg-slate-950 rounded">
            <DoubleDoorRenderer width={160} height={16} />
          </div>
          <p className="mt-3 text-xs text-slate-400 text-center">Dos hojas, dos arcos</p>
        </div>

        {/* Ventana */}
        <div className="bg-slate-800 p-4 rounded-lg border border-slate-700">
          <h3 className="text-xs font-semibold text-white mb-3 text-center">Ventana</h3>
          <div className="flex items-center justify-center h-32 bg-slate-950 rounded">
            <WindowRenderer width={120} height={16} />
          </div>
          <p className="mt-3 text-xs text-slate-400 text-center">Vidrio azul con marco</p>
        </div>

      </div>

      {/* SECCIÓN 3: MOBILIARIO Y SERVICIO */}
      <h2 className="text-xl font-bold text-emerald-400 mb-4 border-b border-slate-700 pb-2">
        3. Mobiliario, Asientos y Servicio
      </h2>
      <div className="grid grid-cols-2 md:grid-cols-4 lg:grid-cols-5 gap-6 mb-12">
        
        {/* Booth */}
        <div className="bg-slate-800 p-4 rounded-lg border border-slate-700">
          <h3 className="text-xs font-semibold text-white mb-3 text-center">Booth</h3>
          <div className="flex items-center justify-center h-32 bg-slate-950 rounded">
            <BoothRenderer width={140} height={100} />
          </div>
          <p className="mt-3 text-xs text-slate-400 text-center">Asientos + mesa central</p>
        </div>

        {/* Barra / Mostrador */}
        <div className="bg-slate-800 p-4 rounded-lg border border-slate-700">
          <h3 className="text-xs font-semibold text-white mb-3 text-center">Barra / Mostrador</h3>
          <div className="flex items-center justify-center h-32 bg-slate-950 rounded">
            <CounterRenderer width={180} height={60} />
          </div>
          <p className="mt-3 text-xs text-slate-400 text-center">Superficie de trabajo</p>
        </div>

        {/* Taburete */}
        <div className="bg-slate-800 p-4 rounded-lg border border-slate-700">
          <h3 className="text-xs font-semibold text-white mb-3 text-center">Taburete</h3>
          <div className="flex items-center justify-center h-32 bg-slate-950 rounded">
            <StoolRenderer size={40} />
          </div>
          <p className="mt-3 text-xs text-slate-400 text-center">Base circular + asiento</p>
        </div>

        {/* Estación de Garzón */}
        <div className="bg-slate-800 p-4 rounded-lg border border-slate-700">
          <h3 className="text-xs font-semibold text-white mb-3 text-center">Estación de Garzón</h3>
          <div className="flex items-center justify-center h-32 bg-slate-950 rounded">
            <ServiceStationRenderer width={80} height={60} />
          </div>
          <p className="mt-3 text-xs text-slate-400 text-center">Compartimentos + superficie</p>
        </div>

        {/* Planta Decorativa */}
        <div className="bg-slate-800 p-4 rounded-lg border border-slate-700">
          <h3 className="text-xs font-semibold text-white mb-3 text-center">Planta Decorativa</h3>
          <div className="flex items-center justify-center h-32 bg-slate-950 rounded">
            <PlantRenderer diameter={60} />
          </div>
          <p className="mt-3 text-xs text-slate-400 text-center">Maceta + follaje orgánico</p>
        </div>

      </div>

      {/* CRITERIOS DE VALIDACIÓN */}
      <div className="mt-12 p-6 bg-emerald-900/20 border border-emerald-700 rounded-lg">
        <h3 className="text-lg font-bold text-emerald-300 mb-3">
          ✅ Criterios de Aceptación - Catálogo P0 Completo
        </h3>
        <ul className="text-sm text-emerald-200 space-y-2">
          <li>✅ 10 objetos arquitectónicos con geometría propia y reconocible</li>
          <li>✅ Mesas: 5 variantes (2, 4, 6 pax) con madera realista y sillas alineadas</li>
          <li>✅ Arquitectura: muros, columnas, puertas (simple/doble), ventanas diferenciados</li>
          <li>✅ Servicio: barra, estación de garzón con detalles funcionales</li>
          <li>✅ Asientos: booth con mesa central, taburete circular</li>
          <li>✅ Decoración: planta con maceta y follaje orgánico</li>
          <li>✅ Todos los objetos usan sombras, materiales y proporciones realistas</li>
          <li>✅ Listo para integrar al FloorPlanCanvas real</li>
        </ul>
      </div>
    </div>
  );
}
