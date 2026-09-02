# ADR-007: Estrategia de Idempotencia (Redis + SQL Híbrido)

**Fecha**: 02 Septiembre 2026  
**Estado**: ✅ Aceptado  
**Contexto**: Pre-Frontend Gate — Punto #7 (Idempotencia en mutaciones)

## Contexto

El middleware `IdempotencyKeyMiddleware` original usaba SQL como única fuente de verdad:

```php
// CÓDIGO ORIGINAL (solo SQL)
$existing = IdempotencyKey::where('key', $idempotencyKey)->first();
// ...
IdempotencyKey::create([...]);
Problemas identificados:
Cuello de botella I/O: en carga alta (50+ terminales), cada POST hace INSERT/SELECT en SQL
Latencia: queries SQL son más lentas que cache en memoria
Escalabilidad: no escala horizontalmente sin replicas de BD
Requisitos:
Idempotencia garantizada (mismo key = misma respuesta)
Performance en alta carga
Durabilidad (auditoría/compliance)
Resilencia (si cache cae, sistema sigue funcionando)
Decisión
Estrategia híbrida: Redis como cache de performance + SQL como fuente de verdad.
Arquitectura Híbrida (Write-Through)
┌─────────────────────────────────────────────────────┐
│  REQUEST ENTRANTE (con Idempotency-Key)             │
├─────────────────────────────────────────────────────┤
│                                                     │
│ PASO 1: Check Redis (O(1), fast path)               │
│ ─────────────────────────────────                   │
│ Cache::get('idempotency:{key}')                     │
│                                                     │
│ ├─ Si existe y hash coincide:                       │
│ │   → Retornar respuesta cacheada (O(1))            │
│ │   → NO tocar SQL                                  │
│ │                                                   │
│ ├─ Si existe pero hash NO coincide:                 │
│ │   → Retornar 409 Conflict                         │
│ │                                                   │
│ └─ Si NO existe:                                    │
│     → Ir a PASO 2                                   │
│                                                     │
│ PASO 2: Check SQL (fuente de verdad)                │
│ ─────────────────────────────────                   │
│ IdempotencyKey::where('key', $key)->first()         │
│                                                     │
│ ├─ Si existe y hash coincide:                       │
│ │   → Write-through: cachear en Redis               │
│ │   → Retornar respuesta                            │
│ │                                                   │
│ ├─ Si existe pero hash NO coincide:                 │
│ │   → Retornar 409 Conflict                         │
│ │                                                   │
│ └─ Si NO existe:                                    │
│     → Ir a PASO 3                                   │
│                                                     │
│ PASO 3: Procesar request (primera vez)              │
│ ─────────────────────────────────                   │
│ $response = $next($request);                        │
│                                                     │
│ PASO 4: Write-through (si response es 2xx)          │
│ ─────────────────────────────────                   │
│ IdempotencyKey::create([...]);  ← SQL (durabilidad) │
│ Cache::put($key, $data, TTL);   ← Redis (perf)      │
│                                                     │
│ Retornar respuesta                                  │
└─────────────────────────────────────────────────────┘
TTL: 24 horas
protected const DEFAULT_TTL_HOURS = 24;
Razones:
Suficiente para reintentos de red (normalmente < 1 hora)
No contamina cache con keys muy antiguas
Balance entre performance y uso de memoria
Clave de Cache
$cacheKey = 'idempotency:' . $idempotencyKey;
Estructura:
Prefijo idempotency: para namespace
Key UUIDv4 (único por request)
Consecuencias
Positivas
✅ Performance: O(1) lookup en Redis (fast path)
✅ Durabilidad: SQL como fuente de verdad (auditoría)
✅ Resilencia: si Redis cae, SQL sigue funcionando
✅ Escalabilidad: Redis maneja alta carga horizontalmente
✅ Backwards compatible: tests existentes siguen pasando
✅ Write-through: consistencia garantizada entre Redis y SQL
Negativas
⚠️ Complejidad: dos fuentes de datos (Redis + SQL)
⚠️ Consistencia eventual: si Redis cae, puede haber desincronización temporal
⚠️ Overhead de write-through: cada write hace INSERT SQL + SET Redis
Neutrales
ℹ️ Latencia de write: write-through agrega ~2-5ms por request (aceptable)
ℹ️ Memoria Redis: ~1KB por key × 24h = uso moderado
Resilencia
Si Redis está caído
Request → Redis falla → Fallback a SQL → Procesamiento normal
Sistema NO funciona (SQL es fuente de verdad)
Este escenario es crítico (SQL down = sistema down)
Mitigación: replicación de BD + monitoreo
Si ambos están caídos
Request → Redis falla → SQL falla → Error 500
Sistema completamente caído
Mitigación: health checks + alertas
Performance Esperada
Escenario: 50 terminales, 100 requests/segundo
Métrica
Solo SQL
Híbrido (Redis + SQL)
Latencia P50
50ms
5ms (Redis hit)
Latencia P99
200ms
50ms (SQL fallback)
Throughput
200 req/s
1000+ req/s
CPU BD
Alto
Bajo (cache hits)
Cache Hit Rate Esperado
Reintentos inmediatos (< 1 min): ~95% cache hit
Reintentos tardíos (1-24h): ~80% cache hit
Requests nuevos: 0% cache hit (obvio)
Alternativas Descartadas
❌ Solo Redis: sin durabilidad, pérdida de datos si Redis cae
❌ Solo SQL: cuello de botella en alta carga
❌ Memcached: sin persistencia, similar a solo Redis
❌ Redis como fuente de verdad: sin auditoría/compliance
Migración
Fase 1: Implementación (actual)
Middleware híbrido implementado
Redis configurado como cache driver
SQL mantiene tabla idempotency_keys
Write-through activo
Fase 2: Monitoreo (próximo sprint)
Métricas de cache hit rate
Latencia P50/P99
Alertas si cache hit rate < 70%
Fase 3: Optimización (opcional)
Ajustar TTL según patrones de uso
Redis cluster para alta disponibilidad
Compresión de response_body en cache
Testing
Tests existentes siguen pasando:
IdempotencyTest: valida flujo básico
PaymentApiTest: valida idempotencia en pagos
OrderApiTest: valida idempotencia en órdenes
Tests nuevos recomendados:
Cache hit vs miss scenarios
Redis down fallback
SQL down error handling
Referencias
ADR-004: Idempotencia en Ledger (nivel de servicio)
ADR-007: Idempotencia en Middleware (nivel de HTTP)
Middleware: app/Shared/Http/Middleware/IdempotencyKeyMiddleware.php
Modelo: app/Shared/Domain/Entities/IdempotencyKey.php
Decisión tomada por: Arquitecto + Desarrollador
Fecha: 02 Septiembre 2026
