# ADR-005: Sistema de Capabilities para Flexibilidad Multi-Restaurante

## Status
Aceptado (2026-08-30)

## Contexto
El POS necesita venderse a distintos tipos de restaurante (fine dining, fast casual,
cafeterías, food trucks, etc.). Cada tipo de restaurante requiere funcionalidades
diferentes:

- Fine dining: ✅ split bills, ✅ tips, ✅ reservaciones, ✅ kitchen display
- Fast casual: ❌ split bills, ❌ tips, ✅ inventario, ❌ reservaciones
- Cafetería: ❌ split bills, ✅ tips, ❌ kitchen display, ❌ inventario
- Food truck: ❌ split bills, ❌ tips, ❌ inventario, ✅ impresión de recibos

Sin un sistema de capabilities, todas las empresas tienen acceso a todas las
funcionalidades, lo que genera:
- Interfaces sobrecargadas con opciones irrelevantes
- Imposibilidad de ofrecer planes de precios diferenciados
- Riesgo de que usuarios accedan a funcionalidades no contratadas

Después de 7 sprints enfocados en seguridad multi-tenant y calidad de código,
el módulo Companies seguía sin controller, sin rutas y sin API. Era la pieza
de mayor apalancamiento para el objetivo real del negocio.

## Decisión
Implementar un sistema de capabilities por empresa con 3 componentes:

### 1. Modelo de Datos
```sql
CREATE TABLE company_capabilities (
    id BIGSERIAL PRIMARY KEY,
    company_id BIGINT NOT NULL REFERENCES companies(id) ON DELETE CASCADE,
    capability_key VARCHAR(50) NOT NULL,
    is_enabled BOOLEAN DEFAULT true,
    settings JSONB DEFAULT '{}',
    UNIQUE(company_id, capability_key)
);
enum CapabilityKey: string
{
    case CAN_SPLIT_BILLS = 'can_split_bills';
    case CAN_MANAGE_INVENTORY = 'can_manage_inventory';
    case REQUIRES_CASHIER_SESSION = 'requires_cashier_session';
    case CAN_ACCEPT_TIPS = 'can_accept_tips';
    case HAS_KITCHEN_DISPLAY = 'has_kitchen_display';
    case CAN_PRINT_RECEIPTS = 'can_print_receipts';
    case SUPPORTS_LOYALTY_PROGRAM = 'supports_loyalty_program';
    case CAN_MANAGE_RESERVATIONS = 'can_manage_reservations';
}
// Uso en rutas:
Route::middleware('capability:can_split_bills')
    ->post('/orders/{uuid}/split', [BillController::class, 'split']);
case CAN_MANAGE_DELIVERY = 'can_manage_delivery';
self::CAN_MANAGE_DELIVERY => 'Gestión de delivery',
Route::middleware('capability:can_manage_delivery')
    ->get('/deliveries', [DeliveryController::class, 'index']);
# API: Habilitar capabilities específicas
PUT /api/v1/companies/{uuid}/capabilities
{
    "capabilities": [
        {"key": "can_split_bills", "is_enabled": true, "settings": {"max_parts": 10}},
        {"key": "can_accept_tips", "is_enabled": false},
        {"key": "can_manage_inventory", "is_enabled": true}
    ]
}

# API: Consultar capabilities
GET /api/v1/companies/{uuid}/capabilities
// En un controller o service
if ($user->company->hasCapability('can_split_bills')) {
    // Lógica de split bills
}

// Con enum (type-safe)
if ($user->company->hasCapability(CapabilityKey::CAN_SPLIT_BILLS)) {
    // Lógica de split bills
}
