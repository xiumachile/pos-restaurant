<?php

namespace App\Shared\Support;

class TranslationResolver
{
    /**
     * Resuelve la traducción de un campo JSONB según la jerarquía de locales.
     *
     * Jerarquía de resolución (sección 10.5 de arquitectura):
     * 1. Clave exacta del locale activo (ej: "es-CL")
     * 2. Idioma base del locale activo (ej: "es" si locale es "es-CL")
     * 3. Clave exacta del fallback locale (ej: "es-CL")
     * 4. Idioma base del fallback locale (ej: "es")
     * 5. Primera traducción disponible
     * 6. Valor por defecto si todo falla
     *
     * @param array|null $translations Array JSONB con traducciones
     * @param string $activeLocale Locale activo (ej: "zh-CN", "es-CL")
     * @param string|null $fallbackLocale Locale de respaldo (ej: "es-CL")
     * @param string $default Valor por defecto si no hay traducciones
     * @return string
     */
    public static function resolve(
        ?array $translations,
        string $activeLocale,
        ?string $fallbackLocale = null,
        string $default = ''
    ): string {
        if (empty($translations) || !is_array($translations)) {
            return $default;
        }

        // 1. Clave exacta del locale activo
        if (isset($translations[$activeLocale]) && $translations[$activeLocale] !== '') {
            return (string) $translations[$activeLocale];
        }

        // 2. Idioma base del locale activo (ej: "es" de "es-CL")
        $baseLocale = self::getBaseLocale($activeLocale);
        if ($baseLocale !== $activeLocale && isset($translations[$baseLocale]) && $translations[$baseLocale] !== '') {
            return (string) $translations[$baseLocale];
        }

        // 3. Clave exacta del fallback locale
        if ($fallbackLocale && $fallbackLocale !== $activeLocale) {
            if (isset($translations[$fallbackLocale]) && $translations[$fallbackLocale] !== '') {
                return (string) $translations[$fallbackLocale];
            }

            // 4. Idioma base del fallback locale
            $baseFallback = self::getBaseLocale($fallbackLocale);
            if ($baseFallback !== $fallbackLocale && isset($translations[$baseFallback]) && $translations[$baseFallback] !== '') {
                return (string) $translations[$baseFallback];
            }
        }

        // 5. Primera traducción disponible
        foreach ($translations as $value) {
            if ($value !== '' && $value !== null) {
                return (string) $value;
            }
        }

        // 6. Valor por defecto
        return $default;
    }

    /**
     * Obtiene el idioma base de un locale (ej: "es-CL" -> "es").
     */
    public static function getBaseLocale(string $locale): string
    {
        $parts = explode('-', $locale);
        return strtolower($parts[0]);
    }

    /**
     * Verifica si un array de traducciones contiene una clave específica.
     */
    public static function has(?array $translations, string $locale): bool
    {
        if (empty($translations)) {
            return false;
        }

        $baseLocale = self::getBaseLocale($locale);

        return (isset($translations[$locale]) && $translations[$locale] !== '')
            || (isset($translations[$baseLocale]) && $translations[$baseLocale] !== '');
    }
}
