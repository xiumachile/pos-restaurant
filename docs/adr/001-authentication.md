# ADR-001: Arquitectura de Autenticación JWT

**Status:** Accepted
**Date:** 2026-08-28
**Deciders:** Equipo de Desarrollo
**Sprint:** S1 - Consolidación de Seguridad

---

## Context

El sistema POS Restaurant requiere autenticación para múltiples contextos:

1. **POS/Tauri**: Cliente desktop offline-first que sincroniza con el servidor
2. **Backoffice/API**: Aplicación web para administración
3. **Broadcasting**: Canales WebSocket para actualizaciones en tiempo real
4. **Servicios internos**: Jobs, Commands, Listeners que procesan datos

Necesitamos decidir:
- ¿Qué mecanismo de autenticación usar para cada contexto?
- ¿Cómo establecer el contexto de tenant (company_id, branch_id)?
- ¿Cómo evitar ambigüedad entre JWT y Sanctum?

---

## Decision

**Usar JWT (PHPOpenSourceSaver/jwt-auth) como mecanismo único de autenticación para todos los contextos expuestos.**

### Configuración

```php
// config/auth.php
'guards' => [
    'api' => [
        'driver' => 'jwt',
        'provider' => 'users',
    ],
],
