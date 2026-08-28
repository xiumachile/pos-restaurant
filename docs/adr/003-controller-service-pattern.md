# ADR-003: Patrón Controller → Service → Domain

**Status:** Accepted
**Date:** 2026-08-28
**Deciders:** Equipo de Desarrollo
**Sprint:** S2-S3 - Consolidación de Arquitectura

---

## Context

Durante S2-S3 detectamos que varios controllers habían crecido a 200-450 líneas concentrando:
- Queries Eloquent y DB::table directas
- Transformaciones de datos inline
- Validaciones de negocio mezcladas con validaciones HTTP
- Lógica de transacciones y manejo de errores

Esto violaba el principio DDD de **separación de responsabilidades** y dificultaba:
- Testeo aislado de lógica de negocio
- Reutilización de lógica entre endpoints
- Mantenimiento a largo plazo

---

## Decision

**Patrón arquitectónico de 3 capas:**
┌─────────────────────────────────────────┐
│ Controller (HTTP Orchestration) │
│ • Valida input (FormRequest) │
│ • Lookups por UUID │
│ • Delega al Service │
│ • Convierte excepciones → JSON │
│ • Retorna respuesta │
└──────────────┬──────────────────────────┘
│
▼
┌─────────────────────────────────────────┐
│ Service (Business Logic) │
│ • Reglas de negocio │
│ • Queries y transformaciones │
│ • Validaciones de dominio │
│ • Orquesta entidades y otros services │
│ • Lanza DomainExceptions │
└──────────────┬──────────────────────────┘
│
▼
┌─────────────────────────────────────────┐
│ Domain (Entities, Value Objects, │
│ Domain Services, Repositories) │
└─────────────────────────────────────────┘

### Responsabilidades Claras

**Controller (~80-150 líneas):**
- Recibe HTTP Request
- Valida input con FormRequest
- Hace lookups simples por UUID + tenant
- Delega toda lógica al Service
- Maneja excepciones → JSON responses
- NO tiene: `DB::table`, `->map()`, `foreach` de transformación, `->sum()` de negocio

**Service (~150-300 líneas):**
- Contiene toda la lógica de negocio
- Hace queries complejas y transformaciones
- Valida reglas de dominio
- Lanza `DomainException` para errores de negocio
- NO sabe nada de HTTP, JSON, Request, Response

**FormRequest:**
- Validación HTTP (required, types, formats)
- NO contiene lógica de negocio

---

## Evidence

### Refactors Aplicados (S2-S3)

| Controller | Antes | Después | Reducción | Service Creado |
|-----------|-------|---------|-----------|----------------|
| CashierTablesController | 451 | 166 | -63% | CashierTableService |
| MenuController | 266 | 161 | -39% | MenuManagementService |
| DteDocumentController | 242 | 171 | -29% | DteDocumentManagementService |
| **Total** | 959 | 498 | **-48%** | 3 Services |

### Beneficios Medidos
- Controllers 48% más pequeños en promedio
- Lógica testeable sin HTTP layer
- Reutilización de lógica entre endpoints
- Separación clara de responsabilidades

---

## Implementation Guide

### Paso 1: Crear el Service

```php
<?php
namespace Modules\X\Domain\Services;

class XManagementService
{
    public function __construct(
        // Inyectar dependencias
    ) {}
    
    public function listX(int $branchId, array $filters): Collection
    {
        // Queries y filtros
    }
    
    public function createX(array $data, User $user): X
    {
        if (!$this->meetsBusinessRule($data)) {
            throw new \DomainException('Mensaje claro');
        }
        return X::create([...]);
    }
    
    private function meetsBusinessRule(array $data): bool
    {
        // Lógica privada
    }
}
<?php
class XController extends Controller
{
    public function __construct(
        private XManagementService $xService
    ) {}
    
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();
        $items = $this->xService->listX($user->branch_id, $request->only(['filter']));
        return XResource::collection($items)->response();
    }
    
    public function store(XRequest $request): JsonResponse
    {
        try {
            $x = $this->xService->createX($request->validated(), $request->user());
            return XResource::make($x)->response()->setStatusCode(201);
        } catch (\DomainException $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 422);
        }
    }
}

---

## Cuándo Aplicar

### Aplicar cuando:
- Controller > 150 líneas
- Controller tiene `->map()`, `->filter()`, `foreach` de transformación
- Controller tiene queries Eloquent complejas (más de un where)
- Controller tiene validaciones de dominio (no solo formato)
- Controller tiene `DB::table` directo

### No aplicar cuando:
- Controller es CRUD simple (≤80 líneas)
- Lógica es solo lookup + retorno
- Controller ya delega a useCases existentes
- Refactor rompería API pública

---

## Consequences

### Positive
1. **Testabilidad**: Services testeables sin HTTP overhead
2. **Reutilización**: misma lógica sirve a múltiples endpoints
3. **Mantenibilidad**: cambios de negocio en un solo lugar
4. **Separación clara**: cada capa tiene responsabilidad única

### Negative
1. **Más archivos**: controller + service en vez de solo controller
2. **Indirección**: para lógica muy simple puede ser over-engineering
3. **Curva de aprendizaje**: nuevos devs deben entender el patrón

### Neutral
1. **Naming convention**: `XManagementService` para gestión CRUD, `XProcessingService` para flujos
2. **Ubicación**: `Modules/X/Domain/Services/` (dominio) o `Modules/X/Application/Services/` (aplicación)

---

## Testing Strategy

### Tests de Controller (HTTP layer)
- Status codes correctos (200, 201, 422, 404)
- JSON structure correcta
- Validación de FormRequest
- Manejo de DomainException → 422

### Tests de Service (business layer)
- Lógica de negocio aislada (sin HTTP)
- Validaciones de dominio lanzan DomainException
- Queries retornan datos correctos
- Transformaciones correctas

### Tests de Integración
- Flujo completo: request → controller → service → DB → response
- Cross-tenant isolation
- Idempotencia

---

## Related ADRs

- **ADR-001**: Arquitectura de Autenticación JWT
- **ADR-002**: Aislamiento Multi-Tenant

---

## Changelog

| Fecha | Versión | Cambios |
|-------|---------|---------|
| 2026-08-28 | 1.0 | Versión inicial (basada en S2-S3) |
