
# ADR-005: Estrategia de Autorización (Spatie vs Roles Simples)

**Fecha**: 01 Septiembre 2026  
**Estado**: ✅ Aceptado  
**Contexto**: Pre-Frontend Gate — Decisión #5

## Contexto

El proyecto tiene `spatie/laravel-permission ^8.3` instalado en `composer.json`,
pero el código utiliza roles simples (campo `role` string en tabla `users` con
middleware `CheckRole` que compara strings). El frontend necesita permisos
granulares para habilitar/deshabilitar botones por rol.

## Decisión

**Mantener Spatie instalado (sin usar activamente) y continuar con roles simples
hasta que el frontend requiera permisos granulares.**

### Razones

1. **Costo de migración alto**: Migrar de `role` string a `spatie_roles` requiere:
   - Cambiar middleware `CheckRole`
   - Migrar datos de usuarios existentes
   - Actualizar policies y tests
   - Refactorizar UI para usar permisos granulares

2. **Overhead mínimo**: Spatie no usado = zero impacto en performance
   - No se ejecutan queries de permisos
   - No hay tablas `spatie_roles` ni `model_has_permissions` creadas
   - No afecta tiempo de respuesta

3. **Flexibilidad futura**: Cuando el frontend requiera permisos granulares:
   - Spatie ya está instalado
   - Migración incremental (no rompe roles existentes)
   - Permisos granulares como `orders.create`, `orders.cancel`, `payments.refund`

### Estrategia de Transición (cuando sea necesaria)

```bash
# Fase 1: Crear roles y permisos Spatie
php artisan make:permission orders.create
php artisan make:permission orders.cancel
php artisan make:permission payments.refund
php artisan make:permission catalog.update

# Fase 2: Asignar permisos a roles existentes
User::where('role', 'admin')->each(fn($u) => $u->givePermissionTo('orders.*'));
User::where('role', 'cashier')->each(fn($u) => $u->givePermissionTo('orders.create'));

# Fase 3: Migrar middleware gradualmente
# CheckRole sigue funcionando, pero usa Spatie como respaldo
Consecuencias
Positivas
✅ Zero migración innecesaria
✅ Roles simples siguen funcionando
✅ Spatie disponible cuando se necesite
✅ Frontend puede usar role string por ahora
Negativas
⚠️ No hay permisos granulares todavía (limita UI condicional)
⚠️ Dos sistemas de autorización coexisten (pero solo uno activo)
Alternativas Descartadas
❌ Migrar completamente a Spatie ahora: Alto costo, zero valor inmediato
❌ Eliminar Spatie del composer: Pierde flexibilidad futura
❌ Implementar sistema custom de permisos: Reinventar la rueda
Criterio de Activación
Activar Spatie cuando el frontend necesite:
Botones condicionales por permiso granular (no solo por rol)
Auditoría detallada de permisos por usuario
Roles personalizados por empresa/branch
Referencias
Package instalado: spatie/laravel-permission ^8.3
Middleware actual: CheckRole (compara $user->role string)
Tabla users: campo role (admin, manager, cashier, waiter, kitchen)
Próxima iteración: Sprint de frontend (Gap 1) evaluará necesidad real
Decisión tomada por: Arquitecto + Desarrollador
Fecha: 01 Septiembre 2026
