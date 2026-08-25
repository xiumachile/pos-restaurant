import { useQuery, useMutation, useQueryClient } from "@tanstack/react-query";
import {
  categoryAdminService,
  productAdminService,
  type CategoryCreatePayload,
  type CategoryUpdatePayload,
  type ProductCreatePayload,
  type ProductUpdatePayload,
} from "@/services/catalogAdminService";
import type { Category, Product } from "@/types/catalog";

const CATEGORIES_KEY = ["admin", "categories"];
const PRODUCTS_KEY = ["admin", "products"];

/* ─── Hooks de categorías ─── */

export function useAdminCategories() {
  return useQuery<Category[], Error>({
    queryKey: CATEGORIES_KEY,
    queryFn: categoryAdminService.listAll,
    staleTime: 30 * 1000,
  });
}

export function useCreateCategory() {
  const queryClient = useQueryClient();
  return useMutation({
    mutationFn: (payload: CategoryCreatePayload) =>
      categoryAdminService.create(payload),
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: CATEGORIES_KEY });
    },
  });
}

export function useUpdateCategory() {
  const queryClient = useQueryClient();
  return useMutation({
    mutationFn: ({ uuid, payload }: { uuid: string; payload: CategoryUpdatePayload }) =>
      categoryAdminService.update(uuid, payload),
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: CATEGORIES_KEY });
    },
  });
}

export function useDeleteCategory() {
  const queryClient = useQueryClient();
  return useMutation({
    mutationFn: (uuid: string) => categoryAdminService.delete(uuid),
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: CATEGORIES_KEY });
    },
  });
}

/* ─── Hooks de productos ─── */

export function useAdminProducts(filters?: {
  categoryId?: number;
  search?: string;
}) {
  return useQuery<Product[], Error>({
    queryKey: [PRODUCTS_KEY, filters?.categoryId ?? "all", filters?.search ?? ""],
    queryFn: () => productAdminService.listAll(filters),
    staleTime: 30 * 1000,
  });
}

export function useCreateProduct() {
  const queryClient = useQueryClient();
  return useMutation({
    mutationFn: (payload: ProductCreatePayload) =>
      productAdminService.create(payload),
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: PRODUCTS_KEY });
    },
  });
}

export function useUpdateProduct() {
  const queryClient = useQueryClient();
  return useMutation({
    mutationFn: ({ uuid, payload }: { uuid: string; payload: ProductUpdatePayload }) =>
      productAdminService.update(uuid, payload),
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: PRODUCTS_KEY });
    },
  });
}

export function useDeleteProduct() {
  const queryClient = useQueryClient();
  return useMutation({
    mutationFn: (uuid: string) => productAdminService.delete(uuid),
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: PRODUCTS_KEY });
    },
  });
}
