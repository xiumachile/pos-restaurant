# ADR-013: Inmutabilidad de tenant en contexto de autenticación

**Fecha**: 2026-09-13  
**Estado**: Aceptado  
**Decisores**: Equipo de desarrollo

## Contexto

### Problema detectado

`mergeAuthContext()` permitía override explícito de `company_id` y `branch_id`:

```typescript
mergeAuthContext({
  company_id: "OTRA_EMPRESA",  // ❌ Override aceptado
  branch_id: "OTRA_SUCURSAL"
})
// Retornaba: company_id="OTRA_EMPRESA" (override exitoso)
Riesgo de seguridad
Aunque SyncEngine.validateContext() ofrece una segunda barrera, el override
genérico de tenant es contrario al principio establecido en ADR-012:
"El contexto del usuario autenticado debe ser la fuente de verdad"
Vector de ataque potencial:
Un caller malicioso o con bug podría crear operaciones cross-tenant
Dependencia en segunda barrera (que puede no estar en todos los paths)
Violación del principio de least privilege
Análisis de uso real
Revisión de callers de mergeAuthContext():
Caller
Override de tenant
Necesidad real
OrderCartPanel
❌ No hace
Solo pasa table_id
useOfflineOrderStore
❌ No hace
Solo pasa payload de negocio
Tests
✅ Hacía (para testing)
Refactorizables
Conclusión: El override de tenant no tiene uso legítimo en producción.
Decisión
Política: Tenant inmutable
mergeAuthContext() SIEMPRE usa los valores del contexto autenticado para:
company_id
branch_id
terminal_id
user_id
user_name
user_role
Si un caller pasa estos campos explícitamente:
Se ignoran silenciosamente
Se loguea warning en desarrollo
El resultado SIEMPRE refleja el tenant autenticado
Razón de diseño
Seguridad por defecto: El tenant autenticado es la única fuente de verdad
Principio de least privilege: Callers no pueden elevar privilegios
Simplicidad: Un solo path de código, menos superficie de ataque
ADR-012 reforzado: Multi-tenancy con aislamiento garantizado
Casos cross-tenant legítimos
Si en el futuro surge un caso legítimo (ej: admin haciendo sync cross-tenant):
NO usar mergeAuthContext()
Crear API explícita: createAdminContext(company_id, branch_id)
Requerir rol específico (admin o super_admin)
Loguear operación en auditoría
Validar permisos en backend
Pero por ahora: No existen casos legítimos, por lo que no se implementa.
Consecuencias
Positivas
✅ Seguridad reforzada: Imposible crear operaciones cross-tenant vía merge
✅ Simplicidad: Un solo comportamiento, menos casos edge
✅ Auditoría: Comportamiento predecible y documentado
✅ ADR-012 reforzado: Multi-tenancy con aislamiento garantizado
Negativas
⚠️ Breaking change: Callers que dependían de override fallarán
⚠️ Tests requieren refactor: Tests de override deben actualizarse
⚠️ Menos flexibilidad: Casos legítimos futuros requieren nueva API
Mitigación
Revisión de callers confirmó que ninguno depende del override
Tests actualizados para validar el nuevo comportamiento
Documentación clara para futuros desarrolladores
Implementación
Cambios en código
authContext.ts:
mergeAuthContext() ignora override de campos de tenant
Warning en consola si caller intenta override
Retorno SIEMPRE refleja contexto autenticado
Tests:
Test de override eliminado
Test de rechazo de override agregado
Test de seguridad específico
Validación
✅ Todos los callers existentes siguen funcionando
✅ Tests de aislamiento multi-tenant (ADR-012) siguen pasando
✅ Test de seguridad valida que override es bloqueado
Alternativas consideradas
Alternativa 1: Mantener override con validación (RECHAZADA)
❌ Complejidad innecesaria
❌ Segunda barrera puede fallar
❌ Viola principio de least privilege
Alternativa 2: API separada para cross-tenant (RECHAZADA por ahora)
⚠️ Más código sin uso legítimo actual
⚠️ Complejidad prematura
✅ Se considerará si surge caso legítimo
Alternativa 3: Eliminar override completamente (ACEPTADA)
✅ Seguridad por defecto
✅ Simplicidad
✅ ADR-012 reforzado
Referencias
ADR-012: Multi-tenancy local obligatorio
Commit de seguridad: (pendiente)
Test de seguridad: src/tests/services/authContext.test.ts
