import { useState } from 'react';
import { useTranslation } from 'react-i18next';
import { useConfirmStore } from '@/store/useConfirmStore';
import { useDefaultNotes } from '@/hooks/useDefaultNotes';
import { getDefaultNoteText, type DefaultNote } from '@/types/defaultNotes';
import { useAuthStore } from '@/store/useAuthStore';
import { ArrowLeft, Plus, Pencil, Trash2, Loader2, CheckCircle2, XCircle } from 'lucide-react';
import { useNavigate } from 'react-router-dom';
import { useToastStore } from '@/store/useToastStore';

export function DefaultNotesPage() {
  const { t, i18n } = useTranslation();
  const navigate = useNavigate();
  const user = useAuthStore((s) => s.user);
  const { notes, isLoading, create, update, delete: deleteNote, isCreating, isUpdating, isDeleting } = useDefaultNotes();
  const addToast = useToastStore((s) => s.addToast);

  const isAdmin = user?.role === 'admin' || user?.role === 'manager';
  const currentLocale = i18n.language === 'zh' || i18n.language === 'zh-CN' ? 'zh' : 'es';

  const [editingNote, setEditingNote] = useState<DefaultNote | null>(null);
  const [isCreatingNew, setIsCreatingNew] = useState(false);
  const [textEs, setTextEs] = useState('');
  const [textZh, setTextZh] = useState('');

  const startCreate = () => {
    setIsCreatingNew(true);
    setEditingNote(null);
    setTextEs('');
    setTextZh('');
  };

  const startEdit = (note: DefaultNote) => {
    setEditingNote(note);
    setIsCreatingNew(false);
    setTextEs(note.text_translations['es'] || note.text_translations['es-CL'] || '');
    setTextZh(note.text_translations['zh'] || note.text_translations['zh-CN'] || '');
  };

  const cancelEdit = () => {
    setEditingNote(null);
    setIsCreatingNew(false);
    setTextEs('');
    setTextZh('');
  };

  const handleSave = async () => {
    if (!textEs.trim()) {
      addToast('error', 'El texto en español es obligatorio');
      return;
    }

    const payload = {
      text_translations: {
        es: textEs.trim(),
        'es-CL': textEs.trim(),
        zh: textZh.trim() || textEs.trim(),
        'zh-CN': textZh.trim() || textEs.trim(),
      },
    };

    try {
      if (isCreatingNew) {
        await create(payload);
        addToast('success', '✅ Nota creada');
      } else if (editingNote) {
        await update({ uuid: editingNote.uuid, payload });
        addToast('success', '✅ Nota actualizada');
      }
      cancelEdit();
    } catch (err) {
      console.error(err);
      addToast('error', 'Error al guardar la nota');
    }
  };

  const handleToggleActive = async (note: DefaultNote) => {
    try {
      await update({ uuid: note.uuid, payload: { is_active: !note.is_active } });
      addToast('success', note.is_active ? 'Nota desactivada' : 'Nota activada');
    } catch (err) {
      addToast('error', 'Error al cambiar estado');
    }
  };

  const handleDelete = async (note: DefaultNote) => {
    useConfirmStore.getState().open({ title: "Cancelar", message: "$1", variant: "info", onConfirm: () => {} });
    return;
    try {
      await deleteNote(note.uuid);
      addToast('success', '🗑️ Nota eliminada');
    } catch (err) {
      addToast('error', 'Error al eliminar');
    }
  };

  return (
    <div className="max-w-4xl mx-auto">
      {/* Header */}
      <div className="flex items-center gap-4 mb-8">
        <button
          onClick={() => navigate('/settings/catalog')}
          className="p-2 hover:bg-slate-800 rounded-lg transition-colors"
        >
          <ArrowLeft size={20} />
        </button>
        <div>
          <h1 className="text-3xl font-bold">{t('catalog.default_notes_title', 'Notas Predefinidas')}</h1>
          <p className="text-slate-400 mt-1">
            {t('catalog.default_notes_subtitle', 'Gestiona las notas predefinidas disponibles para los meseros al tomar pedidos')}
          </p>
        </div>
      </div>

      {/* Botón Nueva Nota */}
      {isAdmin && !isCreatingNew && !editingNote && (
        <button
          onClick={startCreate}
          className="mb-6 px-4 py-2.5 bg-orange-500 hover:bg-orange-600 rounded-lg font-semibold flex items-center gap-2 text-white"
        >
          <Plus size={18} />
          {t('catalog.new_note', 'Nueva Nota')}
        </button>
      )}

      {/* Formulario de edición/creación */}
      {(isCreatingNew || editingNote) && (
        <div className="bg-slate-800 border border-slate-700 rounded-xl p-5 mb-6">
          <h3 className="text-lg font-semibold mb-4">
            {isCreatingNew ? t('catalog.create_note', 'Crear Nota') : t('catalog.edit_note', 'Editar Nota')}
          </h3>
          <div className="space-y-3">
            <div>
              <label className="block text-sm text-slate-300 mb-1">🇪🇸 Español</label>
              <input
                type="text"
                value={textEs}
                onChange={(e) => setTextEs(e.target.value)}
                placeholder="Ej: Sin picante"
                className="w-full px-3 py-2 bg-slate-900 border border-slate-700 rounded-lg text-white focus:ring-2 focus:ring-orange-500 outline-none"
              />
            </div>
            <div>
              <label className="block text-sm text-slate-300 mb-1">🇨🇳 中文 (Chino)</label>
              <input
                type="text"
                value={textZh}
                onChange={(e) => setTextZh(e.target.value)}
                placeholder="例：不要辣"
                className="w-full px-3 py-2 bg-slate-900 border border-slate-700 rounded-lg text-white focus:ring-2 focus:ring-orange-500 outline-none"
              />
            </div>
            <div className="flex gap-2 pt-2">
              <button
                onClick={handleSave}
                disabled={isCreating || isUpdating}
                className="px-4 py-2 bg-orange-500 hover:bg-orange-600 rounded-lg font-semibold flex items-center gap-2 text-white disabled:opacity-50"
              >
                {(isCreating || isUpdating) && <Loader2 size={16} className="animate-spin" />}
                {t('common.save', 'Guardar')}
              </button>
              <button
                onClick={cancelEdit}
                className="px-4 py-2 bg-slate-700 hover:bg-slate-600 rounded-lg font-semibold text-slate-300"
              >
                {t('common.cancel', 'Cancelar')}
              </button>
            </div>
          </div>
        </div>
      )}

      {/* Lista de notas */}
      {isLoading ? (
        <div className="flex items-center justify-center py-12">
          <Loader2 className="animate-spin text-orange-500" size={32} />
        </div>
      ) : notes.length === 0 ? (
        <div className="bg-slate-800/50 border border-slate-700 rounded-xl p-8 text-center">
          <p className="text-slate-400">
            {t('catalog.no_notes', 'No hay notas predefinidas. Crea la primera para que los meseros puedan usarlas.')}
          </p>
        </div>
      ) : (
        <div className="space-y-2">
          {notes.map((note) => (
            <div
              key={note.uuid}
              className={`bg-slate-800 border border-slate-700 rounded-lg p-4 flex items-center justify-between ${
                !note.is_active ? 'opacity-50' : ''
              }`}
            >
              <div className="flex-1">
                <div className="flex items-center gap-2">
                  <span className="font-medium text-white">
                    {getDefaultNoteText(note, currentLocale)}
                  </span>
                  {!note.is_active && (
                    <span className="text-xs px-2 py-0.5 bg-slate-700 text-slate-400 rounded">
                      {t('common.inactive', 'Inactiva')}
                    </span>
                  )}
                </div>
                <div className="text-xs text-slate-400 mt-1">
                  🇪🇸 {note.text_translations['es'] || note.text_translations['es-CL'] || '—'}
                  {' · '}
                  🇨🇳 {note.text_translations['zh'] || note.text_translations['zh-CN'] || '—'}
                </div>
              </div>

              {isAdmin && (
                <div className="flex items-center gap-1">
                  <button
                    onClick={() => handleToggleActive(note)}
                    className="p-2 hover:bg-slate-700 rounded-lg transition-colors"
                    title={note.is_active ? t('common.deactivate', 'Desactivar') : t('common.activate', 'Activar')}
                  >
                    {note.is_active ? (
                      <CheckCircle2 size={16} className="text-green-400" />
                    ) : (
                      <XCircle size={16} className="text-slate-400" />
                    )}
                  </button>
                  <button
                    onClick={() => startEdit(note)}
                    className="p-2 hover:bg-slate-700 rounded-lg transition-colors"
                    title={t('common.edit', 'Editar')}
                  >
                    <Pencil size={16} className="text-blue-400" />
                  </button>
                  <button
                    onClick={() => handleDelete(note)}
                    disabled={isDeleting}
                    className="p-2 hover:bg-red-500/20 rounded-lg transition-colors disabled:opacity-50"
                    title={t('common.delete', 'Eliminar')}
                  >
                    <Trash2 size={16} className="text-red-400" />
                  </button>
                </div>
              )}
            </div>
          ))}
        </div>
      )}
    </div>
  );
}
