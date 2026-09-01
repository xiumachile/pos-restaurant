# Gap de Integración Backend ↔ Frontend

**Fecha**: 31 de agosto de 2026  
**Identificado por**: Revisión arquitectónica post-sprint  
**Estado**: 🟡 Documentado, pendiente de abordar

## Resumen

El sprint backend 30-31 de agosto completó Fase 1 (capabilities) y Fase 2 (Order Core
sin mesa) **a nivel de API/backend**, pero el frontend (en `./frontend/`) no ha sido
actualizado para consumir estas nuevas capacidades. Esto crea un **gap funcional**
donde el backend está listo pero la UI no puede usarlo.

## Gap 1: Frontend no consume capabilities de empresa

### Estado del backend (listo)
- ✅ 6 de 8 capabilities conectadas a lógica real
- ✅ `CompanyPolicy` + `CompanyCapability` entity + endpoints en `CompanyController`
- ✅ Tests completos de middleware de capabilities

### Estado del frontend (no integrado)
- ❌ `SettingsPage.tsx` no existe
- ❌ Ningún componente consulta `capabilities` de la empresa
- ❌ No hay toggle UI para activar/des capabilities por empresa

### Evidencia

```bash
$ grep -rn "capabilities\|can_accept_tips\|can_split_bills" frontend/src/
# 0 resultados
Trabajo estimado
Crear SettingsPage.tsx con listado de capabilities: 3-4 horas
Integrar con GET /api/v1/companies/{id}/capabilities: 2 horas
Toggle UI + llamadas a PUT /capabilities: 3-4 horas
Feature flags condicionales en componentes existentes: 4-6 horas
Total estimado: 12-16 horas
Impacto de no resolver
Backend Fase 1 no se usa en producción a pesar de estar listo
Equipos no pueden personalizar comportamiento por restaurante
Desincronización entre roadmap backend y experiencia de usuario
Gap 2: OrderTakingPage.tsx asume mesa obligatoria
Estado del backend (listo)
✅ 3 tipos de pedido soportados (dine_in, takeout, delivery)
✅ 3 canales de fulfillment (onsite, pickup, delivery)
✅ Endpoints de creación de pedido sin mesa funcionan
✅ Validaciones condicionales por tipo
Estado del frontend (atado a mesa)
❌ OrderTakingPage.tsx monta en ruta con tableUuid obligatorio
❌ Navegación asume flujo: /mesas/:tableUuid → página de pedido
❌ No hay selector de tipo de pedido (dine_in/takeout/delivery)
❌ No hay campos para customer_name, customer_phone, delivery_address
Evidencia
// frontend/src/pages/OrderTakingPage.tsx
const { tableUuid } = useParams<{ tableUuid: string }>();
const { data: activeOrders = [] } = useTableOrders(tableUuid || null);
const table = flattenAreas(areas).find((t) => t.uuid === tableUuid);
La página literalmente se monta en una ruta con tableUuid. No es una
validación opcional, es una decisión estructural de navegación.
Trabajo estimado (refactor grande)
Rediseñar navegación para pedidos sin mesa: 6-8 horas
Selector de tipo de pedido en UI: 4-6 horas
Form condicional por tipo (campos de cliente, dirección): 6-8 horas
Lista unificada de pedidos por canal (onsite/pickup/delivery): 8-10 horas
Integración con nuevos endpoints de transición: 4-6 horas
Total estimado: 28-38 horas
Impacto de no resolver
Fast food, cafeterías, dark kitchens no pueden operar con el sistema
Delivery propio no tiene UI
Takeout requiere pasar por el flujo de mesa (workaround incómodo)
Gap 3: Estados específicos por fulfillment no expuestos
Estado del backend (listo)
✅ 4 nuevos endpoints: /ready-for-pickup, /pickup, /dispatch, /deliver
✅ Timestamps picked_up_at, dispatched_at, delivered_at
✅ Kitchen Display filtra por canal
Estado del frontend (sin integrar)
❌ UI de cocina no muestra pedidos agrupados por canal
❌ No hay botón "Listo para retirar" / "Despachar" / "Entregar"
❌ Sin notificaciones SMS/email para cliente pickup/delivery
Trabajo estimado
Kitchen Display con pestañas por canal: 6-8 horas
Botones de transición específicos: 2-3 horas
Notificaciones externas (Twilio/SendGrid): 4-6 horas
Total estimado: 12-17 horas
Priorización Recomendada
Sprint siguiente: Gap 1 (capabilities en UI)
Razón: Es el más fácil de abordar y habilita valor de negocio inmediato
(personalización por restaurante). Además, es prerequisito para otros gaps
(show/hide features según capability activa).
Horas estimadas: 12-16
Sprint posterior: Gap 2 (Order Core sin mesa en UI)
Razón: Requiere refactor estructural de navegación. Debe hacerse con
cuidado para no romper flujo existente.
Horas estimadas: 28-38 (puede partirse en 2 sprints)
Oportunista: Gap 3 (Kitchen Display por canal)
Razón: Puede hacerse incrementalmente una vez Gap 2 esté resuelto.
Decisiones Pendientes
¿Contratar frontend dev dedicado o dividir entre equipo actual?
¿Setup de design system para componentes de capabilities?
¿Feature flags en frontend (LaunchDarkly/PostHog/unleash) o solo capabilities del backend?
¿Migrar OrderTakingPage a nueva arquitectura de rutas sin mesa, o crear página paralela (/orders/new)?
Documentación Relacionada
ADR-001: Modelo de Fulfillment
ADR-002: Backward Compatibility
Hoja de ruta 31 ago 2026
Guía operativa de fulfillment
