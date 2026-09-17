# ADR-018: Monetary Values as Integer (CLP)

## Contexto
Chile usa CLP sin centavos. DECIMAL genera problemas de redondeo
acumulativo y comparaciones frágiles con epsilon.

## Decisión
Todos los campos monetarios son INTEGER (pesos chilenos).
Cálculos usan aritmética entera sin round(..., 2).

## Consecuencias
- Invariants exactos (net + tax = gross)
- Sin comparaciones con tolerancia
- Simplificación de lógica de split bill
