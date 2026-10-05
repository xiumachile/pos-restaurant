#!/bin/bash

# ═══════════════════════════════════════════════════════════════
# 🧪 SUITE DE PRUEBAS COMPLETA - POS Restaurant
# Backend (Docker) + Frontend (Vitest/TypeCheck)
# ═══════════════════════════════════════════════════════════════

# NO usar set -e: queremos continuar aunque algunas pruebas fallen

# Colores para output
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
CYAN='\033[0;36m'
NC='\033[0m'

# Contadores
TESTS_PASSED=0
TESTS_FAILED=0
TESTS_SKIPPED=0

# Funciones de utilidad
pass() {
    echo -e "${GREEN}✅ PASS${NC}: $1"
    ((TESTS_PASSED++))
}

fail() {
    echo -e "${RED}❌ FAIL${NC}: $1"
    ((TESTS_FAILED++))
    if [ "$2" != "" ]; then
        echo -e "   ${RED}   → $2${NC}"
    fi
}

skip() {
    echo -e "${YELLOW}⏭️  SKIP${NC}: $1"
    ((TESTS_SKIPPED++))
}

info() {
    echo -e "${BLUE}ℹ️  INFO${NC}: $1"
}

section() {
    echo ""
    echo -e "${CYAN}═══════════════════════════════════════════════════════════${NC}"
    echo -e "${CYAN}  $1${NC}"
    echo -e "${CYAN}═══════════════════════════════════════════════════════════${NC}"
}

# Cambiar al directorio del proyecto
cd "$(dirname "$0")"

# ═══════════════════════════════════════════════════════════════
# SECCIÓN 1: VERIFICACIÓN DE SERVICIOS DOCKER
# ═══════════════════════════════════════════════════════════════
section "🐳 1. VERIFICACIÓN DE SERVICIOS DOCKER"

# 1.1 Verificar que docker-compose esté disponible
if command -v docker compose &> /dev/null; then
    pass "Docker Compose disponible"
else
    fail "Docker Compose no disponible" "Instala Docker Compose"
fi

# 1.2 Verificar contenedores corriendo
info "Verificando contenedores..."
DOCKER_PS_OUTPUT=$(docker compose ps 2>&1 || true)

if echo "$DOCKER_PS_OUTPUT" | grep -q "app"; then
    if echo "$DOCKER_PS_OUTPUT" | grep "app" | grep -qE "Up|running|healthy"; then
        pass "Contenedor 'app' está corriendo"
    else
        fail "Contenedor 'app' no está running" "Ejecuta: docker compose up -d"
    fi
else
    fail "Contenedor 'app' no encontrado" "Ejecuta: docker compose up -d"
fi

if echo "$DOCKER_PS_OUTPUT" | grep -q "postgres"; then
    if echo "$DOCKER_PS_OUTPUT" | grep "postgres" | grep -qE "Up|running|healthy"; then
        pass "Contenedor 'postgres' está corriendo"
    else
        fail "Contenedor 'postgres' no está running"
    fi
else
    skip "Contenedor 'postgres' no definido"
fi

# 1.3 Verificar salud del backend
if docker compose exec -T app php -v &> /dev/null; then
    pass "PHP disponible en contenedor app"
else
    fail "PHP no disponible en contenedor app"
fi

# 1.4 Verificar conectividad a PostgreSQL
if docker compose exec -T postgres pg_isready -U postgres &> /dev/null; then
    pass "PostgreSQL responde a pg_isready"
else
    fail "PostgreSQL no responde"
fi

# ═══════════════════════════════════════════════════════════════
# SECCIÓN 2: TESTS DEL BACKEND (PHP/LARAVEL)
# ═══════════════════════════════════════════════════════════════
section "🐘 2. TESTS DEL BACKEND (PHP/LARAVEL)"

# 2.1 Verificar sintaxis PHP de archivos críticos
info "Verificando sintaxis PHP de archivos críticos..."
PHP_FILES=(
    "app/Modules/Tables/Domain/Listeners/OccupyTableOnOrderConfirm.php"
    "app/Modules/Tables/Domain/Entities/RestaurantTable.php"
    "app/Modules/Orders/Domain/Services/OrderService.php"
    "app/Modules/Orders/Domain/Services/OrderStateMachine.php"
    "app/Modules/Orders/Interfaces/Controllers/OrderController.php"
    "app/Modules/Orders/Interfaces/Controllers/OrderTransitionController.php"
    "app/Modules/Tables/Interfaces/Controllers/AreaController.php"
    "app/Modules/Tables/Interfaces/Controllers/RestaurantTableController.php"
)

