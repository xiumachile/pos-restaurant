# ADR-002: Backward Compatibility en Transiciones de Estado

**Estado**: ✅ Aceptado  
**Fecha**: 2026-08-31  
**Autores**: Equipo de desarrollo  
**Relacionado con**: [ADR-001](./001-fulfillment-model.md)

## Contexto

Al agregar estados específicos por canal de fulfillment
(`READY_FOR_PICKUP`, `PICKED_UP`, `DISPATCHED`, `DELIVERED`),
enfrentamos una decisión crítica: ¿cómo migrar sin romper clientes API existentes?

**Situación antes del cambio**:
- Existían 8 estados: `DRAFT → CONFIRMED → PREPARING → READY → SERVED → PAID → CLOSED / CANCELLED`
- El flujo era único y lineal, sin importar el tipo de pedido
- 789 tests existentes asumían el flujo `READY → SERVED` para cualquier tipo
- `OrderTransitionsApiTest` usaba `takeout` y hacía `ready → served` directamente
- Clientes API de producción (POS local, apps móviles) estaban usando este flujo

**Riesgo de ruptura**:
- Si hacíamos `READY → READY_FOR_PICKUP` obligatorio para `takeout`, romperíamos 10+ tests
- Peor aún: romperíamos integraciones de clientes en producción sin avisar
- No era factible coordinar un "flag day" con todos los clientes

## Decisión

Elegimos una estrategia de **backward compatibility permisiva**:

### Regla de transición: READY permite MÚLTIPLES caminos
                ┌──→ SERVED (legacy, funciona en cualquier canal)
                │
                READY ──────────────┼──→ READY_FOR_PICKUP (nuevo, solo takeout)
                │
                └──→ DISPATCHED (nuevo, solo delivery)

### Implementación en `OrderStatus::allowedTransitionsFor()`

```php
self::READY => match($channel) {
    FulfillmentChannel::ONSITE => [self::SERVED, self::CANCELLED],
    FulfillmentChannel::PICKUP => [
        self::READY_FOR_PICKUP,   // nuevo camino canónico
        self::SERVED,             // legacy path preservado
        self::CANCELLED,
    ],
    FulfillmentChannel::DELIVERY => [
        self::DISPATCHED,         // nuevo camino canónico
        self::SERVED,             // legacy path preservado
        self::CANCELLED,
    ],
},
Validación asimétrica
Transiciones NUEVAS específicas son estrictas por canal:
READY_FOR_PICKUP → PICKED_UP solo en pickup
DISPATCHED → DELIVERED solo en delivery
READY → DISPATCHED inválido para pickup (422)
READY → READY_FOR_PICKUP inválido para delivery (422)
Transición LEGACY READY → SERVED es permisiva:
Válida para CUALQUIER canal (onsite, pickup, delivery)
SERVED → PAID sigue funcionando igual
Timestamp served_at se setea normalmente
Dos métodos de validación (dualidad intencional)
// Legacy, sin contexto del pedido (para código que no tiene Order)
public function canTransitionTo(OrderStatus $newStatus): bool

// Nuevo, con contexto del pedido (para OrderStateMachine)
public function canTransitionToFor(OrderStatus $newStatus, Order $order): bool
El método legacy allowedTransitions() retorna la UNIÓN de todas las transiciones
posibles de todos los canales. Es permisivo pero seguro (validación estricta ocurre en
allowedTransitionsFor cuando se tiene el Order).
Consecuencias
Positivas ✅
100% backward compatible: 789 tests existentes siguen pasando sin cambios
Clientes API existentes no se rompen: pueden seguir usando ready → served
Adopción gradual: equipos pueden migrar a los nuevos endpoints cuando quieran
No requiere coordinación con clientes de producción
Flexibilidad: clientes legados y nuevos pueden coexistir indefinidamente
Negativas ⚠️
Semántica más laxa: takeout puede terminar en served (no canónico pero válido)
Posible confusión: ¿debería un cliente usar ready_for_pickup o served?
Dos caminos al mismo resultado en algunos casos (takeout: ready → served vs ready → ready_for_pickup → picked_up)
Mitigaciones
Documentar en la API que los endpoints nuevos son recomendados pero los legacy siguen funcionando
Métricas de uso: después de 3-6 meses, si nadie usa served para takeout, deprecar el camino legacy
Logs de auditoría: registrar qué endpoint se usó para cada transición
Alternativas Consideradas y Descartadas
Alternativa 1: Ruptura limpia (flag day)
READY → SERVED solo en onsite (bloqueado en pickup/delivery)
Descartada porque:
Rompería 10+ tests existentes
Requiere coordinar actualización con todos los clientes API
No hay mecanismo actual de versionado de API
Alternativa 2: Versionado de API
POST /api/v1/orders/{uuid}/serve (legacy, funciona siempre)
POST /api/v2/orders/{uuid}/serve (nuevo, estricto por canal)
Descartada porque:
No tenemos infraestructura de versionado de API
Duplicaría controllers y rutas
Complejidad desproporcionada para el cambio
Alternativa 3: Feature flag por empresa
if ($company->hasFeature('strict_fulfillment_transitions')) { ... }
Descartada porque:
Complejidad operacional (migrar cada empresa manualmente)
No resuelve el problema de tests existentes
Over-engineering para el alcance actual
Recomendaciones para el Futuro
Si se necesita deprecar el camino legacy:
Monitorear uso: logs de qué endpoints se usan por canal
Anunciar deprecación: marcar SERVED como deprecado para takeout/delivery en OpenAPI docs
Período de gracia: 6 meses con warnings en logs
Remover: solo cuando no haya uso en producción
Para nuevos tipos de fulfillment (ej: curbside):
Agregar nuevos estados específicos sin tocar el legacy
Seguir el patrón: nuevo estado canónico + legacy preservado
Mantener allowedTransitionsFor() como fuente única de verdad
Implementación
Archivos modificados:
app/Modules/Orders/Domain/ValueObjects/OrderStatus.php (allowedTransitionsFor)
app/Modules/Orders/Domain/Services/OrderStateMachine.php (assertCanTransitionForOrder)
app/Modules/Orders/Interfaces/Controllers/OrderTransitionController.php (nuevos endpoints)
app/Modules/Orders/Domain/Policies/OrderPolicy.php (nuevos métodos: readyForPickup, pickup, dispatch, deliver)
Tests:
11 tests en OrderFulfillmentTransitionsTest.php:
2 flujos completos (pickup y delivery)
3 validaciones de transiciones inválidas por canal
2 tests de backward compatibility (legacy ready → served en pickup y delivery)
4 tests unitarios del enum
Relacionado
ADR-001: modelo de fulfillment
ADR-003: autorización por rol para cada transición
