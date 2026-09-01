# ADR-001: Modelo de Fulfillment (type vs fulfillment_channel)

**Estado**: ✅ Aceptado  
**Fecha**: 2026-08-31  
**Autores**: Equipo de desarrollo  
**Reemplaza a**: (ninguno — primera decisión documentada)

## Contexto

El sistema originalmente solo soportaba pedidos `dine_in` (comer en el local con mesa).
Para habilitar casos de uso como fast food, cafeterías, dark kitchens y delivery propio,
se necesitaba modelar pedidos sin mesa.

La pregunta clave era: ¿cómo representar la diferencia entre:
- **Qué tipo de pedido es** (dine_in, takeout, delivery) — aspecto contractual/comercial
- **Cómo se entrega al cliente** (onsite, pickup, delivery) — aspecto operativo/logístico

Opciones consideradas:
1. Un solo enum que combine ambos conceptos
2. Dos campos separados (`type` + `fulfillment_channel`)
3. Campos ad-hoc por tipo (ej: `delivery_address` solo para delivery)

## Decisión

Elegimos la **opción 2: dos enums separados** con relación canónica pero no rígida.

### OrderType (qué tipo de pedido)

```php
enum OrderType: string
{
    case DINE_IN = 'dine_in';   // Come en el local
    case TAKEOUT = 'takeout';   // Para llevar
    case DELIVERY = 'delivery'; // Entrega a domicilio
}
Semántica contractual:
Define qué obligaciones tiene el restaurant con el cliente
DINE_IN requiere mesa asignada
TAKEOUT y DELIVERY prohíben mesa
FulfillmentChannel (cómo se entrega)
enum FulfillmentChannel: string
{
    case ONSITE = 'onsite';     // Cliente consume en el local
    case PICKUP = 'pickup';     // Cliente retira en el local
    case DELIVERY = 'delivery'; // Entrega a domicilio
}
Semántica operativa:
Define cómo se cumple el contrato
Controla qué estados de OrderStatus son válidos
Determina qué timestamps de auditoría se setean
Relación canónica (defaults)
OrderType::DINE_IN   → FulfillmentChannel::ONSITE   (default)
OrderType::TAKEOUT   → FulfillmentChannel::PICKUP   (default)
OrderType::DELIVERY  → FulfillmentChannel::DELIVERY (default)
Relación flexible (overrides permitidos)
DINE_IN puede sobreescribirse a PICKUP (edge: cliente pide en mesa pero lleva)
TAKEOUT puede sobreescribirse a ONSITE (edge: cliente pide para llevar pero se queda)
DELIVERY solo puede ser DELIVERY (validado en CreateOrderRequest)
Consecuencias
Positivas ✅
Separación clara de concerns: el contrato comercial es independiente de la ejecución
Flexibilidad para casos edge: clientes que cambian de opinión
Validación en dos capas: request (defensa externa) + domain service (defensa interna)
Extensible: se pueden agregar más tipos sin tocar fulfillment, y viceversa
Backfill simple: pedidos existentes con type se migran a fulfillment_channel automáticamente
Negativas ⚠️
Complejidad adicional: dos enums en lugar de uno
Validación cruzada: hay que validar combinaciones válidas (delivery+onsite es inconsistente)
Documentación requerida: los desarrolladores deben entender la diferencia
Mitigaciones
Método OrderType::defaultFulfillmentChannel() encapsula el mapeo canónico
Validación de combinaciones inválidas en CreateOrderRequest::withValidator()
Tests explícitos de cada combinación válida/inválida
Casos de Uso Habilitados
Caso
Type
Channel
Ejemplo
Restaurant tradicional
dine_in
onsite
Mesa 5, 4 personas
Fast food counter
takeout
onsite
Pide y come en barra
Cafetería pickup
takeout
pickup
Pide por app, retira después
Delivery propio
delivery
delivery
Repartidor entrega a domicilio
Dark kitchen
delivery
delivery
100% delivery, sin local
Food truck
takeout
pickup
Cliente retira en ventanilla
Cliente cambia de opinión
dine_in → override pickup
Pidió mesa pero se lleva
Implementación
Archivos involucrados:
app/Modules/Orders/Domain/ValueObjects/OrderType.php
app/Modules/Orders/Domain/ValueObjects/FulfillmentChannel.php
app/Modules/Orders/Domain/Entities/Order.php (fillable + casts)
database/migrations/2026_08_31_100000_add_fulfillment_channel_to_orders_table.php
app/Modules/Orders/Domain/Services/OrderService.php (lógica de default)
Migración con backfill:
ALTER TABLE orders ADD COLUMN fulfillment_channel VARCHAR;
UPDATE orders SET fulfillment_channel = 'onsite'   WHERE type = 'dine_in';
UPDATE orders SET fulfillment_channel = 'pickup'   WHERE type = 'takeout';
UPDATE orders SET fulfillment_channel = 'delivery' WHERE type = 'delivery';
ALTER TABLE orders ALTER COLUMN fulfillment_channel SET NOT NULL;
ALTER TABLE orders ALTER COLUMN fulfillment_channel SET DEFAULT 'onsite';
Relacionado
ADR-002: cómo este modelo afecta las transiciones
docs/modules/fulfillment.md: guía operativa completa
