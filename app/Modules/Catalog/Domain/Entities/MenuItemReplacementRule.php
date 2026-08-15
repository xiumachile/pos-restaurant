<?php

namespace Modules\Catalog\Domain\Entities;

use App\Shared\Domain\Traits\BelongsToTenant;
use App\Shared\Domain\Traits\HasTranslations;
use App\Shared\Domain\Traits\HasUuid;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class MenuItemReplacementRule extends Model
{
    use HasUuid;
    use BelongsToTenant;
    use HasTranslations;
    use SoftDeletes;

    public const RULE_TYPE_ANY = 'any_product';
    public const RULE_TYPE_PRODUCT = 'allowed_product';
    public const RULE_TYPE_CATEGORY = 'allowed_category';

    protected $fillable = [
        'company_id',
        'branch_id',
        'menu_item_id',
        'target_product_id',
        'rule_type',
        'allowed_product_id',
        'allowed_category_id',
        'max_price_delta',
        'requires_authorization',
        'priority',
        'is_active',
        'description_translations',
    ];

    protected $casts = [
        'max_price_delta' => 'decimal:2',
        'requires_authorization' => 'boolean',
        'priority' => 'integer',
        'is_active' => 'boolean',
        'description_translations' => 'array',
    ];

    protected array $translatableFields = ['description_translations'];

    /**
     * El combo al que aplica la regla.
     */
    public function menuItem(): BelongsTo
    {
        return $this->belongsTo(MenuItem::class);
    }

    /**
     * El producto original que puede ser reemplazado (NULL = aplica a todos).
     */
    public function targetProduct(): BelongsTo
    {
        return $this->belongsTo(Product::class, 'target_product_id');
    }

    /**
     * El producto específico permitido (solo si rule_type = allowed_product).
     */
    public function allowedProduct(): BelongsTo
    {
        return $this->belongsTo(Product::class, 'allowed_product_id');
    }

    /**
     * La categoría permitida (solo si rule_type = allowed_category).
     */
    public function allowedCategory(): BelongsTo
    {
        return $this->belongsTo(Category::class, 'allowed_category_id');
    }

    /**
     * Scope: solo reglas activas.
     */
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    /**
     * Scope: ordenadas por prioridad.
     */
    public function scopeOrdered($query)
    {
        return $query->orderBy('priority')->orderBy('id');
    }

    /**
     * Scope: reglas para un producto específico en un combo.
     */
    public function scopeForProduct($query, int $menuItemId, ?int $targetProductId = null)
    {
        $query->where('menu_item_id', $menuItemId);

        if ($targetProductId !== null) {
            $query->where(function ($q) use ($targetProductId) {
                $q->whereNull('target_product_id')
                    ->orWhere('target_product_id', $targetProductId);
            });
        }

        return $query;
    }

    /**
     * Scope: reglas globales de empresa (branch_id NULL).
     */
    public function scopeGlobal($query)
    {
        return $query->whereNull('branch_id');
    }

    /**
     * Scope: reglas específicas de sucursal.
     */
    public function scopeForBranch($query, int $branchId)
    {
        return $query->where('branch_id', $branchId);
    }

    /**
     * Verifica si un producto cumple con esta regla.
     * 
     * Para reglas tipo 'allowed_category', la categoría referenciada debe estar
     * activa (is_active = true). Si la categoría fue desactivada, la regla se
     * considera inválida y retorna false (fail-closed).
     */
    public function matches(Product $replacement): bool
    {
        if (!$this->is_active) {
            return false;
        }

        return match ($this->rule_type) {
            self::RULE_TYPE_ANY => true,
            self::RULE_TYPE_PRODUCT => $this->allowed_product_id === $replacement->id,
            self::RULE_TYPE_CATEGORY => $this->matchesCategory($replacement),
            default => false,
        };
    }

    /**
     * Verifica si el producto de reemplazo coincide con la categoría permitida
     * y además la categoría está activa.
     */
    private function matchesCategory(Product $replacement): bool
    {
        if ($this->allowed_category_id === null) {
            return false;
        }

        // Si la categoría no está cargada, cargarla
        $category = $this->allowedCategory;
        
        // Si la categoría fue desactivada o eliminada, la regla es inválida
        if (!$category || !$category->is_active) {
            return false;
        }

        return $this->allowed_category_id === $replacement->category_id;
    }

    /**
     * Verifica si esta regla tiene una categoría inválida (desactivada o eliminada).
     * Usado por ValidateComboSubstitution para dar mensajes claros al usuario.
     */
    public function hasInvalidCategory(): bool
    {
        if ($this->rule_type !== self::RULE_TYPE_CATEGORY || $this->allowed_category_id === null) {
            return false;
        }

        $category = $this->allowedCategory;
        return !$category || !$category->is_active;
    }
}
