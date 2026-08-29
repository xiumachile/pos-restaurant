# ADR-004: Defensa en Profundidad para Aislamiento Multi-Tenant

## Status
Aceptado (2026-08-30)

## Contexto
Durante S7 descubrimos que confiar únicamente en el scope global `BelongsToTenant`
para aislamiento multi-tenant es insuficiente. Aunque el scope funciona correctamente,
depende de que ningún desarrollador use `withoutGlobalScopes()` accidentalmente,
y no proporciona visibilidad explícita en el código del controller.

## Decisión
Aplicar **defensa en profundidad** con 3 capas:

1. **Middleware TenantContextMiddleware**: Establece company_id desde JWT
2. **Scope global BelongsToTenant**: Filtra queries automáticamente
3. **Filtros explícitos en controllers**: `->where('company_id', $user->company_id)`

## Patrones Aplicados

### Antes (vulnerable):
```php
$entity = Entity::where('uuid', $uuid)->firstOrFail();
$entity = Entity::where('uuid', $uuid)
    ->where('company_id', $request->user()->company_id)
    ->firstOrFail();
