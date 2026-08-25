import { useQuery, useMutation, useQueryClient } from "@tanstack/react-query";
import {
  menuAdminService,
  type Menu,
  type MenuCreatePayload,
  type MenuUpdatePayload,
  type MenuActivationPayload,
} from "@/services/menuAdminService";

const MENUS_KEY = ["admin", "menus"];

export function useMenus() {
  return useQuery<Menu[], Error>({
    queryKey: MENUS_KEY,
    queryFn: menuAdminService.listAll,
    staleTime: 30 * 1000,
  });
}

export function useMenu(uuid: string | null) {
  return useQuery<{ menu: Menu; items: any[] }, Error>({
    queryKey: ["admin", "menus", uuid],
    queryFn: () => menuAdminService.show(uuid!),
    enabled: !!uuid,
  });
}

export function useCreateMenu() {
  const queryClient = useQueryClient();
  return useMutation({
    mutationFn: (payload: MenuCreatePayload) =>
      menuAdminService.create(payload),
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: MENUS_KEY });
    },
  });
}

export function useUpdateMenu() {
  const queryClient = useQueryClient();
  return useMutation({
    mutationFn: ({ uuid, payload }: { uuid: string; payload: MenuUpdatePayload }) =>
      menuAdminService.update(uuid, payload),
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: MENUS_KEY });
    },
  });
}

export function useDeleteMenu() {
  const queryClient = useQueryClient();
  return useMutation({
    mutationFn: (uuid: string) => menuAdminService.delete(uuid),
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: MENUS_KEY });
    },
  });
}

export function useUpdateMenuActivations() {
  const queryClient = useQueryClient();
  return useMutation({
    mutationFn: ({
      menuUuid,
      activations,
    }: {
      menuUuid: string;
      activations: MenuActivationPayload[];
    }) => menuAdminService.updateActivations(menuUuid, activations),
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: MENUS_KEY });
    },
  });
}
