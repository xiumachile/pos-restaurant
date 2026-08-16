<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Modules\Catalog\Domain\Entities\Category;
use Modules\Catalog\Domain\Entities\Product;
use Modules\Companies\Domain\Entities\Company;
use Modules\Branches\Domain\Entities\Branch;

class CatalogSeeder extends Seeder
{
    public function run(): void
    {
        $company = Company::withoutGlobalScopes()
            ->where('trade_name', 'Wok & Mesa')
            ->first();

        if (!$company) {
            $this->command->error('Compañía Wok & Mesa no existe.');
            return;
        }

        $branch = Branch::withoutGlobalScopes()
            ->where('company_id', $company->id)
            ->where('code', 'MAIN')
            ->first();

        if (!$branch) {
            $this->command->error('Branch MAIN no existe.');
            return;
        }

        $existingCategories = Category::withoutGlobalScopes()
            ->where('company_id', $company->id)
            ->count();

        if ($existingCategories > 0) {
            $this->command->info("Ya existen {$existingCategories} categorías. Saltando.");
            return;
        }

        // Categorías
        $categories = [
            ['name_es' => 'Entradas', 'name_zh' => '开胃菜', 'sort_order' => 1],
            ['name_es' => 'Platos Fuertes', 'name_zh' => '主菜', 'sort_order' => 2],
            ['name_es' => 'Arroces', 'name_zh' => '米饭', 'sort_order' => 3],
            ['name_es' => 'Fideos', 'name_zh' => '面条', 'sort_order' => 4],
            ['name_es' => 'Sushi', 'name_zh' => '寿司', 'sort_order' => 5],
            ['name_es' => 'Postres', 'name_zh' => '甜点', 'sort_order' => 6],
            ['name_es' => 'Bebidas', 'name_zh' => '饮料', 'sort_order' => 7],
        ];

        $createdCategories = [];
        foreach ($categories as $catData) {
            $category = Category::withoutGlobalScopes()->create([
                'company_id' => $company->id,
                'branch_id' => $branch->id,
                'name_translations' => [
                    'es' => $catData['name_es'],
                    'zh' => $catData['name_zh'],
                ],
                'sort_order' => $catData['sort_order'],
                'is_active' => true,
            ]);
            $createdCategories[$catData['name_es']] = $category;
            $this->command->info("Categoría: {$catData['name_es']} ({$catData['name_zh']})");
        }

        // Productos
        $products = [
            // Entradas
            ['sku' => 'ENT-001', 'name_es' => 'Rollitos Primavera', 'name_zh' => '春卷', 'price' => 4500, 'category' => 'Entradas'],
            ['sku' => 'ENT-002', 'name_es' => 'Empanadas de Pollo', 'name_zh' => '鸡肉饺子', 'price' => 3900, 'category' => 'Entradas'],
            ['sku' => 'ENT-003', 'name_es' => 'Sopa Wantán', 'name_zh' => '馄饨汤', 'price' => 4200, 'category' => 'Entradas'],
            ['sku' => 'ENT-004', 'name_es' => 'Edamames', 'name_zh' => '毛豆', 'price' => 3500, 'category' => 'Entradas'],

            // Platos Fuertes
            ['sku' => 'FRT-001', 'name_es' => 'Pad Thai', 'name_zh' => '泰式炒河粉', 'price' => 8500, 'category' => 'Platos Fuertes'],
            ['sku' => 'FRT-002', 'name_es' => 'Pollo Kung Pao', 'name_zh' => '宫保鸡丁', 'price' => 9200, 'category' => 'Platos Fuertes'],
            ['sku' => 'FRT-003', 'name_es' => 'Pato Pekinés', 'name_zh' => '北京烤鸭', 'price' => 14500, 'category' => 'Platos Fuertes'],
            ['sku' => 'FRT-004', 'name_es' => 'Cerdo Agridulce', 'name_zh' => '糖醋里脊', 'price' => 8900, 'category' => 'Platos Fuertes'],

            // Arroces
            ['sku' => 'ARR-001', 'name_es' => 'Arroz Chaufán', 'name_zh' => '炒饭', 'price' => 6500, 'category' => 'Arroces'],
            ['sku' => 'ARR-002', 'name_es' => 'Arroz con Pollo', 'name_zh' => '鸡肉饭', 'price' => 7200, 'category' => 'Arroces'],
            ['sku' => 'ARR-003', 'name_es' => 'Arroz Frito Especial', 'name_zh' => '特别炒饭', 'price' => 7800, 'category' => 'Arroces'],

            // Fideos
            ['sku' => 'FID-001', 'name_es' => 'Fideos Chow Mein', 'name_zh' => '炒面', 'price' => 6200, 'category' => 'Fideos'],
            ['sku' => 'FID-002', 'name_es' => 'Ramen Tonkotsu', 'name_zh' => '豚骨拉面', 'price' => 8900, 'category' => 'Fideos'],
            ['sku' => 'FID-003', 'name_es' => 'Udon con Verduras', 'name_zh' => '蔬菜乌冬面', 'price' => 7500, 'category' => 'Fideos'],

            // Sushi
            ['sku' => 'SUS-001', 'name_es' => 'California Roll', 'name_zh' => '加州卷', 'price' => 6900, 'category' => 'Sushi'],
            ['sku' => 'SUS-002', 'name_es' => 'Nigiri Salmón (6pz)', 'name_zh' => '三文鱼握寿司', 'price' => 8500, 'category' => 'Sushi'],
            ['sku' => 'SUS-003', 'name_es' => 'Sashimi Mixto', 'name_zh' => '综合刺身', 'price' => 12500, 'category' => 'Sushi'],
            ['sku' => 'SUS-004', 'name_es' => 'Dragon Roll', 'name_zh' => '龙卷', 'price' => 9800, 'category' => 'Sushi'],

            // Postres
            ['sku' => 'POS-001', 'name_es' => 'Mochi (3pz)', 'name_zh' => '麻糬', 'price' => 3500, 'category' => 'Postres'],
            ['sku' => 'POS-002', 'name_es' => 'Helado de Matcha', 'name_zh' => '抹茶冰淇淋', 'price' => 2900, 'category' => 'Postres'],
            ['sku' => 'POS-003', 'name_es' => 'Torta de Mango', 'name_zh' => '芒果蛋糕', 'price' => 4200, 'category' => 'Postres'],

            // Bebidas
            ['sku' => 'BEB-001', 'name_es' => 'Té Verde', 'name_zh' => '绿茶', 'price' => 2000, 'category' => 'Bebidas'],
            ['sku' => 'BEB-002', 'name_es' => 'Té de Jazmín', 'name_zh' => '茉莉花茶', 'price' => 2200, 'category' => 'Bebidas'],
            ['sku' => 'BEB-003', 'name_es' => 'Cerveza China', 'name_zh' => '中国啤酒', 'price' => 3500, 'category' => 'Bebidas'],
            ['sku' => 'BEB-004', 'name_es' => 'Sake', 'name_zh' => '清酒', 'price' => 4500, 'category' => 'Bebidas'],
        ];

        foreach ($products as $prodData) {
            $category = $createdCategories[$prodData['category']];
            Product::withoutGlobalScopes()->create([
                'company_id' => $company->id,
                'branch_id' => $branch->id,
                'category_id' => $category->id,
                'sku' => $prodData['sku'],
                'name_translations' => [
                    'es' => $prodData['name_es'],
                    'zh' => $prodData['name_zh'],
                ],
                'base_price' => $prodData['price'],
                'tax_rate' => 19.0,
                'is_combo' => false,
                'is_active' => true,
            ]);
            $this->command->info("  Producto: {$prodData['name_es']} ({$prodData['sku']}) - \${$prodData['price']}");
        }

        $this->command->info('');
        $this->command->info(count($categories) . ' categorías y ' . count($products) . ' productos creados.');
    }
}
