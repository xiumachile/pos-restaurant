# Documentación Semántica: Split Bill Strategies

## Contexto

El sistema soporta dos estrategias de split bill, cada una con características semánticas diferentes:

### 1. splitEqual() - División Equitativa Pura

**Estrategia**: División entera con absorción de residuo

**Algoritmo**:
Para cada bill i de N bills:
bill[i].subtotal = floor(order.subtotal / N)
bill[i].tax = floor(order.tax / N)
bill[i].discount = floor(order.discount / N)
bill[i].total = floor(order.total / N)
Residuo absorbido por bill[0]:
bill[0].subtotal += order.subtotal - (floor(order.subtotal/N) * N)
bill[0].tax += order.tax - (floor(order.tax/N) * N)
bill[0].discount += order.discount - (floor(order.discount/N) * N)
bill[0].total += order.total - (floor(order.total/N) * N)


**Características**:
- ✅ Sin operaciones decimales
- ✅ Precisión exacta en enteros
- ✅ Primera bill absorbe residuo (convención)
- ⚠️ Bills pueden diferir en ±1 CLP

**Ejemplo**:

Order: subtotal=10000, tax=1900, total=11900
Split en 3 bills:
bill[0]: subtotal=3334, tax=634, total=3968 (absorbe residuo)
bill[1]: subtotal=3333, tax=633, total=3966
bill[2]: subtotal=3333, tax=633, total=3966
Verificación: 3334+3333+3333 = 10000 ✅


---

### 2. splitByItems() - División Proporcional Racional

**Estrategia**: Cálculo proporcional con redondeo y absorción de residuo

**Algoritmo**:

Para cada grupo de items:
1. Calcular subtotal del grupo
2. Calcular ratio proporcional:
ratio = groupSubtotal / totalGroupedSubtotal (decimal)
3. Asignar tax proporcional:
groupTax = round(orderTax * ratio) (entero)
4. Asignar discount proporcional:
groupDiscount = round(orderDiscount * ratio) (entero)
4. Calcular total del grupo:
groupTotal = groupSubtotal + groupTax - groupDiscount
Residuo absorbido por última bill:
lastBill.tax += orderTax - sum(all groupTaxes)
lastBill.discount += orderDiscount - sum(all groupDiscounts)
lastBill.total += orderTotal - sum(all groupTotals)


**Características**:
- ⚠️ Usa operaciones decimales internas (ratio)
- ✅ Resultado final en enteros CLP
- ✅ Proporcional al valor de cada grupo
- ✅ Última bill absorbe residuo de redondeo
- ⚠️ Error máximo: ±1 CLP por bill (excepto última)

**Ejemplo**:

Order: subtotal=10000, tax=1900, total=11900
Grupo A: items con subtotal=7000 (70%)
Grupo B: items con subtotal=3000 (30%)
Cálculo proporcional:
ratioA = 7000 / 10000 = 0.7
ratioB = 3000 / 10000 = 0.3
Asignación:
billA.tax = round(1900 * 0.7) = 1330
billB.tax = round(1900 * 0.3) = 570
Verificación: 1330 + 570 = 1900 ✅
billA: subtotal=7000, tax=1330, total=8330
billB: subtotal=3000, tax=570, total=3570
Total: 8330 + 3570 = 11900 ✅


---

## Invariantes Garantizadas

Ambas estrategias garantizan:

1. **Conservación de totales**:

sum(all bills.subtotal) = order.subtotal
sum(all bills.tax) = order.tax
sum(all bills.discount) = order.discount
sum(all bills.total) = order.total


2. **Resultado entero**:
   - Todos los valores finales son enteros CLP
   - Sin decimales en la base de datos

3. **Residuo controlado**:
   - splitEqual: Primera bill absorbe residuo
   - splitByItems: Última bill absorbe residuo

---

## Documentación en Código

### Comentarios Requeridos

