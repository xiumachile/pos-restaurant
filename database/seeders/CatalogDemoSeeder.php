<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Modules\Companies\Domain\Entities\Company;
use Modules\Branches\Domain\Entities\Branch;
use Modules\Catalog\Domain\Entities\Category;
use Modules\Catalog\Domain\Entities\Product;
use Modules\Catalog\Domain\Entities\MenuItem;
use Modules\Catalog\Domain\Entities\MenuItemProduct;
use Modules\Catalog\Domain\Entities\MenuItemReplacementRule;

class CatalogDemoSeeder extends Seeder
{
    public function run(): void
    {
        $company = Company::where('tax_id', '76123456-7')->first();
        
        if (!$company) {
            $this->command->warn('⚠️  Empresa Demo no encontrada. Ejecuta BaseTenantSeeder primero.');
            return;
        }

        $branchCentro = Branch::where('company_id', $company->id)->where('code', 'SCL-001')->first();
        $branchProvidencia = Branch::where('company_id', $company->id)->where('code', 'SCL-002')->first();

        // ============================================
        // 1. CATEGORÍAS (bilingües)
        // ============================================
        $categories = [
            ['sort' => 1, 'es' => 'Entradas', 'zh' => '开胃菜'],
            ['sort' => 2, 'es' => 'Platos Principales', 'zh' => '主菜'],
            ['sort' => 3, 'es' => 'Hamburguesas', 'zh' => '汉堡'],
            ['sort' => 4, 'es' => 'Acompañamientos', 'zh' => '配菜'],
            ['sort' => 5, 'es' => 'Bebidas', 'zh' => '饮料'],
            ['sort' => 6, 'es' => 'Postres', 'zh' => '甜点'],
            ['sort' => 7, 'es' => 'Combos', 'zh' => '套餐'],
        ];

        $categoryMap = [];
        foreach ($categories as $cat) {
            $categoryMap[$cat['es']] = Category::updateOrCreate(
                ['company_id' => $company->id, 'name_translations->es' => $cat['es']],
                [
                    'branch_id' => null,
                    'name_translations' => ['es' => $cat['es'], 'zh' => $cat['zh']],
                    'sort_order' => $cat['sort'],
                    'is_active' => true,
                ]
            );
        }

        $this->command->info('✅ ' . count($categories) . ' categorías creadas');

        // ============================================
        // 2. PRODUCTOS SIMPLES
        // ============================================
        $products = [
            // Entradas
            ['sku' => 'ENT-001', 'cat' => 'Entradas', 'es' => 'Empanadas de Pino (3un)', 'zh' => '智利肉馅卷饼 (3个)', 'price' => 3500],
            ['sku' => 'ENT-002', 'cat' => 'Entradas', 'es' => 'Sopaipillas (6un)', 'zh' => '炸南瓜饼 (6个)', 'price' => 2800],
            
            // Platos Principales
            ['sku' => 'PRI-001', 'cat' => 'Platos Principales', 'es' => 'Lomo a lo Pobre', 'zh' => '智利牛排配薯条', 'price' => 9800],
            ['sku' => 'PRI-002', 'cat' => 'Platos Principales', 'es' => 'Pastel de Choclo', 'zh' => '智利玉米派', 'price' => 7500],
            ['sku' => 'PRI-003', 'cat' => 'Platos Principales', 'es' => 'Caldillo de Congrio', 'zh' => '智利鳗鱼汤', 'price' => 8900],
            
            // Hamburguesas
            ['sku' => 'HAM-001', 'cat' => 'Hamburguesas', 'es' => 'Hamburguesa Clásica', 'zh' => '经典汉堡', 'price' => 5500],
            ['sku' => 'HAM-002', 'cat' => 'Hamburguesas', 'es' => 'Hamburguesa Doble', 'zh' => '双层汉堡', 'price' => 7200],
            ['sku' => 'HAM-003', 'cat' => 'Hamburguesas', 'es' => 'Hamburguesa Vegetariana', 'zh' => '素食汉堡', 'price' => 6800],
            
            // Acompañamientos
            ['sku' => 'ACO-001', 'cat' => 'Acompañamientos', 'es' => 'Papas Fritas', 'zh' => '薯条', 'price' => 2500],
            ['sku' => 'ACO-002', 'cat' => 'Acompañamientos', 'es' => 'Aros de Cebolla', 'zh' => '洋葱圈', 'price' => 2800],
            ['sku' => 'ACO-003', 'cat' => 'Acompañamientos', 'es' => 'Ensalada Mixta', 'zh' => '混合沙拉', 'price' => 3200],
            
            // Bebidas
            ['sku' => 'BEB-001', 'cat' => 'Bebidas', 'es' => 'Coca-Cola 500ml', 'zh' => '可口可乐 500毫升', 'price' => 1500],
            ['sku' => 'BEB-002', 'cat' => 'Bebidas', 'es' => 'Sprite 500ml', 'zh' => '雪碧 500毫升', 'price' => 1500],
            ['sku' => 'BEB-003', 'cat' => 'Bebidas', 'es' => 'Jugo Natural', 'zh' => '天然果汁', 'price' => 2200],
            ['sku' => 'BEB-004', 'cat' => 'Bebidas', 'es' => 'Agua Mineral', 'zh' => '矿泉水', 'price' => 1000],
            ['sku' => 'BEB-005', 'cat' => 'Bebidas', 'es' => 'Cerveza Artesanal', 'zh' => '精酿啤酒', 'price' => 3800],
            
            // Postres
            ['sku' => 'POS-001', 'cat' => 'Postres', 'es' => 'Helado 2 Bolas', 'zh' => '两球冰淇淋', 'price' => 2800],
            ['sku' => 'POS-002', 'cat' => 'Postres', 'es' => 'Tres Leches', 'zh' => '三奶蛋糕', 'price' => 3500],
            ['sku' => 'POS-003', 'cat' => 'Postres', 'es' => 'Tiramisú', 'zh' => '提拉米苏', 'price' => 4200],
        ];

        $productMap = [];
        foreach ($products as $p) {
            $productMap[$p['sku']] = Product::updateOrCreate(
                ['company_id' => $company->id, 'sku' => $p['sku']],
                [
                    'branch_id' => null,
                    'category_id' => $categoryMap[$p['cat']]->id,
                    'name_translations' => ['es' => $p['es'], 'zh' => $p['zh']],
                    'description_translations' => [
                        'es' => 'Descripción de ' . $p['es'],
                        'zh' => $p['zh'] . '的描述',
                    ],
                    'base_price' => $p['price'],
                    'tax_rate' => 19.00,
                    'is_combo' => false,
                    'is_active' => true,
                ]
            );
        }

        $this->command->info('✅ ' . count($products) . ' productos simples creados');

        // ============================================
        // 3. COMBOS (Menús predefinidos)
        // ============================================
        $combos = [
            [
                'sku' => 'COMBO-001',
                'es' => 'Combo Hamburguesa Clásica',
                'zh' => '经典汉堡套餐',
                'base_price' => 8500, // 5500 + 2500 + 1500 - 1000 descuento
                'discount' => 1000,
                'components' => [
                    ['sku' => 'HAM-001', 'qty' => 1, 'substitutable' => false],
                    ['sku' => 'ACO-001', 'qty' => 1, 'substitutable' => true],
                    ['sku' => 'BEB-001', 'qty' => 1, 'substitutable' => true],
                ],
            ],
            [
                'sku' => 'COMBO-002',
                'es' => 'Combo Hamburguesa Doble',
                'zh' => '双层汉堡套餐',
                'base_price' => 10200, // 7200 + 2500 + 1500 - 1000
                'discount' => 1000,
                'components' => [
                    ['sku' => 'HAM-002', 'qty' => 1, 'substitutable' => false],
                    ['sku' => 'ACO-001', 'qty' => 1, 'substitutable' => true],
                    ['sku' => 'BEB-001', 'qty' => 1, 'substitutable' => true],
                ],
            ],
            [
                'sku' => 'COMBO-003',
                'es' => 'Combo Familiar (4 personas)',
                'zh' => '家庭套餐 (4人)',
                'base_price' => 28000, // 4 hamburguesas + 2 papas grandes + 4 bebidas
                'discount' => 3500,
                'components' => [
                    ['sku' => 'HAM-001', 'qty' => 4, 'substitutable' => false],
                    ['sku' => 'ACO-001', 'qty' => 2, 'substitutable' => true],
                    ['sku' => 'BEB-001', 'qty' => 4, 'substitutable' => true],
                ],
            ],
        ];

        foreach ($combos as $comboData) {
            // Crear producto tipo combo
            $comboProduct = Product::updateOrCreate(
                ['company_id' => $company->id, 'sku' => $comboData['sku']],
                [
                    'branch_id' => null,
                    'category_id' => $categoryMap['Combos']->id,
                    'name_translations' => ['es' => $comboData['es'], 'zh' => $comboData['zh']],
                    'description_translations' => [
                        'es' => 'Combo que incluye ' . count($comboData['components']) . ' productos',
                        'zh' => '包含 ' . count($comboData['components']) . ' 种产品的套餐',
                    ],
                    'base_price' => 0, // El precio está en el MenuItem
                    'tax_rate' => 19.00,
                    'is_combo' => true,
                    'is_active' => true,
                ]
            );
            // ✅ AGREGAR ESTA LÍNEA:
    $productMap[$comboData['sku']] = $comboProduct;

    // Crear MenuItem
    $menuItem = MenuItem::updateOrCreate(
        ['company_id' => $company->id, 'product_id' => $comboProduct->id, 'branch_id' => null],
        [
            'base_price' => $comboData['base_price'],
            'discount_amount' => $comboData['discount'],
            'is_active' => true,
        ]
    );

            // Crear MenuItem
            $menuItem = MenuItem::updateOrCreate(
                ['company_id' => $company->id, 'product_id' => $comboProduct->id, 'branch_id' => null],
                [
                    'base_price' => $comboData['base_price'],
                    'discount_amount' => $comboData['discount'],
                    'is_active' => true,
                ]
            );

            // Crear componentes
            foreach ($comboData['components'] as $comp) {
                MenuItemProduct::updateOrCreate(
                    ['menu_item_id' => $menuItem->id, 'product_id' => $productMap[$comp['sku']]->id],
                    [
                        'quantity' => $comp['qty'],
                        'is_substitutable' => $comp['substitutable'],
                    ]
                );
            }

            $this->command->info("  ✅ Combo {$comboData['sku']}: {$comboData['es']}");
        }

        // ============================================
        // 4. REGLAS DE SUSTITUCIÓN
        // ============================================
        $menuItemCombo001 = MenuItem::where('product_id', $productMap['COMBO-001']->id)->first();

        if (!$menuItemCombo001) {
            $this->command->warn('⚠️  MenuItem del COMBO-001 no encontrado. Saltando reglas de sustitución.');
        } else {
            // Regla: bebida puede cambiarse por cualquier bebida
            MenuItemReplacementRule::updateOrCreate(
                [
                    'company_id' => $company->id,
                    'menu_item_id' => $menuItemCombo001->id,
                    'target_product_id' => $productMap['BEB-001']->id,
                    'branch_id' => null,
                ],
                [
                    'rule_type' => 'allowed_category',
                    'allowed_category_id' => $categoryMap['Bebidas']->id,
                    'max_price_delta' => 2000,
                    'requires_authorization' => false,
                    'priority' => 1,
                    'is_active' => true,
                    'description_translations' => [
                        'es' => 'Permite cambiar Coca-Cola por cualquier bebida',
                        'zh' => '允许将可口可乐换成任何饮料',
                    ],
                ]
            );

            // Regla: papas pueden cambiarse por cualquier acompañamiento
            MenuItemReplacementRule::updateOrCreate(
                [
                    'company_id' => $company->id,
                    'menu_item_id' => $menuItemCombo001->id,
                    'target_product_id' => $productMap['ACO-001']->id,
                    'branch_id' => null,
                ],
                [
                    'rule_type' => 'allowed_category',
                    'allowed_category_id' => $categoryMap['Acompañamientos']->id,
                    'max_price_delta' => 1500,
                    'requires_authorization' => false,
                    'priority' => 1,
                    'is_active' => true,
                    'description_translations' => [
                        'es' => 'Permite cambiar papas fritas por cualquier acompañamiento',
                        'zh' => '允许将薯条换成任何配菜',
                    ],
                ]
            );

            $this->command->info('✅ Reglas de sustitución creadas para combos');
        }

        // ============================================
        // Resumen final
        // ============================================
        $this->command->info('');
        $this->command->info('=== RESUMEN CATÁLOGO DEMO ===');
        $this->command->info('Categorías:  ' . Category::where('company_id', $company->id)->count());
        $this->command->info('Productos:   ' . Product::where('company_id', $company->id)->where('is_combo', false)->count());
        $this->command->info('Combos:      ' . Product::where('company_id', $company->id)->where('is_combo', true)->count());
        $this->command->info('MenuItems:   ' . MenuItem::where('company_id', $company->id)->count());
        $this->command->info('Reglas:      ' . MenuItemReplacementRule::where('company_id', $company->id)->count());
    }
}
