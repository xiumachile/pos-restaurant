# Definition of Done — Cambios de Esquema y Dominio

**Versión**: 1.0  
**Fecha**: 31 de agosto de 2026  
**Motivación**: Prevenir el patrón "un endpoint se corrige, el hermano queda igual"
y evitar estados inconsistentes entre modelo, migración y documentación.

## ¿Cuándo aplicar este checklist?

Aplicar obligatoriamente antes de mergear cambios que toquen:
- ✅ Enums de dominio (nuevos casos o métodos)
- ✅ Entidades Eloquent (`$fillable`, `$casts`)
- ✅ Migraciones de base de datos
- ✅ Rutas de API (nuevas o modificadas)
- ✅ Capabilities de empresa
- ✅ Requests (FormRequests)

## Checklist por Tipo de Cambio

### 🔷 Para enums de dominio

- [ ] ¿Existe la migración correspondiente que crea/modifica la columna?
- [ ] ¿El docblock del enum refleja el estado actual (no "se hará en el futuro")?
- [ ] ¿Hay test que cubra el enum directamente?
- [ ] ¿El cast está definido en la entidad correspondiente?
- [ ] ¿Se agregó el caso nuevo a CHECK constraints en DB (si aplica PostgreSQL)?

**Anti-patrón a evitar**: Enum creado con campos en `$fillable` pero sin migración
ejecutada. Ejemplo histórico: `FulfillmentChannel` creado en commit `53515af` con
nota "se agregará en fase posterior" — la migración se agregó en `a7c4289` pero el
docblock quedó desactualizado hasta revisión post-sprint.

### 🔷 Para entidades Eloquent

- [ ] ¿Todo campo en `$fillable` tiene su columna en la DB?
- [ ] ¿Todo campo en `$casts` tiene su columna en la DB?
- [ ] ¿La migración se ejecutó sin errores en el ambiente de test?
- [ ] ¿El backfill (si aplica) está incluido en la migración?
- [ ] ¿El `OrderResource` (o resource correspondiente) expone los nuevos campos?

**Verificación rápida**:
```bash
# Ver columnas reales de tabla
php artisan tinker --execute="dump(Schema::getColumnListing('orders'))"

# Comparar con $fillable
grep -A 40 "protected \$fillable" app/Modules/Orders/Domain/Entities/Order.php
🔷 Para capabilities
¿Está conectada en al menos un punto del dominio (middleware, service, request)?
¿Existe test que valide capability ON y OFF?
¿El middleware capability: está aplicado en las rutas relevantes?
¿El frontend la consume (si aplica al scope)? → Documentar en docs/architecture/gaps/ si no
Ejemplo histórico exitoso:
can_accept_tips: conectada en StorePaymentRequest.withValidator() + 3 tests
has_kitchen_display: conectada en OrderService.updateOrder() + 8 tests
can_manage_inventory: conectada vía middleware + 4 tests en CompanyCapabilityMiddlewareTest
🔷 Para rutas nuevas
¿El controller tiene la validación de autorización ($this->authorize())?
¿Existe método correspondiente en el Policy?
¿La request valida todos los campos de entrada?
¿Hay test de happy path y al menos un error path?
¿Si hay "endpoint hermano" (ej: index/list), se revisó también?
🔷 Para cambios en requests (FormRequests)
¿Las reglas cubren todos los campos del payload esperado?
¿Hay validaciones condicionales según el tipo (ej: delivery requiere dirección)?
¿Los mensajes de error están en español y son claros?
¿withValidator() valida reglas cross-field si aplica?
Anti-patrones a Evitar
❌ "Un endpoint se corrige, el hermano queda igual"
Cuando se corrige un método de un controller, revisar inmediatamente si
los otros métodos del mismo controller tienen el mismo patrón problemático.
Ejemplos históricos:
BillController::index vs BillController::split → IDOR resuelto en uno
pero inicialmente no en el otro (Checklist #14)
StorePaymentRequest con tip_amount sin validar capability → fix en #18
❌ "Modelo listo, DB no lista"
Agregar campos a $fillable/$casts sin tener la migración correspondiente
creada y ejecutada. Deja el código en estado inconsistente.
Ejemplo histórico:
FulfillmentChannel creado con docblock "se agregará en fase posterior"
pero Order entity ya lo tenía en $fillable y $casts. Corregido al
agregar migración en commit a7c4289 y actualizar docblock en revisión.
❌ "Tests pasando sin coverage del cambio"
Agregar tests que pasan pero no cubren el cambio que se hizo.
Check: Cada commit debe tener al menos un test que falle si se revierte
el cambio de ese commit.
❌ "Backend listo, frontend sin tocar"
Completar funcionalidad backend sin considerar integración con UI.
Solución: Crear documento en docs/architecture/gaps/ describiendo:
Qué existe en backend
Qué falta en frontend
Horas estimadas de trabajo
Priorización recomendada
Proceso Recomendado
Antes de empezar (pre-flight)
Identificar qué capas del stack toca el cambio
Abrir el checklist correspondiente
Identificar endpoints/hermanos que podrían tener el mismo patrón
Durante el desarrollo
Marcar items del checklist a medida que se completan
Si surge un anti-patrón conocido, detenerse y resolver antes de continuar
Antes de commitear
Revisar el checklist completo
Ejecutar ./vendor/bin/pest para validar sin regresiones
Verificar que no queden archivos en estado inconsistente
Durante code review
El reviewer debe verificar el checklist también
Si falta un item, pedir que se agregue antes de aprobar
Métricas de Calidad
Indicadores de deuda técnica
⚠️ Número de docblocks desactualizados
⚠️ Número de campos en $fillable sin columna correspondiente
⚠️ Número de capabilities en enum sin conectar a código
⚠️ Número de gaps backend/frontend sin documentar
Meta del equipo
Cero inconsistencias modelo ↔ DB
100% de capabilities conectadas o documentadas como gap
Todos los cambios con al menos un test de regresión
Referencias
Hoja de ruta actualizada
Gaps backend/frontend identificados
ADRs en docs/architecture/decisions/
Checklist original en hoja de ruta (items #1-18)
