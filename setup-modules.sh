#!/bin/bash
# =============================================================
# FASE 0 - Generador de estructura modular
# Crea los módulos según sección 7.1 de la arquitectura
# y la estructura de capas según sección 7.2
# =============================================================

set -e

# Módulos definidos en la sección 7.1
MODULES=(
    "Identity"
    "Companies"
    "Branches"
    "Catalog"
    "Tables"
    "Orders"
    "Kitchen"
    "Billing"
    "Payments"
    "Cashier"
    "Inventory"
    "Customers"
    "Delivery"
    "Reports"
    "Integrations"
    "I18n"
)

# Capas internas según sección 7.2
LAYERS=(
    "Interfaces/Controllers"
    "Interfaces/Resources"
    "Interfaces/Requests"
    "Interfaces/Listeners"
    "Application/UseCases"
    "Application/Commands"
    "Application/Queries"
    "Application/DTOs"
    "Domain/Aggregates"
    "Domain/Entities"
    "Domain/ValueObjects"
    "Domain/Policies"
    "Domain/Events"
    "Domain/Repositories"
    "Infrastructure/Eloquent"
    "Infrastructure/Redis"
    "Infrastructure/Reverb"
    "Infrastructure/Queue"
    "Infrastructure/ExternalApis"
    "Infrastructure/Localization"
    "Routes"
    "Database/Migrations"
    "Database/Seeders"
    "Database/Factories"
    "Tests/Unit"
    "Tests/Feature"
)

echo "🔧 Creando estructura modular..."

for MODULE in "${MODULES[@]}"; do
    echo "  📦 Modules/$MODULE"
    for LAYER in "${LAYERS[@]}"; do
        mkdir -p "Modules/$MODULE/$LAYER"
        touch "Modules/$MODULE/$LAYER/.gitkeep"
    done

    # README por módulo
    cat > "Modules/$MODULE/README.md" <<EOF
# Módulo: $MODULE

Módulo del sistema POS según arquitectura v1.0 (sección 7.1).

## Estructura de capas (sección 7.2)

- \`Interfaces/\`   → Controllers, Resources, Requests, Listeners
- \`Application/\`  → UseCases, Commands, Queries, DTOs
- \`Domain/\`       → Aggregates, Entities, ValueObjects, Policies, Events, Repositories
- \`Infrastructure/\` → Eloquent, Redis, Reverb, Queue, APIs externas, Localización
- \`Routes/\`       → Definición de rutas del módulo
- \`Database/\`     → Migraciones, Seeders, Factories
- \`Tests/\`        → Unit y Feature

## Convenciones
- Toda entidad operativa incluye: company_id, branch_id (sección 8.2).
- Traducciones dinámicas con JSONB (sección 9.5).
- Idempotencia en mutaciones críticas (sección 10.7).
EOF
done

# ---------------------------------------------------------
# Estructura Shared (capas transversales)
# ---------------------------------------------------------
echo "  🧩 Shared/"
SHARED_LAYERS=(
    "Domain/Traits"
    "Domain/Scopes"
    "Domain/ValueObjects"
    "Domain/Contracts"
    "Application"
    "Infrastructure"
    "Http/Middleware"
    "Support"
)
for LAYER in "${SHARED_LAYERS[@]}"; do
    mkdir -p "Shared/$LAYER"
    touch "Shared/$LAYER/.gitkeep"
done

cat > "Shared/README.md" <<EOF
# Shared (Transversal)

Código compartido entre módulos según arquitectura v1.0.

## Contenido
- \`Domain/Traits/\`   → HasUuid, MultiTenant, Syncable, Auditable
- \`Domain/Scopes/\`   → CompanyScope, BranchScope (aislamiento multiempresa)
- \`Domain/Contracts/\` → Interfaces compartidas
- \`Http/Middleware/\`  → SetCompanyContext, SetBranchContext, ResolveLocale
- \`Support/\`          → Helpers (TranslationResolver, etc.)
EOF

echo ""
echo "✅ Estructura modular creada correctamente."
echo "   Módulos: ${#MODULES[@]}"
echo ""
echo "👉 Siguiente paso: composer dump-autoload"
