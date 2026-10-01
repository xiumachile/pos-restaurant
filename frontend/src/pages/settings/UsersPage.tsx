import { useState } from "react";
import { useTranslation } from "react-i18next";
import { useQuery, useMutation, useQueryClient } from "@tanstack/react-query";
import { userService, type CreateUserRequest, type UpdateUserRequest } from "@/services/userService";
import type { User } from "@/types/auth";
import { useToastStore } from "@/store/useToastStore";
import { Plus, Pencil, Trash2, X, Loader2, User as UserIcon } from "lucide-react";
import { useConfirmStore } from "@/store/useConfirmStore";

type UserRole = 'admin' | 'manager' | 'waiter' | 'cashier' | 'kitchen';

const ROLE_OPTIONS: { value: UserRole; labelKey: string }[] = [
  { value: 'admin', labelKey: 'roles.admin' },
  { value: 'manager', labelKey: 'roles.manager' },
  { value: 'waiter', labelKey: 'roles.waiter' },
  { value: 'cashier', labelKey: 'roles.cashier' },
  { value: 'kitchen', labelKey: 'roles.kitchen' },
];

export function UsersPage() {
  const { t } = useTranslation();
  const queryClient = useQueryClient();
  const [isModalOpen, setIsModalOpen] = useState(false);
  const [editingUser, setEditingUser] = useState<User | null>(null);

  const { data: users = [], isLoading } = useQuery({
    queryKey: ['users'],
    queryFn: userService.getUsers,
  });

  const createMutation = useMutation({
    mutationFn: (data: CreateUserRequest) => userService.createUser(data),
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: ['users'] });
      useToastStore.getState().addToast('success', t('users.created_success'));
      setIsModalOpen(false);
    },
    onError: (error: any) => {
      useToastStore.getState().addToast('error', error.response?.data?.detail || t('users.create_error'));
    }
  });

  const updateMutation = useMutation({
    mutationFn: ({ uuid, data }: { uuid: string; data: UpdateUserRequest }) => 
      userService.updateUser(uuid, data),
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: ['users'] });
      useToastStore.getState().addToast('success', t('users.updated_success'));
      setIsModalOpen(false);
      setEditingUser(null);
    },
    onError: (error: any) => {
      useToastStore.getState().addToast('error', error.response?.data?.detail || t('users.update_error'));
    }
  });

  const deleteMutation = useMutation({
    mutationFn: (uuid: string) => userService.deleteUser(uuid),
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: ['users'] });
      useToastStore.getState().addToast('success', t('users.deleted_success'));
    },
    onError: (error: any) => {
      useToastStore.getState().addToast('error', error.response?.data?.detail || t('users.delete_error'));
    }
  });

  const handleSave = (e: React.FormEvent<HTMLFormElement>) => {
    e.preventDefault();
    const formData = new FormData(e.currentTarget);
    const payload = {
      name: formData.get('name') as string,
      email: formData.get('email') as string,
      role: formData.get('role') as UserRole,
      pin: formData.get('pin') as string || undefined,
    };

    if (editingUser) {
      updateMutation.mutate({ uuid: editingUser.uuid, data: payload });
    } else {
      createMutation.mutate(payload);
    }
  };

  const openEdit = (user: User) => {
    setEditingUser(user);
    setIsModalOpen(true);
  };

  const openCreate = () => {
    setEditingUser(null);
    setIsModalOpen(true);
  };

  return (
    <div className="p-6 space-y-6">
      {/* Header */}
      <div className="flex items-center justify-between">
        <div>
          <h1 className="text-2xl font-bold text-white flex items-center gap-2">
            <UserIcon className="text-orange-500" />
            {t("users.title")}
          </h1>
          <p className="text-slate-400 text-sm mt-1">{t("users.description")}</p>
        </div>
        <button
          onClick={openCreate}
          className="flex items-center gap-2 px-4 py-2 bg-orange-500 hover:bg-orange-600 text-white rounded-lg font-medium transition-colors"
        >
          <Plus size={18} />
          {t("users.add")}
        </button>
      </div>

      {/* Tabla de Usuarios */}
      <div className="bg-slate-900 border border-slate-800 rounded-xl overflow-hidden">
        {isLoading ? (
          <div className="flex items-center justify-center py-12">
            <Loader2 className="animate-spin text-orange-500" size={32} />
          </div>
        ) : users.length === 0 ? (
          <div className="text-center py-12 text-slate-400">
            <UserIcon size={48} className="mx-auto mb-3 opacity-50" />
            <p>{t("users.empty")}</p>
          </div>
        ) : (
          <table className="w-full text-left">
            <thead className="bg-slate-800 text-slate-400 text-xs uppercase">
              <tr>
                <th className="px-6 py-3">{t("users.name")}</th>
                <th className="px-6 py-3">{t("users.email")}</th>
                <th className="px-6 py-3">{t("users.role")}</th>
                <th className="px-6 py-3 text-right">{t("users.actions")}</th>
              </tr>
            </thead>
            <tbody className="divide-y divide-slate-800">
              {users.map((user) => (
                <tr key={user.uuid} className="hover:bg-slate-800/50 transition-colors">
                  <td className="px-6 py-4 text-white font-medium">{user.name}</td>
                  <td className="px-6 py-4 text-slate-400">{user.email}</td>
                  <td className="px-6 py-4">
                    <span className="px-2 py-1 rounded-full text-xs font-medium bg-slate-800 text-slate-300 border border-slate-700">
                      {t(`roles.${user.role}`)}
                    </span>
                  </td>
                  <td className="px-6 py-4 text-right">
                    <div className="flex items-center justify-end gap-2">
                      <button
                        onClick={() => openEdit(user)}
                        className="p-2 text-slate-400 hover:text-blue-400 hover:bg-blue-400/10 rounded-lg transition-colors"
                        title={t("common.edit")}
                      >
                        <Pencil size={16} />
                      </button>
                      <button
                        onClick={() => {
                          useConfirmStore.getState().open({
                            title: t("common.confirm_delete"),
                            message: t("users.delete_confirm"),
                            variant: "danger",
                            onConfirm: () => {
                              deleteMutation.mutate(user.uuid);
                            }
                          });
                        }}
                        className="p-2 text-slate-400 hover:text-red-400 hover:bg-red-400/10 rounded-lg transition-colors"
                        title={t("common.delete")}
                      >
                        <Trash2 size={16} />
                      </button>
                    </div>
                  </td>
                </tr>
              ))}
            </tbody>
          </table>
        )}
      </div>

      {/* Modal de Crear/Editar */}
      {isModalOpen && (
        <div className="fixed inset-0 bg-black/70 flex items-center justify-center z-50 p-4">
          <div className="bg-slate-900 border border-slate-700 rounded-xl w-full max-w-md shadow-2xl">
            <div className="flex items-center justify-between p-4 border-b border-slate-800">
              <h2 className="text-lg font-bold text-white">
                {editingUser ? t("users.edit") : t("users.add")}
              </h2>
              <button onClick={() => setIsModalOpen(false)} className="text-slate-400 hover:text-white">
                <X size={20} />
              </button>
            </div>
            
            <form onSubmit={handleSave} className="p-4 space-y-4">
              <div>
                <label className="block text-sm font-medium text-slate-400 mb-1">{t("users.name")}</label>
                <input
                  name="name"
                  defaultValue={editingUser?.name}
                  required
                  className="w-full bg-slate-800 border border-slate-700 rounded-lg px-3 py-2 text-white focus:outline-none focus:border-orange-500"
                />
              </div>
              
              <div>
                <label className="block text-sm font-medium text-slate-400 mb-1">{t("users.email")}</label>
                <input
                  name="email"
                  type="email"
                  defaultValue={editingUser?.email}
                  required
                  className="w-full bg-slate-800 border border-slate-700 rounded-lg px-3 py-2 text-white focus:outline-none focus:border-orange-500"
                />
              </div>

              <div>
                <label className="block text-sm font-medium text-slate-400 mb-1">{t("users.role")}</label>
                <select
                  name="role"
                  defaultValue={editingUser?.role || 'waiter'}
                  className="w-full bg-slate-800 border border-slate-700 rounded-lg px-3 py-2 text-white focus:outline-none focus:border-orange-500"
                >
                  {ROLE_OPTIONS.map((opt) => (
                    <option key={opt.value} value={opt.value}>
                      {t(opt.labelKey)}
                    </option>
                  ))}
                </select>
              </div>

              <div>
                <label className="block text-sm font-medium text-slate-400 mb-1">
                  {t("users.pin")} <span className="text-slate-600 text-xs">({t("users.pin_optional")})</span>
                </label>
                <input
                  name="pin"
                  type="password"
                  maxLength={6}
                  placeholder="****"
                  className="w-full bg-slate-800 border border-slate-700 rounded-lg px-3 py-2 text-white focus:outline-none focus:border-orange-500"
                />
              </div>

              <div className="flex gap-3 pt-2">
                <button
                  type="button"
                  onClick={() => setIsModalOpen(false)}
                  className="flex-1 px-4 py-2 bg-slate-800 hover:bg-slate-700 text-white rounded-lg font-medium transition-colors"
                >
                  {t("common.cancel")}
                </button>
                <button
                  type="submit"
                  disabled={createMutation.isPending || updateMutation.isPending}
                  className="flex-1 px-4 py-2 bg-orange-500 hover:bg-orange-600 disabled:bg-slate-700 disabled:text-slate-400 text-white rounded-lg font-medium transition-colors flex items-center justify-center gap-2"
                >
                  {(createMutation.isPending || updateMutation.isPending) && <Loader2 size={16} className="animate-spin" />}
                  {t("common.save")}
                </button>
              </div>
            </form>
          </div>
        </div>
      )}
    </div>
  );
}
