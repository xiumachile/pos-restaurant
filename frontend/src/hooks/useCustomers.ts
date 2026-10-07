import { useQuery, useMutation, useQueryClient } from '@tanstack/react-query';
import { apiClient } from '@/services/apiClient';

export interface Customer {
  id: string;
  phone: string;
  name: string;
  email?: string;
  address?: string;
  commune?: string;
  address_reference?: string;
  full_address?: string;
  notes?: string;
}

interface SearchResponse {
  found: boolean;
  customer?: Customer;
}

/**
 * Hook para buscar cliente por teléfono
 */
/**
 * Normaliza un teléfono: elimina espacios, guiones y paréntesis.
 * Mantiene solo dígitos y el signo + inicial.
 */
export function normalizePhone(phone: string): string {
  let cleaned = phone.replace(/[^0-9+]/g, '');
  if (cleaned.startsWith('56') && !cleaned.startsWith('+')) {
    cleaned = '+' + cleaned;
  }
  return cleaned;
}

/**
 * Cuenta solo los dígitos de un teléfono (sin +, espacios, guiones).
 */
export function countDigits(phone: string): number {
  return phone.replace(/\D/g, '').length;
}

export function useCustomerByPhone(phone: string | null) {
  return useQuery({
    queryKey: ['customer', 'phone', phone],
    queryFn: async (): Promise<SearchResponse> => {
      if (!phone) {
        return { found: false };
      }
      const normalized = normalizePhone(phone);
      const digits = countDigits(normalized);
      if (digits < 8) {
        return { found: false };
      }
      
      const response = await apiClient.get<any>('/customers/search', {
        params: { phone: normalized }
      });

      const data = response.data;
      console.log('[useCustomerByPhone] 📥 Respuesta cruda del backend:', JSON.stringify(data, null, 2));
      
      // El backend devuelve {found, customer: CustomerResource}
      // CustomerResource tiene formato JSON:API: {type, id, attributes}
      if (!data.found || !data.customer) {
        return { found: false };
      }
      
      // Aplanar formato JSON:API a estructura simple
      const res = data.customer;
      const attrs = res.attributes || res;
      
      const flatCustomer: Customer = {
        id: res.id,
        phone: attrs.phone,
        name: attrs.name,
        email: attrs.email,
        address: attrs.address,
        commune: attrs.commune,
        address_reference: attrs.address_reference,
        full_address: attrs.full_address,
        notes: attrs.notes,
      };
      
      console.log('[useCustomerByPhone] ✅ Cliente aplanado:', JSON.stringify(flatCustomer, null, 2));
      return { found: true, customer: flatCustomer };
    },
    enabled: !!phone && countDigits(normalizePhone(phone)) >= 8,
    staleTime: 30000, // 30 segundos
    retry: 1,
  });
}

/**
 * Hook para crear nuevo cliente
 */
export function useCreateCustomer() {
  const queryClient = useQueryClient();

  return useMutation({
    mutationFn: async (customerData: Partial<Customer>): Promise<Customer> => {
      const response = await apiClient.post<{ data: Customer }>('/customers', customerData);
      return response.data.data;
    },
    onSuccess: (newCustomer) => {
      // Invalidar queries de búsqueda para que se actualicen
      queryClient.invalidateQueries({ queryKey: ['customer'] });
      
      // Actualizar cache con el nuevo cliente
      queryClient.setQueryData(['customer', 'phone', newCustomer.phone], {
        found: true,
        customer: newCustomer,
      });
    },
  });
}

/**
 * Hook para actualizar cliente existente
 */
export function useUpdateCustomer() {
  const queryClient = useQueryClient();

  return useMutation({
    mutationFn: async ({ id, ...data }: Partial<Customer> & { id: string }): Promise<Customer> => {
      const response = await apiClient.put<{ data: Customer }>(`/customers/${id}`, data);
      return response.data.data;
    },
    onSuccess: (updatedCustomer) => {
      queryClient.invalidateQueries({ queryKey: ['customer'] });
      queryClient.setQueryData(['customer', 'phone', updatedCustomer.phone], {
        found: true,
        customer: updatedCustomer,
      });
    },
  });
}
