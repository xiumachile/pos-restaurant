import i18n from "@/i18n";

/**
 * Obtiene la traducción adecuada de un objeto de traducciones.
 * Usa el idioma actual de la app (i18n.language), con fallback a 'es'.
 * 
 * @param translations - Objeto con claves de idioma (ej: {es: "Bar", zh: "吧台"})
 * @param fallback - Valor por defecto si no hay traducción
 */
export function getTranslation(
  translations: Record<string, string> | null | undefined,
  fallback = ""
): string {
  if (!translations || typeof translations !== "object") {
    return fallback;
  }

  const currentLang = i18n.language || "es";
  
  // Intentar idioma exacto
  if (translations[currentLang]) {
    return translations[currentLang];
  }
  
  // Intentar sin variante regional (ej: "es-CL" → "es")
  const baseLang = currentLang.split("-")[0];
  if (translations[baseLang]) {
    return translations[baseLang];
  }
  
  // Fallback a español
  if (translations.es) {
    return translations.es;
  }
  
  // Última opción: primer valor disponible o fallback
  const firstValue = Object.values(translations)[0];
  return firstValue || fallback;
}

/**
 * Valida que un color hexadecimal sea válido.
 */
export function isValidHexColor(color: string): boolean {
  return /^#[0-9a-fA-F]{6}$/.test(color);
}

/**
 * Obtiene el color de una zona, con fallback si es inválido.
 */
export function getZoneColor(color: string | null | undefined): string {
  if (color && isValidHexColor(color)) {
    return color;
  }
  return "#64748b"; // slate-500 como fallback
}
