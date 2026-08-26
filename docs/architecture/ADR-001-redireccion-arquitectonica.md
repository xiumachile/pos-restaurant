# ADR-001: Redirección arquitectónica — Consolidación antes que extensión

**Estado:** Accepted
**Fecha:** 26 de agosto de 2026
**Decisor:** Xiuming Chile + Arquitecto de Software
**Reemplaza:** Metodología de desarrollo por features (fases A-F.2)

---

## Contexto

El proyecto `pos-restaurant` alcanzó 220 commits con una arquitectura modular
considerable pero con el siguiente patrón recurrente:

1. Se agregaba una funcionalidad (CRUD, UI, endpoint)
2. Posteriormente se descubrían efectos secundarios (tenant isolation, idempotencia,
   state machines, contratos entre módulos)
3. Se parchaban los efectos sin resolver la causa raíz

Esto generó deuda técnica acumulada en el core transaccional mientras el catálogo
y pricing avanzaron más rápido de lo que el core podía soportar.

### Trabajo realizado bajo la metodología anterior (Fases A-F.2)

| Fase | Trabajo | Commits | Estado |
|------|---------|---------|--------|
| A | CRUD categorías/productos | `0d569c3`, `cbb2be3` | Funcional, requiere retrofit |
| B | PriceList + ProductPrice (multicanal) | `e6305ad` | Funcional, requiere retrofit |
| C | Menu + MenuActivation + resolución automática | `f0b8cc5` | Funcional, requiere retrofit |
| D | Sustituciones de combos (jerarquía sucursal>empresa) | `c588e7d` | **Patrón a replicar** |
| E | Frontend admin unificado | `55b2f9e` | Funcional |
| F.1 | Fix modal + precios múltiples por lista | `59c5dd6` | Funcional |
| F.2 | Recetas + food cost + ingredientes | `5602cb7` | Funcional, requiere retrofit |

### Deuda técnica identificada

| Problema | Evidencia | Severidad |
|----------|-----------|-----------|
| Cashier muta Tables por DB directa | `DB::table('restaurant_tables')` en CashierTablesController:255,408 | 🔴 Alta |
| Impresión backend hace `fsockopen` directo | PrintService.php:82 — no viable multi-sucursal | 🟡 Media |
| Sin CI/CD automático | No hay GitHub Actions ni GitLab CI | 🔴 Alta |
| Bill permite delete() físico | BillingService.php:263 — reparación de bill corrupta | 🟢 Baja |
| Sin idempotencia en mutaciones críticas | Solo Payments tiene `idempotency_key` | 🟡 Media |
| `withoutGlobalScopes()` en módulos cross | RecipeController, ComboReplacementRuleController | 🔴 Alta |
| Sin state machines formales | Order/Table/Payment usan string status | 🟡 Media (Fase 2) |
| Sin Payment Ledger append-only | No existen PaymentRefund/Reversal | 🟡 Media (Fase 2) |

### Infraestructura subutilizada

- `Company.settings` (JSONB) — existe pero **cero usos** en el código
- `OrderType.requiresTable()` — dominio listo, aplicación no lo explota
- Reglas de sustitución (jerarquía sucursal>empresa) — **patrón correcto no replicado**

---

## Decisión

**A partir de este commit, cambia la metodología de desarrollo.**

### DE (metodología anterior)

### A (nueva metodología)

### Principios rectores

1. **No hay feature sin backend verificable.** Antes de construir UI, confirmar que
   el backend de escritura existe y es correcto.
2. **No hay regla hardcodeada si depende del rubro.** Debe leer de `capabilities`.
3. **No hay LWW para datos financieros.** Solo para catálogo y configuración visual.
4. **No hay edición de registros financieros.** Toda corrección es transacción nueva.
5. **No hay bypass de TenantContext.** Si `withoutGlobalScopes()` es necesario, es
   síntoma de aislamiento mal diseñado.
6. **No hay módulo que mute tablas de otro módulo.** Comunicación por eventos o
   casos de uso.

---

## Consecuencias

### Positivas
- Base sólida para multi-rubro (restaurante, café, fast food, dark kitchen, etc.)
- Ventaja competitiva real: offline-first + configurable sin código por cliente
- Trabajo A-F.2 **no se tira**, se retrofit a `capabilities` (Fase 1)

### Negativas
- Velocidad de feature visible disminuye durante F0-F2 (consolidación no visible)
- Requiere disciplina para no "agregar un feature más" antes de consolidar

### Neutras
- Duración total: 45-48 semanas (secuencial), reducible con 2-3 desarrolladores

---

## Trabajo derivado

Las fases de consolidación están definidas en:
- `docs/roadmap/ROADMAP-F0-F12.md` — cronograma con prerrequisitos
- `docs/roadmap/STATUS-ACTUAL.md` — inventario técnico del estado actual

La primera fase (F0 — Estabilización) se ejecuta inmediatamente con este commit.

---

## Gate de entrada a cada fase

Ninguna fase se inicia sin cumplir:

| Fase | Gate |
|------|------|
| F0 | Commit de baseline (este ADR) |
| F1 | F0 completada + CI verde |
| F2 | F1 completada + capabilities funcionando |
| F3 | F2 completada + state machines testeadas |
| F4 | F3 completada + stress test offline pasado |
| F5 | F4 completada + Commerce Core estable |
| F6 | F5 completada + Pricing Engine operativo |
| F7 | F6 completada + Inventory Ledger auditado |
| F8-F12 | F7 completada + v0.7 productivo |

---

## Referencias

- Documento original de redirección: `redireccion_proyecto.txt` (en repo raíz)
- Plan arquitectónico complementario: documento "Plan de Consolidación Arquitectónica"
  del 25 de agosto de 2026

---

## Revisión programada

Este ADR se revisará al completar F2 (State Machines), F4 (Sync 2.0) y F7 (Production).
Si la metodología no está generando los resultados esperados, se ajusta con un ADR-002.
