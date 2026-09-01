# ADR-003: Patrón de Policies DDD (Order/Company/CashSession)

**Estado**: ✅ Aceptado  
**Fecha**: 2026-08-31  
**Autores**: Equipo de desarrollo

## Contexto

El proyecto tenía inconsistencias en autorización:

1. **Validaciones duplicadas inline**: `CompanyController` tenía la misma lógica
   `$user->role !== 'super_admin' && $company->id !== $user->company_id` duplicada
   en 5 métodos. Esto causó el **bug financiero #15** cuando se editaron 4 de 5 copias.

2. **Métodos sin autorización**: `CashSessionController::open()` y `current()`
   no validaban roles ni `branch_id`. Cualquier usuario autenticado podía cerrar
   sesiones de OTRAS sucursales de la misma empresa.

3. **Ausencia de policy**: `CompanyPolicy` no existía, aunque era el lugar natural
   según el patrón Laravel/DDD.

Necesitábamos un patrón **consistente y reutilizable** para todas las entidades del dominio.

## Decisión

Adoptamos el **patrón Laravel Policy con defensa en profundidad DDD**:

### Estructura del policy

```php
class EntityPolicy
{
    // 1. Defensa en profundidad: validación de tenant SIEMPRE primero
    private function belongsToUserCompany(User $user, Entity $entity): bool
    {
        return $entity->company_id === $user->company_id;
    }

    // 2. Autorización por rol + propiedad específica del caso
    public function someAction(User $user, Entity $entity): bool
    {
        if (!$this->belongsToUserCompany($user, $entity)) {
            return false;  // Cross-tenant bloqueado
        }

        if (in_array($user->role, ['admin', 'manager'])) {
            return true;   // Admins tienen acceso total dentro de su empresa
        }

        if ($user->role === 'waiter') {
            return $entity->waiter_id === $user->id;  // Propietario del pedido
        }

        return false;
    }
}
Tres capas de defensa (defense in depth)
┌─────────────────────────────────────────────────────┐
│ Capa 1: CompanyScope (scope global de Eloquent)     │
│   → todas las queries automáticas filtran company_id│
└─────────────────────────────────────────────────────┘
              ↓
┌─────────────────────────────────────────────────────┐
│ Capa 2: Policy (autorización explícita)             │
│   → valida rol + propiedad + reglas de dominio      │
└─────────────────────────────────────────────────────┘
              ↓
┌─────────────────────────────────────────────────────┐
│ Capa 3: Filtros en query del controller             │
│   → where('company_id', ...)->where('branch_id', ..)│
│   → incluso si capas 1-2 fallan, no hay fuga        │
└─────────────────────────────────────────────────────┘
Registro centralizado en AuthServiceProvider
protected $policies = [
    Order::class => OrderPolicy::class,
    Company::class => CompanyPolicy::class,
    CashSession::class => CashSessionPolicy::class,
];
Uso en controllers
public function someAction(Request $request, string $uuid): JsonResponse
{
    $entity = Entity::where('uuid', $uuid)
        ->where('company_id', $request->user()->company_id)   // capa 3
        ->where('branch_id', $request->user()->branch_id)     // capa 3 bis
        ->firstOrFail();

    $this->authorize('someAction', $entity);                  // capa 2

    // ... lógica de negocio
}
Autorización a nivel de clase (sin instancia)
Para acciones que no requieren una entidad específica (ej: crear):
public function create(Request $request): JsonResponse
{
    $this->authorize('create', Entity::class);   // clase, no instancia

    // ... lógica
}
// En el policy:
public function create(User $user): bool
{
    return in_array($user->role, ['admin', 'manager']);
}
Consecuencias
Positivas ✅
Un único lugar para cambiar reglas de autorización
Defensa en profundidad: 3 capas independientes previenen fugas
Cross-tenant isolation garantizada por belongsToUserCompany()
Cross-branch isolation cuando aplica (ej: cajeros)
Tests de autorización separados de tests de lógica de negocio
Coherencia con Laravel: usa el patrón nativo, familiar para nuevos devs
Negativas ⚠️
Más archivos: un policy por entidad (pero es organización limpia)
Curva de aprendizaje: devs nuevos deben entender el patrón
Riesgo de olvidarse de registrar en AuthServiceProvider
Mitigaciones
Tests obligatorios por cada policy (coverage de todos los métodos)
Convención de nombres: EntityPolicy para Entity
Documentación inline en cada método explicando la regla
Policies Implementados
OrderPolicy (el más completo)
Método
admin/manager
waiter (dueño)
kitchen
cashier
view
✅
✅
si está en kitchen queue
si espera pago
update
✅
✅
❌
❌
confirm
✅
✅
❌
✅
prepare
✅
❌
✅
❌
ready
✅
❌
✅
❌
readyForPickup
✅
❌
✅
❌
pickup
✅
✅
❌
✅
dispatch
✅
❌
✅
❌
deliver
✅
✅
❌
❌
serve
✅
✅
❌
❌
pay
✅
❌
❌
✅
close
✅
❌
❌
✅
cancel
✅ (siempre)
✅ (draft propio)
❌
❌
CompanyPolicy (simple, para super_admin)
Método
super_admin
admin
Otros roles
view
✅ cualquier empresa
✅ su empresa
❌
update
✅ cualquier empresa
✅ su empresa
❌
updateCapabilities
✅
❌
❌
CashSessionPolicy (roles de caja + branch isolation)
Método
cashier/admin/manager
waiter/kitchen
open
✅ (en su branch)
❌
close
✅ (solo sesiones de su branch)
❌
viewCurrent
✅
✅ (cualquier rol operativo)
Convenciones Establecidas
Nombres de métodos del policy
Verbos de acción: view, update, delete, confirm, pay, etc.
Coherencia con controller: si el controller tiene public function confirm(),
el policy debe tener public function confirm(User $user, Entity $entity)
Registro obligatorio
Todo nuevo policy debe agregarse a AuthServiceProvider::$policies.
Método de defensa en profundidad
Todos los policies deben tener un método privado belongsToUserCompany() como primera
validación en cada método público.
Implementación
Archivos involucrados:
app/Modules/Orders/Domain/Policies/OrderPolicy.php
app/Modules/Companies/Domain/Policies/CompanyPolicy.php
app/Modules/Payments/Domain/Policies/CashSessionPolicy.php
app/Providers/AuthServiceProvider.php (registro)
Tests:
tests/Feature/OrderPolicyTest.php (14 tests)
tests/Feature/CompanyPolicyTest.php (8 tests)
tests/Feature/CashSessionPolicyTest.php (8 tests)
Decisiones Futuras
Si se agrega un nuevo rol (ej: delivery_driver)
Agregar rol al sistema de usuarios
Actualizar los policies relevantes (ej: OrderPolicy::deliver())
Agregar tests para el nuevo rol
Actualizar documentación en este ADR
Si se agrega una nueva entidad con autorización
Crear EntityPolicy siguiendo el patrón
Registrar en AuthServiceProvider::$policies
Agregar tests de coverage completo
Documentar en este ADR la tabla de permisos
Relacionado
ADR-002: policies específicos para transiciones
Commit d8673db: CompanyPolicy creado
Commit cbe37c5: CashSessionPolicy creado
