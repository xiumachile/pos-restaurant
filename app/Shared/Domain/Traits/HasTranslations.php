<?php

namespace App\Shared\Domain\Traits;

use App\Shared\Support\TranslationResolver;

trait HasTranslations
{
    /**
     * Campos que contienen traducciones JSONB.
     * Los modelos DEBEN definir esta propiedad.
     * Ejemplo: protected array $translatableFields = ['name_translations', 'description_translations'];
     */
    // ELIMINAR ESTA LÍNEA:
    // protected array $translatableFields = [];

    /**
     * Obtiene la traducción de un campo según el locale activo.
     */
    public function translate(string $field, ?string $activeLocale = null, string $default = ''): string
    {
        // Verificar que el modelo definió la propiedad
        if (!property_exists($this, 'translatableFields')) {
            throw new \RuntimeException(
                'Model ' . get_class($this) . ' must define $translatableFields property.'
            );
        }

        if (!in_array($field, $this->translatableFields)) {
            return $default;
        }

        $translations = $this->getAttribute($field);

        if (!is_array($translations)) {
            return $default;
        }

        $activeLocale = $activeLocale ?? app()->getLocale();
        $fallbackLocale = $this->getFallbackLocale();

        return TranslationResolver::resolve($translations, $activeLocale, $fallbackLocale, $default);
    }

    /**
     * Método mágico para acceder a traducciones como propiedades.
     */
    public function getAttribute($key)
    {
        if (str_ends_with($key, '_resolved')) {
            $baseField = substr($key, 0, -8) . '_translations';
            
            if (property_exists($this, 'translatableFields') && in_array($baseField, $this->translatableFields)) {
                return $this->translate($baseField);
            }
        }

        return parent::getAttribute($key);
    }

    /**
     * Establece una traducción específica.
     */
    public function setTranslation(string $field, string $locale, string $value): self
    {
        if (!property_exists($this, 'translatableFields')) {
            throw new \RuntimeException(
                'Model ' . get_class($this) . ' must define $translatableFields property.'
            );
        }

        if (!in_array($field, $this->translatableFields)) {
            throw new \InvalidArgumentException("Field {$field} is not a translatable field.");
        }

        $translations = $this->getAttribute($field) ?? [];

        if (!is_array($translations)) {
            $translations = [];
        }

        $translations[$locale] = $value;
        $this->setAttribute($field, $translations);

        return $this;
    }

    /**
     * Establece múltiples traducciones.
     */
    public function setTranslations(string $field, array $translations): self
    {
        if (!property_exists($this, 'translatableFields')) {
            throw new \RuntimeException(
                'Model ' . get_class($this) . ' must define $translatableFields property.'
            );
        }

        if (!in_array($field, $this->translatableFields)) {
            throw new \InvalidArgumentException("Field {$field} is not a translatable field.");
        }

        $this->setAttribute($field, $translations);
        return $this;
    }

    /**
     * Obtiene el fallback locale desde la jerarquía.
     */
    protected function getFallbackLocale(): string
    {
        if (method_exists($this, 'branch') && $this->relationLoaded('branch') && $this->branch) {
            return $this->branch->default_locale ?? $this->getDefaultFallback();
        }

        if (method_exists($this, 'company') && $this->relationLoaded('company') && $this->company) {
            return $this->company->fallback_locale ?? $this->getDefaultFallback();
        }

        return $this->getDefaultFallback();
    }

    /**
     * Fallback por defecto del sistema.
     */
    protected function getDefaultFallback(): string
    {
        return config('app.fallback_locale', 'es');
    }
}
