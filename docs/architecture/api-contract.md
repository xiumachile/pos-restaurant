# Contrato de API - POS Restaurante

**Fecha**: Septiembre 2026  
**Versión**: 1.0.0  
**Estado**: Congelado (Contract Freeze)  
**Documentación automática**: [Scramble](/docs/api)

## Resumen Ejecutivo

El sistema expone **198 endpoints** organizados en 15 módulos. La documentación se genera automáticamente con **Scramble** desde código fuente (PHPDoc + validación de Laravel).

**Formato único de errores**: Todos los endpoints siguen el mismo formato de respuesta de error.

## Convenciones Globales

### Autenticación

Todos los endpoints requieren autenticación JWT (excepto `/auth/login` y `/auth/register`).

```http
Authorization: Bearer <jwt_token>
Headers Obligatorios
Header	Requerido	Descripción
Authorization	✅ Sí	Token JWT (excepto login/register)
Accept	❌ No	application/json (default)
Accept-Language	❌ No	es-CL o zh-CN (default: es-CL)
X-Terminal-ID	❌ No	UUID de terminal (para contexto POS/KDS)
Idempotency-Key	⚠️ Condicional	Requerido en mutaciones críticas (POST/PUT/DELETE)
Mutaciones Críticas (Idempotencia)
Los siguientes endpoints requieren Idempotency-Key:
Endpoint	Método	Razón
/api/v1/payments	POST	Previene doble cobro
/api/v1/orders/{uuid}/split	POST	Previene doble split
/api/v1/cashier/sessions	POST	Previene doble apertura
/api/v1/cashier/sessions/{uuid}/close	POST	Previene doble cierre
/api/v1/refunds	POST	Previene doble reembolso

Formato: UUID v4
Idempotency-Key: 550e8400-e29b-41d4-a716-446655440000

Paginación
Todos los endpoints GET que retornan listas soportan paginación:
GET /api/v1/orders?page=1&per_page=20
Respuesta:
{
  "data": [...],
  "meta": {
    "current_page": 1,
    "per_page": 20,
    "total": 150,
    "last_page": 8
  }
}
Filtros
Los endpoints GET soportan filtros vía query params:
GET /api/v1/orders?status=paid&branch_id=1&date_from=2026-09-01
Formato Único de Errores
Todos los endpoints siguen este formato de respuesta de error:
Estructura Base
{
  "error": "error_code",
  "message": "Mensaje descriptivo para humanos",
  "details": {
    "campo": ["Error específico del campo"]
  }
}

Campos:
error (string): Código de error máquina (snake_case)
message (string): Mensaje descriptivo para humanos
details (object, opcional): Errores de validación por campo
Códigos de Error Estándar
401 Unauthorized
Error Code          Descripción                  Cuándo ocurre
unauthenticated Token inválido o ausente    No hay token JWT o está expirado

Ejemplo:
{
  "error": "unauthenticated",
  "message": "Token de autenticación inválido o ausente."
}

403 Forbidden
Error Code	Descripción	Cuándo ocurre
forbidden	Sin permisos de rol	Usuario no tiene rol requerido
capability_not_enabled	Funcionalidad deshabilitada	Empresa no tiene capability habilitado
company_not_found	Sin empresa asociada	Usuario no está asociado a empresa

Ejemplo (forbidden):
{
  "error": "forbidden",
  "message": "No tienes permisos para realizar esta acción.",
  "required_roles": ["admin", "manager"],
  "current_role": "waiter"
}

Ejemplo (capability_not_enabled):
{
  "error": "capability_not_enabled",
  "message": "Esta funcionalidad no está habilitada para tu empresa.",
  "required_capability": "can_split_bills"
}

404 Not Found
Error Code	Descripción	Cuándo ocurre
not_found	Recurso no existe	UUID/ID no encontrado
order_not_found	Orden no existe	Order UUID inválido
bill_not_found	Bill no existe	Bill UUID inválido

Ejemplo:
{
  "error": "order_not_found",
  "message": "La orden solicitada no existe."
}

409 Conflict
Error Code	Descripción	Cuándo ocurre
idempotency_conflict	Misma key, diferente payload	Idempotency-Key reutilizada con datos diferentes
duplicate_resource	Recurso duplicado	UUID/ID ya existe

Ejemplo:
{
  "error": "idempotency_conflict",
  "message": "Esta clave de idempotencia ya fue utilizada con diferentes datos."
}

422 Unprocessable Entity
Error Code	Descripción	Cuándo ocurre
validation_error	Error de validación	Datos no cumplen reglas
invalid_status_transition	Transición inválida	Cambio de estado no permitido
insufficient_funds	Fondos insuficientes	Pago excede monto disponible
insufficient_stock	Stock insuficiente	No hay inventario disponible
cash_session_required	Sesión de caja requerida	No hay sesión de caja abierta
session_open_failed	Apertura de sesión falló	Ya hay sesión abierta
session_close_failed	Cierre de sesión falló	Sesión no está abierta
payment_failed	Pago falló	Error en procesamiento de pago

Ejemplo (validation_error):
{
  "error": "validation_error",
  "message": "Los datos enviados no son válidos.",
  "details": {
    "amount": ["El monto debe ser mayor a 0."],
    "payment_method_id": ["El método de pago no existe."]
  }
}

Ejemplo (insufficient_funds):
{
  "error": "insufficient_funds",
  "message": "El monto del pago excede el monto disponible.",
  "available": 5000.00,
  "requested": 10000.00
}

500 Internal Server Error
Error Code	Descripción	Cuándo ocurre
internal_error	Error interno	Excepción no controlada

Ejemplo:
{
  "error": "internal_error",
  "message": "Ocurrió un error interno. Por favor intente nuevamente."
}

Código de Error vs Mensaje
Regla: El frontend NUNCA debe depender del campo message para lógica de negocio.
Correcto ✅:

if (response.data.error === 'insufficient_funds') {
  showError('Fondos insuficientes');
}

Incorrecto ❌:
if (response.data.message.includes('fondos')) {
  showError('Fondos insuficientes');
}

Justificación: Los mensajes pueden cambiar (traducciones, mejoras de UX), pero los códigos de error son estables.
Capabilities por Empresa
Las empresas pueden habilitar/deshabilitar funcionalidades específicas.
Capabilities Disponibles

Capability Key	Descripción	Endpoints Afectados
can_split_bills	Dividir cuentas	POST /api/v1/orders/{uuid}/split
can_manage_inventory	Gestionar inventario	Todos los endpoints de /api/v1/inventory
requires_cashier_session	Requiere sesión de caja	POST /api/v1/payments
can_accept_tips	Aceptar propinas	POST /api/v1/payments (campo tip_amount)
has_kitchen_display	Kitchen Display System	Eventos WebSocket de cocina
can_print_receipts	Imprimir recibos	POST /api/v1/print-jobs
supports_loyalty_program	Programa de lealtad	Endpoints de lealtad (futuro)
can_manage_reservations	Gestionar reservaciones	Endpoints de reservaciones (futuro)

Validación de Capabilities
Los endpoints que requieren capability específico retornan 403 capability_not_enabled si la empresa no lo tiene habilitado.
Ejemplo:
POST /api/v1/orders/{uuid}/split
Authorization: Bearer <token>

Respuesta (si capability deshabilitado):
{
  "error": "capability_not_enabled",
  "message": "Esta funcionalidad no está habilitada para tu empresa.",
  "required_capability": "can_split_bills"
}

Status Code: 403 Forbidden
Eventos WebSocket (Reverb)
El sistema emite eventos en tiempo real vía WebSocket (Laravel Reverb).
Canales Disponibles

Canal	Autorización	Descripción
kitchen.{branchId}	kitchen, admin, manager de la sucursal	Eventos de cocina (KDS)
waiters.{branchId}	waiter, admin, manager de la sucursal	Eventos de meseros
dashboard.{companyId}	admin, manager de la empresa	Eventos de dashboard

Eventos de Cocina
Evento	Canal	Descripción	Datos
order.confirmed	kitchen.{branchId}	Orden confirmada	Order UUID, items, mesa, mesero
order.paid	kitchen.{branchId}	Orden pagada	Order UUID, payment method
order.ready	kitchen.{branchId}	Orden lista	Order UUID, ready_at
order.cancelled	kitchen.{branchId}	Orden cancelada	Order UUID, reason

Ejemplo de payload:
{
  "event": "order.confirmed",
  "order_uuid": "550e8400-e29b-41d4-a716-446655440000",
  "order_number": "ORD-001",
  "type": "dine_in",
  "status": "confirmed",
  "table": {
    "uuid": "...",
    "table_number": "A1",
    "area_code": "MAIN"
  },
  "waiter": {
    "uuid": "...",
    "name": "Juan Pérez"
  },
  "items_count": 3,
  "notes": "Sin cebolla",
  "confirmed_at": "2026-09-16T10:30:00Z"
}

Suscripción desde Frontend
import Echo from 'laravel-echo';
import Pusher from 'pusher-js';

const echo = new Echo({
  broadcaster: 'reverb',
  key: import.meta.env.VITE_REVERB_APP_KEY,
  wsHost: import.meta.env.VITE_REVERB_HOST,
  wsPort: import.meta.env.VITE_REVERB_PORT,
  forceTLS: false,
});

// Suscribirse a canal de cocina
echo.private(`kitchen.${branchId}`)
  .listen('.order.confirmed', (data) => {
    console.log('Orden confirmada:', data);
  })
  .listen('.order.paid', (data) => {
    console.log('Orden pagada:', data);
  });

Endpoints Críticos
Autenticación
Endpoint	Método	Descripción	Auth
/api/v1/auth/login	POST	Login con email/password	❌ No
/api/v1/auth/login/pin	POST	Login con PIN POS	❌ No
/api/v1/auth/refresh	POST	Refrescar token JWT	✅ Sí
/api/v1/auth/logout	POST	Logout	✅ Sí

Órdenes
Endpoint	Método	Descripción	Auth	Capability
/api/v1/orders	GET	Listar órdenes	✅	-
/api/v1/orders	POST	Crear orden	✅	-
/api/v1/orders/{uuid}	GET	Ver orden	✅	-
/api/v1/orders/{uuid}	PUT	Actualizar orden	✅	-
/api/v1/orders/{uuid}/confirm	POST	Confirmar orden	✅	-
/api/v1/orders/{uuid}/split	POST	Dividir cuenta	✅	can_split_bills

Pagos
Endpoint	Método	Descripción	Auth	Capability	Idempotency
/api/v1/payments	POST	Registrar pago	✅	requires_cashier_session*	✅ Sí
/api/v1/billing/payments	POST	Pagar bill	✅	requires_cashier_session*	✅ Sí

*Si capability está habilitado
Caja
Endpoint	Método	Descripción	Auth	Idempotency
/api/v1/cashier/sessions	POST	Abrir sesión	✅	✅ Sí
/api/v1/cashier/sessions/{uuid}/close	POST	Cerrar sesión	✅	✅ Sí
/api/v1/cashier/movements	POST	Movimiento de caja	✅	✅ Sí

Sincronización
Endpoint	Método	Descripción	Auth
/api/v1/sync/push	POST	Enviar cambios locales	✅
/api/v1/sync/pull	POST	Descargar cambios	✅
/api/v1/sync/status	GET	Estado de sync	✅

Documentación Automática (Scramble)
El sistema usa Scramble para generar documentación OpenAPI automáticamente desde código fuente.
Acceso
UI interactiva: GET /docs/api
JSON OpenAPI: GET /docs/api.json
Cómo Contribuir
Para documentar un endpoint:
Agregar PHPDoc al método del controller:
/**
 * Crear una nueva orden.
 *
 * @param CreateOrderRequest $request
 * @return \Illuminate\Http\JsonResponse
 *
 * @response 201 scenario="Success" {"data": {"uuid": "...", "order_number": "ORD-001"}}
 * @response 422 scenario="Validation error" {"error": "validation_error", "message": "...", "details": {...}}
 */
public function store(CreateOrderRequest $request)
{
    // ...
}

Definir reglas de validación en Form Request:
class CreateOrderRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'table_id' => ['required', 'exists:tables,id'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.product_id' => ['required', 'exists:products,id'],
            'items.*.quantity' => ['required', 'integer', 'min:1'],
        ];
    }
}

Scramble extrae automáticamente:
Parámetros de ruta
Query params
Request body (desde Form Request)
Response schema (desde @response tags)
Status codes
Errores de validación
Criterio de Cierre
"El frontend puede desarrollarse contra contratos estables."
Validación
Estado: ✅ CUMPLIDO
Justificación:
✅ Formato único de errores: Todos los endpoints siguen la misma estructura
✅ Códigos de error estables: Frontend puede depender de error field, no de message
✅ Capabilities documentadas: Frontend sabe qué endpoints pueden retornar 403 capability_not_enabled
✅ Eventos WebSocket documentados: Frontend sabe qué eventos escuchar y en qué canales
✅ Documentación automática: Scramble genera OpenAPI desde código (siempre actualizada)
✅ Idempotencia documentada: Frontend sabe qué endpoints requieren Idempotency-Key
Garantías para Frontend

Garantía	Mecanismo	Validación
Formato de errores estable	error field es código máquina	✅ Documentado
Capabilities predecibles	403 con required_capability	✅ Documentado
Eventos WebSocket estables	Canales y eventos documentados	✅ Documentado
Idempotencia clara	Header Idempotency-Key requerido	✅ Documentado
Documentación actualizada	Scramble genera desde código	✅ Automático

Limitaciones Conocidas
1. openapi.yaml está vacío
Estado: Limitación aceptada (no necesario).
Justificación:
Scramble genera documentación automáticamente en /docs/api.json
No hay necesidad de mantener openapi.yaml manualmente
openapi.yaml solo tiene metadata base (info, servers, security schemes)
Impacto: Ninguno. Frontend usa documentación generada por Scramble.
2. Algunos endpoints no tienen PHPDoc completo
Estado: Deuda técnica menor.
Justificación:
Scramble puede inferir schema desde Form Requests
PHPDoc mejora documentación pero no es obligatorio
Se puede mejorar incrementalmente
Impacto: Documentación menos detallada en algunos endpoints.
Mitigación: Agregar PHPDoc a endpoints críticos (pagos, billing, sync).

Conclusión
Estado actual: Contrato de API congelado y documentado.
Garantías:
✅ Formato único de errores
✅ Códigos de error estables
✅ Capabilities documentadas
✅ Eventos WebSocket documentados
✅ Documentación automática (Scramble)
Criterio de cierre: ✅ CUMPLIDO
Próximo paso: Frontend puede desarrollarse contra contratos estables.

## POST /api/v1/bills (ADR-020)

Crea una bill en el backend. Usado para sincronizar bills creadas offline, incluyendo split bills.

**Autenticación**: Requerida (Bearer token)  
**Idempotencia**: Requerida (header `Idempotency-Key`)

### Request Body
```json
{
  "order_id": "uuid",
  "subtotal": 10000,
  "tax_amount": 1900,
  "tip_amount": 500,
  "total": 12400,
  "items": ["item_uuid_1", "item_uuid_2"],
  "type": "split" | "single",
  "status": "open" | "paid" | "cancelled"
}

Response (201 Created)

{
  "id": "uuid",
  "order_id": "uuid",
  "subtotal": 10000,
  "tax_amount": 1900,
  "tip_amount": 500,
  "total": 12400,
  "items": ["item_uuid_1", "item_uuid_2"],
  "type": "split",
  "status": "open",
  "created_at": "2026-01-21T12:00:00Z"
}

Casos de Uso
	Split bill offline: Frontend crea múltiples bills offline, sincroniza al backend vía SyncEngine
	Bill única: Frontend crea bill offline, sincroniza al backend
Validaciones
	order_id debe existir
	subtotal + tax_amount + tip_amount debe igualar total
	items deben pertenecer al order
Errores
	400 Bad Request: Validaciones fallidas
	401 Unauthorized: Sin autenticación
	409 Conflict: Idempotency-Key duplicado (retorna bill existente)
Referencia: ADR-020 (Bills sincronizables)

