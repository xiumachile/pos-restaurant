**Fecha:** Septiembre 2026  
**Estado:** Aceptado  
**Contexto:** Sistema POS para restaurantes en Chile

## Contexto

El sistema maneja dinero en múltiples puntos:
- SQLite local (offline-first)
- TypeScript (frontend)
- PostgreSQL (backend cloud)
- Impresión de tickets

### Problemas identificados:

1. **SQLite usa REAL para dinero**
   ```sql
   amount REAL,
   grand_total REAL,
   tax_total REAL
2. TypeScript usa number
   amount: number;
   grand_total: number;
3. 19% IVA hardcodeado en múltiples lugares
   const tax = subtotal * 0.19;
4. Operaciones aritméticas sin control de precisión
   const split = total / guests;  // 9999.999999...
Riesgos:
Errores de redondeo: 10000.01 en lugar de 10000
Inconsistencias entre frontend y backend
Dificultad para auditoría contable
Problemas en split bills, tips, partial payments
Decisión
Para Chile (CLP - peso chileno):
SQLite local: INTEGER (centavos)
El peso chileno no tiene centavos fraccionarios
Almacenar como entero: 10000 = $10.000
Evita errores de punto flotante
TypeScript: number con validación
Mantener number para compatibilidad
Agregar validaciones de precisión
Usar helpers para operaciones críticas
Backend: DECIMAL o INTEGER
PostgreSQL puede usar DECIMAL(10,0) o INTEGER
Mantener consistencia con frontend
Estrategia de implementación:
1. Crear helpers de Money
   // src/utils/money.ts
   export function roundToCents(amount: number): number {
     return Math.round(amount);
   }
   
   export function calculateTax(subtotal: number, rate: number = 0.19): number {
     return roundToCents(subtotal * rate);
   }
   
   export function splitAmount(total: number, parts: number): number[] {
     const base = Math.floor(total / parts);
     const remainder = total % parts;
     return Array(parts).fill(base).map((val, i) => 
       i < remainder ? val + 1 : val
     );
   }

2. Extraer IVA a constante
   // src/config/tax.ts
   export const IVA_RATE = 0.19;
   export const IVA_PERCENTAGE = 19;
3. Validaciones de precisión
   export function validateMoneyAmount(amount: number): void {
     if (!Number.isInteger(amount)) {
       console.warn(`[Money] Amount ${amount} is not an integer`);
     }
   }
4. Plan de migración gradual
Fase 1: Crear helpers y constantes (este commit)
Fase 2: Migrar migraciones a INTEGER (próximo commit)
Fase 3: Actualizar código para usar helpers (futuro)
Consecuencias
Positivas:
✅ Evita errores de redondeo
✅ Consistencia entre frontend y backend
✅ Auditoría contable más clara
✅ Compatible con estándares financieros
Negativas:
⚠️ Requiere migración de schema
⚠️ Código legacy debe actualizarse gradualmente
⚠️ Posible breaking change en APIs
Alternativas consideradas
Opción 1: Mantener REAL (descartada)
❌ Errores de precisión inevitables
❌ No es estándar financiero
❌ Problemas en split bills
Opción 2: Usar string para dinero (descartada)
❌ Overkill para el caso de uso
❌ Complejidad innecesaria
❌ Performance impactada
Opción 3: Migrar todo inmediatamente (descartada)
❌ Demasiado invasivo
❌ Riesgo de breaking changes
❌ Dificulta testing
Opción 4: Estrategia gradual con helpers (ACEPTADA)
✅ No rompe código existente
✅ Mejora progresiva
✅ Fácil de testear
✅ Documentación clara
Métricas
Commits planeados: 3
Archivos modificados: ~15
Tests nuevos: ~10
Breaking changes: 0 (gradual)
Referencias
IEEE 754 floating point issues
Martin Fowler - Money Pattern
Chilean peso - Wikipedia
