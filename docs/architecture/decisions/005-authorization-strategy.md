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

## Consecuencias

### Positivas
✅ Zero migración innecesaria  
✅ Roles simples siguen funcionando  
✅ Spatie disponible cuando se necesite  
✅ Frontend puede usar `role` string por ahora

### Negativas
⚠️ No hay permisos granulares todavía (limita UI condicional)  
⚠️ Dos sistemas de autorización coexisten (pero solo uno activo)

## Alternativas Descartadas

❌ **Migrar completamente a Spatie ahora**: Alto costo, zero valor inmediato  
❌ **Eliminar Spatie del composer**: Pierde flexibilidad futura  
❌ **Implementar sistema custom de permisos**: Reinventar la rueda

## Criterio de Activación

Activar Spatie cuando el frontend necesite:
- Botones condicionales por permiso granular (no solo por rol)
- Auditoría detallada de permisos por usuario
- Roles personalizados por empresa/branch

---

**Decisión tomada por**: Arquitecto + Desarrollador  
**Fecha**: 01 Septiembre 2026
