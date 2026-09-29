export interface DefaultNote {
  uuid: string;
  text_translations: Record<string, string>;
  sort_order: number;
  is_active: boolean;
}

export interface CreateDefaultNotePayload {
  text_translations: Record<string, string>;
  sort_order?: number;
  is_active?: boolean;
}

export interface UpdateDefaultNotePayload {
  text_translations?: Record<string, string>;
  sort_order?: number;
  is_active?: boolean;
}

export function getDefaultNoteText(
  note: DefaultNote,
  locale: string = "es"
): string {
  if (!note.text_translations) return "Sin texto";
  return (
    note.text_translations[locale] ||
    note.text_translations["es-CL"] ||
    note.text_translations["es"] ||
    note.text_translations["zh"] ||
    Object.values(note.text_translations)[0] ||
    "Sin texto"
  );
}
