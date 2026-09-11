# ADR-007: Arquitectura Híbrida de Impresión y Cobro Offline-First

**Fecha**: Septiembre 2026  
**Estado**: Aceptado  
**Contexto**: POS desktop con Tauri + modo offline-first

---

## Contexto

El sistema POS debe operar en restaurantes donde la conexión a internet puede
fallar en cualquier momento. Los procesos críticos (cobro, impresión de tickets,
comandas de cocina) no pueden detenerse aunque el backend esté inaccesible.

Requisitos:
1. **Cobro offline**: El cajero debe poder cobrar sin conexión
2. **Impresión local**: Tickets y comandas deben imprimirse aunque el backend esté caído
3. **Sincronización diferida**: Al recuperar conexión, todos los eventos se sincronizan
4. **Transparencia**: El usuario final no debe notar si está online u offline

## Decisión

Implementamos una **arquitectura híbrida de dos motores paralelos**:

### 1. Dual Print Engine Architecture
┌─────────────────────────────────────────────────────┐
│ usePrintEngine (hook) │
│ Inicia ambos motores al autenticarse │
└─────────────────────────────────────────────────────┘
│
┌───────────┴───────────┐
│ │
▼ ▼
┌──────────────┐ ┌──────────────────┐
│ OnlinePrint │ │ OfflinePrint │
│ Engine │ │ Engine │
│ │ │ │
│ Polling 5s │ │ Polling 3s │
│ del backend │ │ de SQLite local │
│ │ │ │
│ Solo online │ │ SIEMPRE activo │
└──────┬───────┘ └────────┬─────────┘
│ │
▼ ▼
print_jobs local_print_jobs
(PostgreSQL) (SQLite local)


### 2. Hook Drop-in Replacement para Cobro

En `BillPaymentModalV2.tsx`:

```typescript
const isOffline = useConnectionMode();
const payBill = isOffline ? useOfflinePayment() : usePayBill();

Ambos hooks tienen la misma interfaz ({ billUuid, payload } → PayBillResponse),
lo que permite un reemplazo transparente sin modificar el resto de la UI.
3. Flujo Offline de Cobro
Usuario cobra offline
        ↓
useOfflinePayment.detecta modo offline
        ↓
offlinePaymentService.createPaymentOffline
        ├─ LocalBill (SQLite)
        ├─ LocalPayment (SQLite)
        ├─ CashMovement (SQLite, si es efectivo)
        ├─ LocalPrintJob (SQLite) ← NUEVO
        └─ SyncQueue (encola para sync futuro)
        ↓
OfflinePrintEngine detecta job pendiente (3s)
        ↓
Job se imprime automáticamente
        ↓
Cuando hay conexión → SyncEngine procesa SyncQueue
        ↓
Backend sincroniza bill + payment + movement

Componentes Clave
Infraestructura de Impresión Offline
Componente
Responsabilidad
local_print_jobs (tabla)
Cola persistente de jobs de impresión
LocalPrintJobRepository
CRUD + recovery de jobs abandonados
OfflinePrintEngine
Motor que procesa jobs locales (polling 3s)
useOfflinePrintJob
Hook para encolar impresiones desde UI
markAsPermanentlyFailed
Para errores críticos (sin retry)
Bridge de Cobro
Hook
Función
useConnectionMode
Detecta si estamos offline (navigator.onLine + simulatedOffline)
useOfflinePayment
Drop-in replacement de usePayBill con bridge a SQLite
usePayBill
Hook original online (sin cambios)
Consecuencias
Positivas
✅ Resilencia: Si el backend falla, los jobs locales siguen imprimiéndose
✅ Transparencia: El usuario no nota si está online/offline
✅ Bajo riesgo: No modifica código existente de impresión online
✅ Auditoría: Cada job local tiene user_id, branch_id, timestamps
✅ Recuperación: Jobs en printing > 2min vuelven a pending automáticamente
✅ Retry: Fallos temporales reintentan hasta max_attempts
Negativas
⚠️ Complejidad: Dos motores paralelos en lugar de uno
⚠️ Almacenamiento: Duplicación parcial (jobs locales + cloud)
⚠️ Consistencia eventual: Datos locales pueden diferir del backend temporalmente
⚠️ Resolución de conflictos: Si el backend rechaza un pago offline durante sync,
hay que reconciliar (pendiente de implementación)
Riesgos Mitigados
Pérdida de tickets: Cola persistente + recovery de jobs abandonados
Impresiones duplicadas: idempotency_key en cada job
Jobs pegados en printing: Timeout automático de 2 minutos
Errores críticos: markAsPermanentlyFailed evita reintentos inútiles
Alternativas Consideradas
A. Solo OnlinePrintEngine con queue local
❌ Backend es punto único de fallo
❌ Si backend cae, nada se imprime
B. Solo OfflinePrintEngine (sin backend)
❌ No aprovecha la infraestructura cloud existente
❌ Otros sistemas (web admin, analytics) no verían los datos
C. Wizard de cobro separado para offline
❌ Duplica 400+ líneas de UI
❌ Dos flujos diferentes confunden al usuario
❌ Mayor mantenimiento
D. Detección automática sin hook unificado
❌ Cada caller tendría que implementar la lógica de detección
❌ Inconsistencias entre componentes
Métricas de Implementación
Commits: 5 (infraestructura + integración)
Tests nuevos: 40+ (repos + hooks + engine)
Archivos nuevos: 8 (migración, repos, engine, hooks, tests)
Líneas agregadas: ~1500
Tests totales del proyecto: 250+ pasando
Referencias
ADR-002: Multi-tenant isolation
ADR-006: Event Sourcing híbrido offline
FASE 6 del roadmap: Repositorios offline
FASE 8 del roadmap: Event Sourcing híbrido

