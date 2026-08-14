<?php

namespace Modules\Printers\Domain\Entities;

use App\Shared\Domain\Traits\BelongsToTenant;
use App\Shared\Domain\Traits\HasUuid;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Modules\Branches\Domain\Entities\Branch;
use Modules\Catalog\Domain\Entities\Category;
use Modules\Companies\Domain\Entities\Company;

/**
 * Mapeo de categorías de productos a impresoras de cocina.
 * Define qué productos van a qué impresora.
 */
class PrinterStationMapping extends Model
{
    use HasUuid;
    use BelongsToTenant;

    protected $fillable = [
        'company_id',
        'branch_id',
        'printer_id',
        'category_id',
        'product_keywords',
        'priority',
        'is_active',
    ];

    protected $casts = [
        'product_keywords' => 'array',
        'priority' => 'integer',
        'is_active' => 'boolean',
    ];

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    public function printer(): BelongsTo
    {
        return $this->belongsTo(Printer::class);
    }

    public function category(): BelongsTo
    {
        // withoutGlobalScopes para evitar conflicto con BelongsToTenant de Category
        // Las categorías pueden ser globales a la empresa (sin branch_id específico)
        return $this->belongsTo(Category::class)->withoutGlobalScopes();
    }

    /**
     * Verifica si un producto debe imprimirse en esta impresora.
     */
    public function matchesProduct(string $productName, ?int $categoryId): bool
    {
        // Si tiene category_id, verificar coincidencia exacta
        if ($this->category_id && $categoryId === $this->category_id) {
            return true;
        }

        // Si tiene keywords, buscar en el nombre del producto
        if (!empty($this->product_keywords)) {
            $productNameLower = strtolower($productName);
            foreach ($this->product_keywords as $keyword) {
                if (str_contains($productNameLower, strtolower($keyword))) {
                    return true;
                }
            }
        }

        return false;
    }
}
