# Módulo: I18n

Módulo del sistema POS según arquitectura v1.0 (sección 7.1).

## Estructura de capas (sección 7.2)

- `Interfaces/`   → Controllers, Resources, Requests, Listeners
- `Application/`  → UseCases, Commands, Queries, DTOs
- `Domain/`       → Aggregates, Entities, ValueObjects, Policies, Events, Repositories
- `Infrastructure/` → Eloquent, Redis, Reverb, Queue, APIs externas, Localización
- `Routes/`       → Definición de rutas del módulo
- `Database/`     → Migraciones, Seeders, Factories
- `Tests/`        → Unit y Feature

## Convenciones
- Toda entidad operativa incluye: company_id, branch_id (sección 8.2).
- Traducciones dinámicas con JSONB (sección 9.5).
- Idempotencia en mutaciones críticas (sección 10.7).
