
## Refactor de "God Components" (Prioridad Alta antes de nuevas features)
Los siguientes archivos han crecido demasiado (>650 líneas) y deben dividirse antes de agregar la nueva funcionalidad de Cartas/Catálogo:
1. **`ProductsTab.tsx` (703 líneas)**: Dividir en subcomponentes (ProductList, ProductFilters, ProductForm).
2. **`MenusTab.tsx` (654 líneas)**: Separar lógica de gestión de menús de la UI.
3. **`SyncEngine.ts` (766 líneas)**: Extraer estrategias de sync por entidad (OrderSync, PaymentSync, CatalogSync).
4. **`CashCloseWizard.tsx` (777 líneas)**: Separar pasos del wizard en componentes individuales.
5. **`schema.ts` (1124 líneas)**: Dividir migraciones en archivos separados por versión/módulo.

## Limpieza de Logs
- Se eliminaron archivos de depuración (`sqlite-write-map-*.txt`).
- Se recomienda configurar ESLint `no-console` con excepciones solo para errores (`console.error`) en producción.
