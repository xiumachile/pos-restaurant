# ADR-022: Contract Freeze Validado

**Fecha**: 2026-09-18  
**Estado**: Aceptada  
**Contexto**: Validación final del contrato frontend-backend antes de producción

---

## Contexto

Después de múltiples iteraciones de auditoría y corrección, el sistema POS ha alcanzado un estado de consistencia completa en:

1. **Modelo monetario**: CLP entero (sin decimales) en frontend y backend
2. **Semántica de propinas**: `sale_amount` vs `tip_amount` correctamente separados
3. **Semántica de cierre**: Orders marcadas como PAID solo cuando venta + propina están completas
4. **Integridad financiera**: Sin aritmética flotante, sin epsilon en comparaciones

## Decisión

Declarar el **Frontend Contract Freeze** como válido a partir del commit `fee8fcf`.

### Validaciones Cumplidas

#### Frontend (Tauri + React + TypeScript)
- ✅ 453 tests pasando (56 archivos)
- ✅ Typecheck sin errores
- ✅ SQLite con columnas INTEGER para montos
- ✅ Sin aritmética flotante en cálculos financieros
- ✅ EventStore como auditoría best-effort (ADR-021)

#### Backend (Laravel)
- ✅ 57+ tests pasando (módulo Payments)
- ✅ Sintaxis PHP válida (php -l OK)
- ✅ PaymentService: parámetros `int`, sin epsilon
- ✅ BillingService: sin `floatval`, comparaciones exactas
- ✅ Payment/Bill/Order entities: casts `['integer']`
- ✅ Semántica de cierre unificada (venta + propina)

#### Contrato OpenAPI
- ✅ 36 endpoints documentados
- ✅ Todos los montos como `integer` en schemas
- ✅ Idempotencia para mutaciones críticas

### Invariantes Financieras Garantizadas
1. Order.PAID ⟺ (pagos_venta + pagos_propina) >= amount_due
2. amount_due = grand_total + tip_amount
3. Payment.total_amount = amount + tip_amount (enteros)
4. Bill.paid_amount + Bill.remaining_amount = Bill.total (exacto)


## Consecuencias

### Positivas
- **Estabilidad**: Cualquier cambio futuro debe preservar estas invariantes
- **Claridad**: Frontend y backend tienen contrato explícito y validado
- **Confianza**: Sistema listo para producción

### Responsabilidades
- **Nuevos endpoints**: Deben seguir el modelo CLP entero
- **Modificaciones**: Deben preservar las invariantes financieras
- **Documentación**: Cualquier cambio requiere actualizar OpenAPI

## Referencias

- ADR-011: Modelo de Montos Chile (enteros, no decimales)
- ADR-018: Integridad Financiera en Backend (sin floats, sin epsilon)
- ADR-019: Contrato de sincronización de propinas
- ADR-021: EventStore es best-effort

## Historial

- **2026-09-18**: ADR-022 creado después de auditoría completa
- Commits de consolidación: `fee8fcf`, `4450d41`, `28e9f40`, `dcd96f3`, `d7d4a53`
