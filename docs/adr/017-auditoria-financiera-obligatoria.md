# ADR-017: Auditoría Financiera Obligatoria

**Fecha**: Septiembre 2026  
**Estado**: ✅ Aceptado  
**Decisión**: P0-9 resuelto

## Contexto

El sistema maneja operaciones financieras críticas (pagos, refunds, split bills,
cambios de precio, cancelaciones) que requieren trazabilidad completa para:

1. **Cumplimiento normativo** (SII Chile)
2. **Auditoría externa** (contadores, auditores)
3. **Forensic analysis** (investigar incidentes financieros)
4. **Accountability** (responsabilidad por acción de usuario)

Existen dos opciones:
- **A) Auditoría opcional**: Solo para algunas operaciones críticas
- **B) Auditoría obligatoria**: Para TODAS las operaciones financieras

## Decisión

**Opción B: Auditoría OBLIGATORIA para todas las operaciones financieras.**

### Operaciones que DEBEN registrar AuditLog

| Categoría | Operaciones |
|-----------|-------------|
| **Pagos** | Crear pago, registrar propina, aplicar descuento |
| **Refunds** | Refund total, refund parcial, anular refund |
| **Bills** | Crear bill, split bill, merge bills, cancelar bill |
| **Orders** | Cancelar orden, cambiar status crítico, cambiar precio |
| **Caja** | Abrir sesión, cerrar sesión, movimiento manual |
| **Config financiera** | Cambiar impuestos, cambiar métodos de pago |

### Operaciones que NO requieren AuditLog

- Lectura de datos (queries)
- Navegación de UI
- Consultas de reportes
- Impresión de tickets (no modifica estado)

## Justificación

### Por qué obligatoria

1. **Inmutabilidad**: `AuditLog` override `update()` y `delete()` para lanzar
   `RuntimeException`. No puede modificarse ni eliminarse.

2. **Integridad**: `BelongsToTenant` garantiza aislamiento multi-tenant.
   Auditoría de empresa A nunca es visible para empresa B.

3. **Performance**: El overhead es mínimo (< 1ms por operación) porque
   AuditLog usa inserción directa sin lógica compleja.

4. **Forensic completeness**: En caso de incidente, tener el 100% de las
   operaciones financieras auditadas permite reconstruir exactamente qué pasó.

5. **Compliance**: SII Chile puede requerir trazabilidad completa de
   operaciones financieras.

### Por qué NO opcional

1. **Riesgo de omisión**: Si es opcional, desarrolladores pueden olvidar
   agregar el log en nuevas operaciones.

2. **Inconsistencia**: Auditoría parcial hace que el forensic analysis sea
   incompleto y poco confiable.

3. **Complejidad cognitiva**: Obligar a pensar "¿esta operación necesita log?"
   en cada cambio aumenta la carga mental.

## Implementación

### Servicio centralizado

```php
// app/Modules/Audit/Domain/Services/AuditService.php
class AuditService {
    public function log(
        string $action,
        string $entityType,
        int $entityId,
        ?string $entityUuid,
        array $payload = [],
        array $changes = [],
        ?string $reason = null
    ): AuditLog {
        return AuditLog::create([
            'company_id' => tenant()->company_id,
            'branch_id' => tenant()->branch_id,
            'user_id' => auth()->id(),
            'user_name' => auth()->user()?->name,
            'action' => $action,
            'entity_type' => $entityType,
            'entity_id' => $entityId,
            'entity_uuid' => $entityUuid,
            'payload' => $payload,
            'changes' => $changes,
            'reason' => $reason,
            'ip_address' => request()->ip(),
            'user_agent' => request()->userAgent(),
            'occurred_at' => now(),
        ]);
    }
}

Ejemplo de uso

// En PaymentService::registerPayment()
$payment = Payment::create([...]);

$this->auditService->log(
    action: 'payment.created',
    entityType: Payment::class,
    entityId: $payment->id,
    entityUuid: $payment->uuid,
    payload: [
        'amount' => $payment->amount,
        'tip_amount' => $payment->tip_amount,
        'method_code' => $payment->method_code,
    ],
    reason: $notes
);

Tests de integridad

test('AuditLog es inmutable: no se puede actualizar', function () {
    $log = AuditLog::factory()->create();
    
    expect(fn() => $log->update(['action' => 'modified']))
        ->toThrow(RuntimeException::class);
});

test('AuditLog es inmutable: no se puede eliminar', function () {
    $log = AuditLog::factory()->create();
    
    expect(fn() => $log->delete())
        ->toThrow(RuntimeException::class);
});

Consecuencias
Positivas
✅ Trazabilidad completa de operaciones financieras
✅ Cumplimiento normativo garantizado
✅ Forensic analysis confiable
✅ Auditoría externa facilitada
✅ Accountability de usuarios
Negativas
⚠️ Overhead mínimo en cada operación financiera (~1ms)
⚠️ Crecimiento de tabla audit_logs (mitigar con particionado por fecha)
⚠️ Requiere disciplina de usar AuditService en toda operación financiera
Mitigación de negativas
Performance: Medir impacto real; si > 5ms, considerar inserción async
Crecimiento: Particionar audit_logs por mes; archivar registros > 2 años
Disciplina: Code review obligatorio + lint rule que detecte operaciones
financieras sin AuditService
Validación
✅ AuditLog existe con BelongsToTenant
✅ Inmutabilidad garantizada (update/delete lanzan RuntimeException)
✅ AuditService centralizado
✅ 6 archivos de tests de auditoría
✅ AuditIntegrityTest, AuditLogTest pasando
Referencias
AuditLog.php
AuditService.php
AuditLogTest.php
AuditIntegrityTest.php