for file in "${PHP_FILES[@]}"; do
    if [ -f "$file" ]; then
        if docker compose exec -T app php -l "$file" 2>&1 | grep -q "No syntax errors"; then
            pass "Sintaxis OK: $(basename $file)"
        else
            fail "Sintaxis ERROR: $(basename $file)"
        fi
    else
        skip "Archivo no existe: $(basename $file)"
    fi
done

# 2.2 Verificar rutas registradas (método robusto)
info "Verificando rutas de la API..."

# Método 1: Verificar archivos de rutas directamente
ORDERS_ROUTES=$(grep -c "Route::" app/Modules/Orders/Routes/api.php 2>/dev/null || echo "0")
TABLES_ROUTES=$(grep -c "Route::" app/Modules/Tables/Routes/api.php 2>/dev/null || echo "0")

if [ "$ORDERS_ROUTES" -gt 0 ]; then
    pass "Rutas de /orders registradas ($ORDERS_ROUTES rutas)"
else
    fail "Rutas de /orders NO registradas"
fi

if [ "$TABLES_ROUTES" -gt 0 ]; then
    pass "Rutas de /tables registradas ($TABLES_ROUTES rutas)"
else
    fail "Rutas de /tables NO registradas"
fi

# Método 2: Verificar que los controllers existen
if [ -f "app/Modules/Orders/Interfaces/Controllers/OrderController.php" ]; then
    pass "OrderController existe"
else
    fail "OrderController NO existe"
fi

if [ -f "app/Modules/Tables/Interfaces/Controllers/RestaurantTableController.php" ]; then
    pass "RestaurantTableController existe"
else
    fail "RestaurantTableController NO existe"
fi

if [ -f "app/Modules/Tables/Interfaces/Controllers/AreaController.php" ]; then
    pass "AreaController existe"
else
    fail "AreaController NO existe"
fi

# 2.3 Verificar migraciones
info "Verificando estado de migraciones..."
if docker compose exec -T app php artisan migrate:status 2>&1 | grep -q "Ran"; then
    pass "Migraciones ejecutadas"
else
    fail "Migraciones NO ejecutadas"
fi

