import React, { useState, useEffect, useRef, useCallback } from 'react';
import { useCustomerByPhone, useCreateCustomer, useUpdateCustomer, type Customer } from '@/hooks/useCustomers';

interface CustomerFormProps {
  initialData?: {
    customer_id?: string;
    customer_name?: string;
    customer_phone?: string;
    delivery_address?: string;
    commune?: string;
    address_reference?: string;
  };
  onChange: (data: {
    customer_id?: string;
    customer_name: string;
    customer_phone: string;
    delivery_address: string;
    commune?: string;
    address_reference?: string;
  }) => void;
}

export function CustomerForm({ initialData, onChange }: CustomerFormProps) {
  const [phone, setPhone] = useState(initialData?.customer_phone || '');
  const [editMode, setEditMode] = useState(false);
  const [formData, setFormData] = useState({
    name: initialData?.customer_name || '',
    email: '',
    address: initialData?.delivery_address || '',
    commune: initialData?.commune || '',
    address_reference: initialData?.address_reference || '',
    notes: '',
  });

  const { data: searchResult, isLoading: isSearching } = useCustomerByPhone(phone);
  const createMutation = useCreateCustomer();
  const updateMutation = useUpdateCustomer();

  const customer = searchResult?.found ? searchResult.customer : null;
  const found = !!customer;

  // Cuando se encuentra un cliente, llenar el formulario
  useEffect(() => {
    if (customer) {
    }
    if (customer && !editMode) {
      // Fallback: soporta formato JSON:API (customer.attributes.*) y plano (customer.*)
      const attrs = (customer as any).attributes || customer;
      setFormData({
        name: attrs.name || '',
        email: attrs.email || '',
        address: attrs.address || '',
        commune: attrs.commune || '',
        address_reference: attrs.address_reference || '',
        notes: attrs.notes || '',
      });
    }
  }, [customer, editMode]);

  // Ref estable para onChange (evita loop infinito)
  const onChangeRef = useRef(onChange);
  onChangeRef.current = onChange;

  // Emitir cambios al padre (sin onChange en dependencias)
  useEffect(() => {
    onChangeRef.current({
      customer_id: found ? customer?.id : undefined,
      customer_name: formData.name,
      customer_phone: phone,
      delivery_address: formData.address,
      commune: formData.commune,
      address_reference: formData.address_reference,
    });
  }, [phone, formData, found, customer?.id]);

  const handleCreate = async () => {
    try {
      await createMutation.mutateAsync({
        phone,
        name: formData.name,
        email: formData.email || undefined,
        address: formData.address,
        commune: formData.commune || undefined,
        address_reference: formData.address_reference || undefined,
        notes: formData.notes || undefined,
      });
      setEditMode(false);
    } catch (error) {
      console.error('Error creating customer:', error);
    }
  };

  const handleUpdate = async () => {
    if (!customer?.id) return;
    
    try {
      await updateMutation.mutateAsync({
        id: customer.id,
        phone,
        name: formData.name,
        email: formData.email || undefined,
        address: formData.address,
        commune: formData.commune || undefined,
        address_reference: formData.address_reference || undefined,
        notes: formData.notes || undefined,
      });
      setEditMode(false);
    } catch (error) {
      console.error('Error updating customer:', error);
    }
  };

  // Contar solo dígitos del teléfono (ignorar +, espacios, guiones)
  const phoneDigits = phone.replace(/\D/g, '').length;

  const isValid = formData.name.trim().length > 0 && 
                  phoneDigits >= 8 && 
                  formData.address.trim().length > 0;

  return (
    <div className="space-y-4">
      {/* Campo de teléfono con búsqueda automática */}
      <div>
        <label htmlFor="customer-phone" className="block text-sm font-medium text-slate-300 mb-1">
          Teléfono del cliente *
        </label>
        <div className="relative">
          <input
            id="customer-phone"
            type="tel"
            inputMode="tel"
            value={phone}
            onChange={(e) => setPhone(e.target.value)}
            placeholder="+56 9 1234 5678"
            maxLength={20}
            disabled={createMutation.isPending || updateMutation.isPending}
            className="w-full px-3 py-2 border border-slate-700 rounded-md focus:outline-none focus:ring-2 focus:ring-orange-500"
          />
          {isSearching && (
            <span className="absolute right-3 top-1/2 -translate-y-1/2 text-slate-500">
              🔍
            </span>
          )}
          {found && !isSearching && (
            <span className="absolute right-3 top-1/2 -translate-y-1/2 text-green-500">
              ✅
            </span>
          )}
        </div>
        {found && (
          <p className="mt-1 text-sm text-green-400">
            Cliente encontrado: {customer?.name}
          </p>
        )}
      </div>

      {/* Formulario de datos */}
      {(!found || editMode) && (
        <div className="space-y-3 border-t pt-4">
          <div>
            <label htmlFor="customer-name" className="block text-sm font-medium text-slate-300 mb-1">
              Nombre completo *
            </label>
            <input
              id="customer-name"
              type="text"
              value={formData.name}
              onChange={(e) => setFormData({ ...formData, name: e.target.value })}
              placeholder="María González"
              className="w-full px-3 py-2 border border-slate-700 rounded-md focus:outline-none focus:ring-2 focus:ring-orange-500"
            />
          </div>

          <div>
            <label htmlFor="customer-email" className="block text-sm font-medium text-slate-300 mb-1">
              Email (opcional)
            </label>
            <input
              id="customer-email"
              type="email"
              value={formData.email}
              onChange={(e) => setFormData({ ...formData, email: e.target.value })}
              placeholder="maria@example.com"
              className="w-full px-3 py-2 border border-slate-700 rounded-md focus:outline-none focus:ring-2 focus:ring-orange-500"
            />
          </div>

          <div>
            <label htmlFor="customer-address" className="block text-sm font-medium text-slate-300 mb-1">
              Dirección de entrega *
            </label>
            <input
              id="customer-address"
              type="text"
              value={formData.address}
              onChange={(e) => setFormData({ ...formData, address: e.target.value })}
              placeholder="Av. Providencia 1234, Depto 501"
              className="w-full px-3 py-2 border border-slate-700 rounded-md focus:outline-none focus:ring-2 focus:ring-orange-500"
            />
          </div>

          <div className="grid grid-cols-2 gap-3">
            <div>
              <label htmlFor="customer-commune" className="block text-sm font-medium text-slate-300 mb-1">
                Comuna
              </label>
              <input
                id="customer-commune"
                type="text"
                value={formData.commune}
                onChange={(e) => setFormData({ ...formData, commune: e.target.value })}
                placeholder="Providencia"
                className="w-full px-3 py-2 border border-slate-700 rounded-md focus:outline-none focus:ring-2 focus:ring-orange-500"
              />
            </div>
            <div>
              <label htmlFor="customer-reference" className="block text-sm font-medium text-slate-300 mb-1">
                Referencia
              </label>
              <input
                id="customer-reference"
                type="text"
                value={formData.address_reference}
                onChange={(e) => setFormData({ ...formData, address_reference: e.target.value })}
                placeholder="Frente al parque"
                className="w-full px-3 py-2 border border-slate-700 rounded-md focus:outline-none focus:ring-2 focus:ring-orange-500"
              />
            </div>
          </div>

          <div>
            <label htmlFor="customer-notes" className="block text-sm font-medium text-slate-300 mb-1">
              Notas (opcional)
            </label>
            <textarea
              id="customer-notes"
              value={formData.notes}
              onChange={(e) => setFormData({ ...formData, notes: e.target.value })}
              placeholder="Cliente frecuente, prefiere llamar antes de entregar"
              rows={2}
              className="w-full px-3 py-2 border border-slate-700 rounded-md focus:outline-none focus:ring-2 focus:ring-orange-500"
            />
          </div>

          {!found && (
            <button
              onClick={handleCreate}
              disabled={!isValid || createMutation.isPending}
              className="w-full bg-orange-500 text-white py-2 px-4 rounded-md hover:bg-orange-600 disabled:bg-slate-600 disabled:cursor-not-allowed"
            >
              {createMutation.isPending ? 'Creando...' : 'Crear cliente nuevo'}
            </button>
          )}
        </div>
      )}

      {/* Acciones para cliente existente */}
      {found && !editMode && (
        <div className="flex gap-2 border-t pt-4">
          <button
            onClick={() => setEditMode(true)}
            className="flex-1 bg-slate-700 text-slate-300 py-2 px-4 rounded-md hover:bg-slate-600"
          >
            ✏️ Editar datos
          </button>
          <button
            onClick={handleUpdate}
            disabled={updateMutation.isPending}
            className="flex-1 bg-orange-500 text-white py-2 px-4 rounded-md hover:bg-orange-600 disabled:bg-slate-600"
          >
            {updateMutation.isPending ? 'Guardando...' : '💾 Guardar cambios'}
          </button>
        </div>
      )}
    </div>
  );
}
