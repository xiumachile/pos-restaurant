# ADR-014: Fail-secure en validación de contexto

**Fecha**: 2026-09-13  
**Estado**: Aceptado  
**Decisores**: Equipo de desarrollo

## Contexto

### Problema detectado

`validateContext()` tenía un modo permisivo peligroso:

```typescript
if (!current) {
  return true;  // ❌ Sin contexto = ALLOW
}
Justificación original: "para no romper tests legacy que no configuran auth"
Riesgo de seguridad
Este comportamiento viola el principio fail-secure:
En caso de duda o estado anómalo, DENEGAR la operación.
Vector de ataque potencial:
Si el auth context se pierde por bug o ataque, todas las operaciones son permitidas
SyncEngine procesaría items de la cola sin validar tenant
Items maliciosos o de otros tenants podrían ejecutarse
Ejemplo concreto:
Usuario se desloguea (o sesión expira)
Hay items pendientes en sync_queue de tenant anterior
SyncEngine ejecuta: validateContext() retorna true (sin auth = permisivo)
Items de otro tenant se procesan ❌
Análisis de callers
Caller
Comportamiento esperado sin auth
SyncEngine.processItem()
❌ Debe RECHAZAR item (datos de otro tenant
Conclusión: Todos los callers productivos deben fallar sin auth.
Decisión
Principio: Fail-secure
validateContext() SIEMPRE requiere contexto autenticado:
if (!current) {
  console.error(
    "[authContext] ❌ Validación rechazada: sin usuario autenticado. " +
    "Operación bloqueada (fail-secure)."
  );
  return false;  // ✅ DENY
}
Impacto en tests
Tests que dependían del modo permisivo deben:
Mockear auth context en beforeEach() (como producción)
Validar que sin auth se rechaza (test de fail-secure)
NO debilitar reglas de seguridad para acomodar tests
Razón de diseño
Fail-secure: En duda, denegar (no permitir)
Consistencia: Mismo comportamiento en tests y producción
Auditoría: Log de error cuando se bloquea operación sin auth
ADR-012/013 reforzado: Multi-tenancy con validación estricta
Consecuencias
Positivas
✅ Seguridad reforzada: Sin auth = operaciones bloqueadas
✅ Fail-secure: Comportamiento seguro por defecto
✅ Tests realistas: Mismo comportamiento que producción
✅ Auditoría mejorada: Logs de error claros
Negativas
⚠️ Breaking change: Tests legacy que no mockean auth fallarán
⚠️ Setup adicional: Tests requieren beforeEach() con auth mockeado
Mitigación
Revisión de tests confirmó que solo 1 test depende del modo permisivo
Test actualizado para validar fail-secure (espera false sin auth)
Documentación clara para futuros tests
Implementación
Cambios en código
authContext.ts:
validateContext() retorna false si no hay auth
Log de error (no warning) cuando se bloquea
Comentario JSDoc actualizado
Tests:
Test de "modo permisivo" renombrado a "modo fail-secure"
Expectativa cambiada: sin auth → false (rechazado)
Validación
✅ Suite completa pasando (431 tests)
✅ SyncEngine tests siguen pasando (ya mockean auth)
✅ Test de fail-secure valida comportamiento correcto
Alternativas consideradas
Alternativa 1: Mantener modo permisivo (RECHAZADA)
❌ Viola principio fail-secure
❌ Debilita seguridad para acomodar tests
❌ Comportamiento diferente entre tests y producción
Alternativa 2: Modo permisivo solo en NODE_ENV=test (RECHAZADA)
❌ Complejidad innecesaria
❌ Tests no reflejan comportamiento productivo
❌ Bugs de auth no se detectan en tests
Alternativa 3: Fail-secure siempre (ACEPTADA)
✅ Seguridad por defecto
✅ Tests realistas
✅ Principio fail-secure aplicado
Referencias
ADR-012: Multi-tenancy local obligatorio
ADR-013: Inmutabilidad de tenant
OWASP: Fail Secure (https://owasp.org/www-community/attacks/Fail_secure)
Commit de seguridad: (pendiente)
