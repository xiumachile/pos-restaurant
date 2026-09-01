# Architecture Decision Records (ADRs)

Este directorio contiene las decisiones arquitectónicas clave del proyecto,
siguiendo el formato [Michael Nygard](https://cognitect.com/blog/2011/11/15/documenting-architecture-decisions).

## ¿Por qué ADRs?

Cada decisión importante queda registrada con:
- **Contexto**: qué problema/decisión enfrentábamos
- **Decisión**: qué elegimos y por qué
- **Consecuencias**: qué implicaciones (positivas y negativas) tiene

Esto permite que futuros desarrolladores (o el mismo equipo meses después)
entiendan el razonamiento detrás de decisiones no obvias.

## Índice

| ADR | Título | Fecha | Estado |
|-----|--------|-------|--------|
| [001](./001-fulfillment-model.md) | Modelo de Fulfillment (type vs fulfillment_channel) | 2026-08-31 | ✅ Aceptado |
| [002](./002-backward-compatible-transitions.md) | Backward Compatibility en Transiciones de Estado | 2026-08-31 | ✅ Aceptado |
| [003](./003-policy-pattern.md) | Patrón de Policies DDD (Order/Company/CashSession) | 2026-08-31 | ✅ Aceptado |

## Convenciones

- **Numeración secuencial**: `001-`, `002-`, etc.
- **Estado**: `Propuesto` | `Aceptado` | `Deprecado` | `Reemplazado por ADR-XXX`
- **Ubicación**: siempre en `docs/architecture/decisions/`
