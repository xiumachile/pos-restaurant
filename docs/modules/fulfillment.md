# Módulo de Fulfillment — Guía Operativa

Esta guía explica cómo usar el sistema de fulfillment (pedidos con/sin mesa)
en el backend POS Restaurant.

## 📋 Conceptos Clave

### OrderType (qué tipo de pedido)
| Tipo | Descripción | Requiere mesa |
|------|-------------|---------------|
| `dine_in` | Comer en el local | ✅ Sí |
| `takeout` | Para llevar | ❌ No |
| `delivery` | Entrega a domicilio | ❌ No |

### FulfillmentChannel (cómo se entrega)
| Canal | Descripción | Default para |
|-------|-------------|--------------|
| `onsite` | Cliente consume en el local | `dine_in` |
| `pickup` | Cliente retira en el local | `takeout` |
| `delivery` | Entrega a domicilio | `delivery` |

## 🔄 Flujos de Estado por Canal

### ONSITE (dine_in tradicional)
DRAFT → CONFIRMED → PREPARING → READY → SERVED → PAID → CLOSED

**Endpoints**:
POST /orders/{uuid}/confirm
POST /orders/{uuid}/prepare
POST /orders/{uuid}/ready
POST /orders/{uuid}/serve
POST /orders/{uuid}/pay
POST /orders/{uuid}/close

**Timestamps seteados**: `confirmed_at`, `served_at`, `paid_at`, `closed_at`

---

### PICKUP (takeout)
POST /orders/{uuid}/confirm
POST /orders/{uuid}/prepare
POST /orders/{uuid}/ready
POST /orders/{uuid}/ready-for-pickup ← NUEVO
POST /orders/{uuid}/pickup ← NUEVO
POST /orders/{uuid}/pay
POST /orders/{uuid}/close

**Timestamps seteados**: `confirmed_at`, `picked_up_at`, `paid_at`, `closed_at`

---

### DELIVERY
DRAFT → CONFIRMED → PREPARING → READY → DISPATCHED → DELIVERED → PAID → CLOSED

**Endpoints**:
POST /orders/{uuid}/confirm
POST /orders/{uuid}/prepare
POST /orders/{uuid}/ready
POST /orders/{uuid}/dispatch ← NUEVO
POST /orders/{uuid}/deliver ← NUEVO
POST /orders/{uuid}/pay
POST /orders/{uuid}/close

**Timestamps seteados**: `confirmed_at`, `dispatched_at`, `delivered_at`, `paid_at`, `closed_at`

## 📝 Ejemplos de Uso con cURL

### Crear pedido dine_in
```bash
curl -X POST https://api.example.com/api/v1/orders \
  -H "Authorization: Bearer $TOKEN" \
  -H "Content-Type: application/json" \
  -d '{
    "type": "dine_in",
    "table_uuid": "550e8400-e29b-41d4-a716-446655440000"
  }'
Crear pedido takeout (simple)
curl -X POST https://api.example.com/api/v1/orders \
  -H "Authorization: Bearer $TOKEN" \
  -H "Content-Type: application/json" \
  -d '{
    "type": "takeout"
  }'
Crear pedido takeout con pickup programado
curl -X POST https://api.example.com/api/v1/orders \
  -H "Authorization: Bearer $TOKEN" \
  -H "Content-Type: application/json" \
  -d '{
    "type": "takeout",
    "customer_name": "Juan Pérez",
    "customer_phone": "+56912345678",
    "pickup_at": "2026-08-31T13:30:00Z"
  }'
Crear pedido delivery
curl -X POST https://api.example.com/api/v1/orders \
  -H "Authorization: Bearer $TOKEN" \
  -H "Content-Type: application/json" \
  -d '{
    "type": "delivery",
    "customer_name": "María García",
    "customer_phone": "+56987654321",
    "delivery_address": "Av. Providencia 1234, Depto 501",
    "delivery_notes": "Tocar timbre 501"
  }'
Transicionar pedido takeout a ready_for_pickup
curl -X POST https://api.example.com/api/v1/orders/{uuid}/ready-for-pickup \
  -H "Authorization: Bearer $TOKEN"
Transicionar pedido delivery a dispatched
curl -X POST https://api.example.com/api/v1/orders/{uuid}/dispatch \
  -H "Authorization: Bearer $TOKEN"
⚠️ Validaciones
Creación de pedidos
Tipo
Campos requeridos
Campos prohibidos
dine_in
table_uuid
delivery_address, pickup_at
takeout
(ninguno obligatorio)
table_uuid
delivery
customer_name, customer_phone, delivery_address
table_uuid, pickup_at
Override de fulfillment_channel
dine_in puede tener fulfillment_channel=onsite (default) o pickup (edge)
takeout puede tener pickup (default) u onsite (edge)
delivery SOLO puede tener delivery (consistente)
🔐 Autorización por Rol
Acción
admin
manager
waiter (dueño)
kitchen
cashier
Crear pedido
✅
✅
✅
❌
✅
Confirmar
✅
✅
✅
❌
✅
Preparar
✅
✅
❌
✅
❌
Marcar listo
✅
✅
❌
✅
❌
Ready for pickup
✅
✅
❌
✅
❌
Marcar retirado
✅
✅
✅
❌
✅
Despachar
✅
✅
❌
✅
❌
Entregar
✅
✅
✅
❌
❌
Servir
✅
✅
✅
❌
❌
Pagar
✅
✅
❌
❌
✅
Cerrar
✅
✅
❌
❌
✅
Cancelar
✅
✅
✅ (draft propio)
❌
❌
🔄 Backward Compatibility
Flujo legacy READY → SERVED
Sigue siendo válido en cualquier canal. Esto permite que clientes API
existentes sigan funcionando sin cambios.
# Válido para dine_in, takeout, delivery
POST /orders/{uuid}/serve
Recomendación para nuevos clientes: usar los endpoints específicos
(ready-for-pickup, dispatch) para semántica más clara.
📊 Estructura de Respuesta JSON
{
  "data": {
    "uuid": "550e8400-e29b-41d4-a716-446655440000",
    "order_number": "ORD-001-20260831-0001",
    "type": "delivery",
    "type_label": "Delivery",
    "fulfillment_channel": "delivery",
    "fulfillment_channel_label": "Entrega a domicilio",
    "status": "dispatched",
    "customer_name": "María García",
    "customer_phone": "+56987654321",
    "delivery_address": "Av. Providencia 1234, Depto 501",
    "delivery_notes": "Tocar timbre 501",
    "confirmed_at": "2026-08-31T12:00:00Z",
    "dispatched_at": "2026-08-31T12:45:00Z",
    "delivered_at": null,
    "paid_at": null,
    "closed_at": null
  }
}
🔧 Solución de Problemas
Error 422: "Los pedidos delivery requieren dirección"
Causa: Intentar crear delivery sin delivery_address.
Solución: Incluir delivery_address en el request.
Error 422: "Los pedidos takeout no pueden tener mesa"
Causa: Intentar crear takeout con table_uuid.
Solución: Remover table_uuid del request.
Error 422: "Transición inválida de ready a dispatched"
Causa: Intentar despachar un pedido de pickup o dine_in.
Solución: dispatch solo funciona para pedidos con fulfillment_channel=delivery.
Error 403: "No autorizado para despachar"
Causa: Usuario con rol sin permisos (ej: cashier).
Solución: Usar un usuario con rol admin, manager o kitchen.
📚 Documentación Relacionada
ADR-001: modelo conceptual
ADR-002: estrategia de compatibilidad
ADR-003: autorización por rol