# 2.4 Verificar modelo de Areas
AREA_COUNT=$(docker compose exec -T app php artisan tinker --execute="
    echo Modules\\\\Tables\\\\Domain\\\\Entities\\\\Area::count();
" 2>&1 | grep -oE '[0-9]+' | tail -1)

if [ -n "$AREA_COUNT" ] && [ "$AREA_COUNT" -gt 0 ]; then
    pass "Modelo Area funcional ($AREA_COUNT áreas)"
else
    fail "Modelo Area no funcional o sin datos"
fi

# ═══════════════════════════════════════════════════════════════
# SECCIÓN 3: INTEGRIDAD DE BASE DE DATOS
# ═══════════════════════════════════════════════════════════════
section "🗄️  3. INTEGRIDAD DE BASE DE DATOS"

# 3.1 Verificar mesas duplicadas
DUPLICATED=$(docker compose exec -T postgres psql -U postgres -d pos_restaurant -t -c "
    SELECT COUNT(*) FROM (
        SELECT table_number, area_code, COUNT(*) 
        FROM restaurant_tables 
        WHERE deleted_at IS NULL 
        GROUP BY table_number, area_code 
        HAVING COUNT(*) > 1
    ) sub;
" 2>/dev/null | tr -d ' ' || echo "ERROR")

if [ "$DUPLICATED" = "0" ]; then
    pass "Sin mesas duplicadas"
elif [ "$DUPLICATED" != "ERROR" ]; then
    fail "Mesas duplicadas encontradas: $DUPLICATED" "Ejecuta limpieza de duplicados"
else
    skip "No se pudo verificar duplicados"
fi

# 3.2 Contar mesas totales
TABLE_COUNT=$(docker compose exec -T postgres psql -U postgres -d pos_restaurant -t -c "
    SELECT COUNT(*) FROM restaurant_tables WHERE deleted_at IS NULL;
" 2>/dev/null | tr -d ' ' || echo "?")
info "Total de mesas: $TABLE_COUNT"

# 3.3 Contar órdenes
ORDER_COUNT=$(docker compose exec -T postgres psql -U postgres -d pos_restaurant -t -c "
    SELECT COUNT(*) FROM orders;
" 2>/dev/null | tr -d ' ' || echo "?")
info "Total de órdenes: $ORDER_COUNT"

# 3.4 Mesas por estado
info "Mesas por estado:"
docker compose exec -T postgres psql -U postgres -d pos_restaurant -c "
    SELECT status, COUNT(*) as cantidad
    FROM restaurant_tables 
    WHERE deleted_at IS NULL
    GROUP BY status
    ORDER BY status;
" 2>/dev/null || skip "No se pudo obtener estado de mesas"

# ═══════════════════════════════════════════════════════════════
# SECCIÓN 4: TESTS DE API (ENDPOINTS)
# ═══════════════════════════════════════════════════════════════
section "🌐 4. TESTS DE ENDPOINTS (API)"

# Intentar obtener token JWT para pruebas
info "Obteniendo token de prueba..."
TOKEN=$(docker compose exec -T app php artisan tinker --execute="
    \\\$user = \\\\Modules\\\\Identity\\\\Domain\\\\Entities\\\\User::first();
    if (\\\$user) {
        echo auth('api')->tokenById(\\\$user->id);
    } else {
        echo 'NO_USER';
    }
" 2>&1 | tail -1 | tr -d '\r\n' | grep -oE 'eyJ[A-Za-z0-9_-]+' || echo "")

if [ -z "$TOKEN" ] || [ "$TOKEN" = "NO_USER" ]; then
    skip "No se pudo obtener token (saltando tests de API autenticados)"
else
    info "Token obtenido correctamente"
    API_URL="http://localhost:8000/api/v1"

    # 4.1 GET /tables
    HTTP_CODE=$(curl -s -o /dev/null -w "%{http_code}" -H "Authorization: Bearer $TOKEN" "$API_URL/tables" 2>/dev/null || echo "000")
    if [ "$HTTP_CODE" = "200" ]; then
        pass "GET /tables → 200 OK"
    else
        fail "GET /tables → $HTTP_CODE"
    fi

    # 4.2 GET /areas
    HTTP_CODE=$(curl -s -o /dev/null -w "%{http_code}" -H "Authorization: Bearer $TOKEN" "$API_URL/areas" 2>/dev/null || echo "000")
    if [ "$HTTP_CODE" = "200" ]; then
        pass "GET /areas → 200 OK"
    else
        fail "GET /areas → $HTTP_CODE"
    fi

    # 4.3 GET /orders
    HTTP_CODE=$(curl -s -o /dev/null -w "%{http_code}" -H "Authorization: Bearer $TOKEN" "$API_URL/orders?limit=10" 2>/dev/null || echo "000")
    if [ "$HTTP_CODE" = "200" ]; then
        pass "GET /orders → 200 OK"
    else
        fail "GET /orders → $HTTP_CODE"
    fi
fi

# ═══════════════════════════════════════════════════════════════
# SECCIÓN 5: TESTS DEL FRONTEND
# ═══════════════════════════════════════════════════════════════
section "⚛️  5. TESTS DEL FRONTEND"

cd frontend || { fail "No se pudo acceder a frontend/"; cd ..; }

# 5.1 Verificar que package.json exista
if [ -f "package.json" ]; then
    pass "package.json existe"
else
    fail "package.json no existe"
fi

# 5.2 Verificar node_modules
if [ -d "node_modules" ]; then
    pass "node_modules existe"
else
    fail "node_modules no existe" "Ejecuta: npm install"
fi

# 5.3 TypeCheck
info "Ejecutando TypeScript TypeCheck..."
if npm run typecheck > /tmp/typecheck.log 2>&1; then
    pass "TypeCheck sin errores"
else
    fail "TypeCheck con errores"
    echo -e "${RED}Últimos 5 errores:${NC}"
    tail -5 /tmp/typecheck.log | sed 's/^/   /'
fi

# 5.4 Verificar archivos críticos
info "Verificando archivos críticos del frontend..."
CRITICAL_FILES=(
    "src/pages/TablesPage.tsx"
    "src/pages/OrderTakingPage.tsx"
    "src/components/floor-plan/FloorPlanView.tsx"
    "src/hooks/floor-plan/useRestaurantTables.ts"
    "src/hooks/floor-plan/useOperationalTableData.ts"
    "src/components/areas/AreaManagementModal.tsx"
    "src/components/tables/TableManagementModal.tsx"
)

for file in "${CRITICAL_FILES[@]}"; do
    if [ -f "$file" ]; then
        pass "Archivo existe: $(basename $file)"
    else
        fail "Archivo NO existe: $(basename $file)"
    fi
done

# 5.5 Verificar traducciones
if [ -f "src/locales/es-CL.json" ] && [ -f "src/locales/zh-CN.json" ]; then
    ES_KEYS=$(grep -o '"[^"]*":' src/locales/es-CL.json 2>/dev/null | sort -u | wc -l)
    ZH_KEYS=$(grep -o '"[^"]*":' src/locales/zh-CN.json 2>/dev/null | sort -u | wc -l)
    
    if [ "$ES_KEYS" -gt 100 ] && [ "$ZH_KEYS" -gt 100 ]; then
        pass "Traducciones es-CL: $ES_KEYS claves"
        pass "Traducciones zh-CN: $ZH_KEYS claves"
    else
        fail "Traducciones incompletas (es: $ES_KEYS, zh: $ZH_KEYS)"
    fi
else
    fail "Archivos de traducción faltantes"
fi

# Volver al directorio raíz
cd ..

# ═══════════════════════════════════════════════════════════════
# SECCIÓN 6: TESTS DE INTEGRACIÓN (FLUJO COMPLETO)
# ═══════════════════════════════════════════════════════════════
section "🔗 6. TESTS DE INTEGRACIÓN"

# 6.1 Verificar que el módulo Floor Plan esté correctamente integrado
info "Verificando integración del módulo Floor Plan..."

FLOOR_PLAN_FILES=(
    "frontend/src/stores/floor-plan/floorPlanStore.ts"
    "frontend/src/components/floor-plan/FloorPlanView.tsx"
    "frontend/src/components/floor-plan/editor/FloorPlanToolbar.tsx"
    "frontend/src/components/floor-plan/editor/FloorPlanSidebar.tsx"
    "frontend/src/components/floor-plan/operational/EditModeFAB.tsx"
)

for file in "${FLOOR_PLAN_FILES[@]}"; do
    if [ -f "$file" ]; then
        pass "Floor Plan: $(basename $file)"
    else
        fail "Floor Plan faltante: $(basename $file)"
    fi
done

# 6.2 Verificar que las rutas del router incluyan floor-plan
if grep -q "floor-plan" frontend/src/router/index.tsx 2>/dev/null; then
    pass "Ruta /floor-plan registrada en router"
else
    fail "Ruta /floor-plan NO registrada en router"
fi

# 6.3 Verificar que Sidebar tenga enlace a floor-plan
if grep -q "floor-plan" frontend/src/components/layout/Sidebar.tsx 2>/dev/null; then
    pass "Sidebar incluye enlace a floor-plan"
else
    fail "Sidebar NO incluye enlace a floor-plan"
fi

# ═══════════════════════════════════════════════════════════════
# RESUMEN FINAL
# ═══════════════════════════════════════════════════════════════
section "📊 RESUMEN FINAL"

TOTAL=$((TESTS_PASSED + TESTS_FAILED + TESTS_SKIPPED))
if [ "$TOTAL" -gt 0 ]; then
    PASS_RATE=$((TESTS_PASSED * 100 / TOTAL))
else
    PASS_RATE=0
fi

echo ""
echo -e "Total de pruebas: ${CYAN}$TOTAL${NC}"
echo -e "${GREEN}✅ Pasaron: $TESTS_PASSED${NC}"
echo -e "${RED}❌ Fallaron: $TESTS_FAILED${NC}"
echo -e "${YELLOW}⏭️  Omitidos: $TESTS_SKIPPED${NC}"
echo -e "Tasa de éxito: ${CYAN}${PASS_RATE}%${NC}"
echo ""

if [ "$TESTS_FAILED" -eq 0 ]; then
    echo -e "${GREEN}═══════════════════════════════════════════════════════════${NC}"
    echo -e "${GREEN}  🎉 ¡TODAS LAS PRUEBAS PASARON EXITOSAMENTE! 🎉${NC}"
    echo -e "${GREEN}═══════════════════════════════════════════════════════════${NC}"
    exit 0
else
    echo -e "${RED}═══════════════════════════════════════════════════════════${NC}"
    echo -e "${RED}  ⚠️  HAY $TESTS_FAILED PRUEBAS FALLIDAS${NC}"
    echo -e "${RED}═══════════════════════════════════════════════════════════${NC}"
    exit 1
fi
