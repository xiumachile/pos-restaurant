<?php

namespace Modules\Catalog\Application\Services;

use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Modules\Catalog\Domain\Contracts\CatalogExportServiceInterface;

class CatalogExportService implements CatalogExportServiceInterface
{
    public function getChangedCategories(int $branchId, ?Carbon $since): Collection
    {
        $query = DB::table('categories')
            ->where('branch_id', $branchId);

        if ($since) {
            $query->where('updated_at', '>', $since);
        }

        return $query->get()->map(function ($cat) {
            return [
                'uuid' => $cat->uuid,
                'name_translations' => json_decode($cat->name_translations, true),
                'parent_id' => $cat->parent_id,
                'sort_order' => $cat->sort_order,
                'is_active' => $cat->is_active,
                'updated_at' => $cat->updated_at,
            ];
        });
    }

    public function getChangedProducts(int $branchId, ?Carbon $since): Collection
    {
        $query = DB::table('products')
            ->where('branch_id', $branchId);

        if ($since) {
            $query->where('updated_at', '>', $since);
        }

        return $query->get()->map(function ($prod) {
            return [
                'uuid' => $prod->uuid,
                'category_id' => $prod->category_id,
                'name_translations' => json_decode($prod->name_translations, true),
                'description_translations' => json_decode($prod->description_translations, true),
                'base_price' => $prod->base_price,
                'is_active' => $prod->is_active,
                'updated_at' => $prod->updated_at,
            ];
        });
    }
}
