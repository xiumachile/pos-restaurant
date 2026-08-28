<?php

namespace Modules\Catalog\Domain\Services;

use Illuminate\Support\Collection;
use Modules\Catalog\Domain\Entities\Category;
use Modules\Identity\Domain\Entities\User;

/**
 * Servicio de dominio para gestión de categorías.
 *
 * Extraído de CategoryController en S5 para cumplir DDD:
 * - Centraliza validación de profundidad máxima (2 niveles)
 * - Valida reglas de dominio (no auto-referencia, no eliminar con productos)
 * - Lanza DomainExceptions en lugar de retornar JSON
 */
class CategoryManagementService
{
    /**
     * Lista categorías con filtros.
     */
    public function listCategories(?bool $activeOnly = null): Collection
    {
        return Category::query()
            ->with('parent')
            ->when(
                $activeOnly === true,
                fn ($q) => $q->where('is_active', true)
            )
            ->orderBy('sort_order')
            ->orderBy('name_translations->es')
            ->get();
    }

    /**
     * Crea una categoría nueva.
     *
     * @throws \DomainException Si excede profundidad máxima.
     */
    public function createCategory(array $data, User $user): Category
    {
        $parentId = null;
        $depth = 0;

        if (!empty($data['parent_id'])) {
            $parent = Category::where(
                'uuid',
                $data['parent_id']
            )->firstOrFail();

            if ($parent->depth >= 1) {
                throw new \DomainException(
                    'No se permiten más de 2 niveles de categorías.'
                );
            }

            $parentId = $parent->id;
            $depth = $parent->depth + 1;
        }

        return Category::create([
            'company_id' => $user->company_id,
            'branch_id' => $user->branch_id,
            'parent_id' => $parentId,
            'depth' => $depth,
            'name_translations' => $data['name_translations'],
            'sort_order' => $data['sort_order'] ?? 0,
            'is_active' => $data['is_active'] ?? true,
            'tax_id' => $data['tax_id'] ?? null,
        ]);
    }

    /**
     * Actualiza una categoría existente.
     *
     * @throws \DomainException
     *         Si se asigna a sí misma como padre
     *         o excede profundidad.
     */
    public function updateCategory(
        Category $category,
        array $data
    ): Category {
        if (array_key_exists('parent_id', $data)) {
            if (!empty($data['parent_id'])) {
                if ($data['parent_id'] === $category->uuid) {
                    throw new \DomainException(
                        'Una categoría no puede ser su propio padre.'
                    );
                }

                $parent = Category::where(
                    'uuid',
                    $data['parent_id']
                )->firstOrFail();

                if ($parent->depth >= 1) {
                    throw new \DomainException(
                        'No se permiten más de 2 niveles de categorías.'
                    );
                }

                $data['parent_id'] = $parent->id;
                $data['depth'] = $parent->depth + 1;
            } else {
                $data['parent_id'] = null;
                $data['depth'] = 0;
            }
        }

        $category->update($data);

        return $category->fresh('parent');
    }

    /**
     * Elimina una categoría.
     *
     * @throws \DomainException
     *         Si tiene productos activos o subcategorías.
     */
    public function deleteCategory(Category $category): void
    {
        if ($category->products()
            ->where('is_active', true)
            ->exists()) {

            $count = $category->products()
                ->where('is_active', true)
                ->count();

            throw new \DomainException(
                "No se puede eliminar una categoría con productos activos ({$count} productos)."
            );
        }

        if ($category->children()->exists()) {
            $count = $category->children()->count();

            throw new \DomainException(
                "No se puede eliminar una categoría con subcategorías ({$count} subcategorías)."
            );
        }

        $category->delete();
    }
}