```php
/**
 * Split order into N equal bills
 * 
 * ESTRATEGIA: División entera con absorción de residuo
 * - Usa floor() para división entera
 * - Primera bill absorbe residuo de redondeo
 * - Sin operaciones decimales
 * - Precisión: ±1 CLP entre bills
 * 
 * INVARIANTE: sum(bills.subtotal) = order.subtotal
 */
public function splitEqual(Order $order, int $parts): array
{
    // floor() para división entera
    $subtotalPerBill = (int) floor($order->subtotal / $parts);
    
    // Residuo absorbido por primera bill
    $subtotalResidue = $order->subtotal - ($subtotalPerBill * $parts);
    $bills[0]->subtotal = $subtotalPerBill + $subtotalResidue;
}

/**
 * Split order by item groups (proportional)
 * 
 * ESTRATEGIA: Cálculo proporcional racional → resultado entero
 * - Usa ratio decimal internamente: groupSubtotal / totalSubtotal
 * - Round() para convertir a entero
 * - Última bill absorbe residuo de redondeo
 * - Proporcional al valor de cada grupo
 * 
 * NOTA: Aunque usa división decimal internamente (ratio),
 *       el resultado final es siempre entero CLP.
 * 
 * INVARIANTE: sum(bills.tax) = order.tax
 */
public function splitByItems(Order $order, array $itemGroups): array
{
    // Ratio proporcional (decimal interno)
    $ratio = $groupSubtotal / $totalGroupedSubtotal;
    
    // Round para entero final
    $groupTax = (int) round($order->tax * $ratio);
    
    // Última bill absorbe residuo
    $lastBill->tax = $order->tax - array_sum($allGroupTaxes);
}

Comparación Semántica
Aspecto	splitEqual()	splitByItems()
Operaciones internas	Solo enteros (floor)	Decimales (ratio) + enteros (round)
Precisión	±1 CLP entre bills	Proporcional al valor
Residuo	Primera bill	Última bill
Caso de uso	Dividir cuenta equitativamente	Dividir por items consumidos
Complejidad	O(1)	O(n) donde n = grupos
Fairness	Todas iguales (±1 CLP)	Proporcional al consumo

Justificación Arquitectónica
¿Por qué splitByItems usa decimales internamente?
El cálculo proporcional requiere división:
ratio = groupSubtotal / totalGroupedSubtotal

Esto es inherentemente decimal porque:
7000 / 10000 = 0.7 (exacto)
3333 / 10000 = 0.3333... (periódico)
No se puede evitar la aritmética racional para calcular proporciones correctas.
¿Por qué el resultado final es entero?
Después del cálculo proporcional, aplicamos:
groupTax = round(orderTax * ratio)

Esto convierte el resultado racional a entero CLP, con un error máximo de ±0.5 CLP por bill.
¿Por qué absorber el residuo?
El redondeo introduce errores acumulados:

sum(round(orderTax * ratio_i)) ≠ orderTax

La diferencia (residuo) debe ser absorbida por una bill para mantener la invariante:

sum(all bills.tax) = order.tax

Documentación para Usuarios
En UI/UX
Opción 1: "Dividir en partes iguales"
Divide la cuenta en N partes iguales.
Cada persona paga aproximadamente lo mismo (±$1 CLP).

Opción 2: "Dividir por lo que consumió cada uno"
Cada persona paga proporcionalmente a lo que consumió.
El cálculo es exacto basado en los items de cada grupo.

Testing Strategy
Tests para splitEqual()
test('splitEqual conserva totales exactos', function () {
    $order = Order::factory()->create([
        'subtotal' => 10000,
        'tax' => 1900,
        'total' => 11900
    ]);
    
    $bills = BillService::splitEqual($order, 3);
    
    expect(array_sum(array_column($bills, 'subtotal')))->toBe(10000);
    expect(array_sum(array_column($bills, 'tax')))->toBe(1900);
    expect(array_sum(array_column($bills, 'total')))->toBe(11900);
});

Tests para splitByItems()
test('splitByItems conserva totales exactos', function () {
    $order = Order::factory()->create([
        'subtotal' => 10000,
        'tax' => 1900,
        'total' => 11900
    ]);
    
    $itemGroups = [
        ['items' => [1, 2], 'subtotal' => 7000],
        ['items' => [3, 4], 'subtotal' => 3000]
    ];
    
    $bills = BillService::splitByItems($order, $itemGroups);
    
    expect(array_sum(array_column($bills, 'subtotal')))->toBe(10000);
    expect(array_sum(array_column($bills, 'tax')))->toBe(1900);
    expect(array_sum(array_column($bills, 'total')))->toBe(11900);
});

test('splitByItems es proporcional', function () {
    $order = Order::factory()->create([
        'subtotal' => 10000,
        'tax' => 1900
    ]);
    
    $itemGroups = [
        ['items' => [1], 'subtotal' => 7000],  // 70%
        ['items' => [2], 'subtotal' => 3000]   // 30%
    ];
    
    $bills = BillService::splitByItems($order, $itemGroups);
    
    expect($bills[0]['tax'])->toBe(1330);  // 70% de 1900
    expect($bills[1]['tax'])->toBe(570);   // 30% de 1900
});

Referencias
ADR-020: Bills sincronizables
ADR-011: Modelo chileno (IVA incluido en precios)
ADR-018: Modelo monetario CLP entero
Última actualización: 2026-01-21
Autor: Equipo de Desarrollo

---

## Known Limitations

### splitByItems() usa float internamente

**Ubicación**: `app/Modules/Billing/Application/Services/BillingService.php`

**Descripción**:
El método `splitByItems()` usa aritmética de punto flotante internamente
para calcular proporciones:
```php
$ratio = $groupSubtotal / $totalGroupedSubtotal;  // float
$groupTax = (int) round($orderTax * $ratio);      // float → int

Prioridad: Baja (no afecta funcionalidad ni correctness)


### ✅ Decisión: No Bloquea el Freeze

**Razones:**
1. ✅ El código funciona correctamente
2. ✅ Las invariantes se preservan
3. ✅ No hay bugs funcionales
4. ✅ El impacto es puramente de "pureza arquitectónica"
5. ✅ El usuario explícitamente dice "no detendría el frontend"

### 📝 Acción Sugerida

Agregar esta observación a `docs/architecture/split-bill-semantics.md` como sección "Known Limitations":

```bash
cd ~/pos-restaurant

cat >> docs/architecture/split-bill-semantics.md << 'EOF'

---

## Known Limitations

### splitByItems() usa float internamente

**Ubicación**: `app/Modules/Billing/Application/Services/BillingService.php`

**Descripción**:
El método `splitByItems()` usa aritmética de punto flotante internamente
para calcular proporciones:
```php
$ratio = $groupSubtotal / $totalGroupedSubtotal;  // float
$groupTax = (int) round($orderTax * $ratio);      // float → int

Justificación:
El cálculo proporcional requiere división, que es inherentemente racional
El resultado final se convierte a entero con round()
La última bill absorbe el residuo de redondeo
Las invariantes financieras se preservan correctamente
Estado: 🟡 Hardening (no bloqueador)
Mejora futura:
Reemplazar con intdiv() para aritmética 100% entera:

$groupTax = intdiv($orderTax * $groupSubtotal, $totalGroupedSubtotal);

Prioridad: Baja (no afecta funcionalidad ni correctness)
Impacto:
✅ Resultado final: Entero CLP (sin cambio)
✅ Invariantes: Preservadas (sin cambio)
⚠️ Consistencia con ADR-018: Parcial (float interno vs 100% entero)
Referencia: ADR-018 (Integridad Financiera en Backend)
