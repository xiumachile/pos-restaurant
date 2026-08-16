<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Modules\Catalog\Domain\Entities\Product;
use Modules\Catalog\Domain\Entities\MenuItem;

class MenuItemsSeeder extends Seeder
{
    public function run(): void
    {
        // Obtener product_ids que YA tienen MenuItem (usando DB directo para evitar scopes)
        $existingProductIds = DB::table('menu_items')
            ->distinct()
            ->pluck('product_id')
            ->toArray();

        // Products sin MenuItem (query directa sin scopes problemáticos)
        $products = Product::withoutGlobalScopes()
            ->whereNotIn('id', $existingProductIds)
            ->get();

        if ($products->isEmpty()) {
            $this->command->info('Todos los Products ya tienen MenuItem asociado.');
            return;
        }

        $this->command->info("Creando MenuItems para {$products->count()} Products...");

        $created = 0;
        foreach ($products as $product) {
            try {
                MenuItem::withoutGlobalScopes()->create([
                    'company_id' => $product->company_id,
                    'branch_id' => $product->branch_id,
                    'product_id' => $product->id,
                    'base_price' => $product->base_price,
                    'discount_amount' => 0,
                    'is_active' => $product->is_active,
                ]);
                $name = $product->name_translations['es'] ?? $product->sku;
                $this->command->info("  ✓ MenuItem creado para {$name} ({$product->sku})");
                $created++;
            } catch (\Throwable $e) {
                $this->command->warn("  ⚠ Error con {$product->sku}: " . $e->getMessage());
            }
        }

        $this->command->info("✅ {$created} MenuItems creados.");
    }
}
