<?php

namespace Modules\Customers\Interfaces\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Modules\Customers\Domain\Entities\Customer;
use Modules\Customers\Interfaces\Resources\CustomerResource;

/**
 * Controlador de clientes (CRM básico).
 * 
 * Endpoints:
 * - GET    /api/v1/customers          → Listar clientes de la empresa
 * - GET    /api/v1/customers/search   → Buscar por teléfono (prioritario)
 * - POST   /api/v1/customers          → Crear nuevo cliente
 * - GET    /api/v1/customers/{uuid}   → Detalle de cliente
 * - PUT    /api/v1/customers/{uuid}   → Actualizar cliente
 * - DELETE /api/v1/customers/{uuid}   → Soft delete
 */
class CustomerController extends Controller
{
    /**
     * Búsqueda por teléfono (endpoint prioritario para delivery).
     * 
     * GET /api/v1/customers/search?phone=+56912345678
     * 
     * Retorna:
     * - 200 + customer si existe
     * - 200 + found=false si no existe (no 404 para no revelar información)
     */
    public function search(Request $request): JsonResponse
    {
        $request->validate([
            'phone' => ['required', 'string', 'max:30'],
        ]);

        $user = $request->user();
        $phone = $request->input('phone');

$customer = Customer::findByPhone($phone, $user->company_id);

return response()->json([
            'found' => !is_null($customer),
            'customer' => $customer ? new CustomerResource($customer) : null,
        ]);
    }

    /**
     * Listar clientes de la empresa (paginado).
     */
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();
        $perPage = min((int) $request->input('per_page', 20), 100);

        $customers = Customer::where('company_id', $user->company_id)
            ->when($request->input('search'), function ($query, $search) {
                $query->where(function ($q) use ($search) {
                    $q->where('name', 'ilike', "%{$search}%")
                      ->orWhere('phone', 'ilike', "%{$search}%");
                });
            })
            ->orderBy('name')
            ->paginate($perPage);

        return CustomerResource::collection($customers)
            ->response()
            ->setStatusCode(200);
    }

    /**
     * Crear nuevo cliente.
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'phone' => ['required', 'string', 'max:30'],
            'name' => ['required', 'string', 'max:200'],
            'email' => ['nullable', 'email', 'max:200'],
            'address' => ['nullable', 'string', 'max:500'],
            'commune' => ['nullable', 'string', 'max:100'],
            'address_reference' => ['nullable', 'string', 'max:500'],
            'notes' => ['nullable', 'string'],
        ]);

        $user = $request->user();

        // Verificar que no exista ya un cliente con ese teléfono
        $existing = Customer::findByPhone($validated['phone'], $user->company_id);
        if ($existing) {
            return response()->json([
                'message' => 'Ya existe un cliente con ese teléfono.',
                'errors' => ['phone' => ['El teléfono ya está registrado.']],
            ], 422);
        }

        $customer = Customer::create(array_merge($validated, [
            'company_id' => $user->company_id,
            'branch_id' => $user->branch_id,
        ]));

        return (new CustomerResource($customer))
            ->response()
            ->setStatusCode(201);
    }

    /**
     * Detalle de cliente.
     */
    public function show(Request $request, string $uuid): JsonResponse
    {
        $user = $request->user();

        $customer = Customer::where('uuid', $uuid)
            ->where('company_id', $user->company_id)
            ->firstOrFail();

        return (new CustomerResource($customer))
            ->response()
            ->setStatusCode(200);
    }

    /**
     * Actualizar cliente.
     */
    public function update(Request $request, string $uuid): JsonResponse
    {
        $user = $request->user();

        $customer = Customer::where('uuid', $uuid)
            ->where('company_id', $user->company_id)
            ->firstOrFail();

        $validated = $request->validate([
            'phone' => ['sometimes', 'required', 'string', 'max:30'],
            'name' => ['sometimes', 'required', 'string', 'max:200'],
            'email' => ['nullable', 'email', 'max:200'],
            'address' => ['nullable', 'string', 'max:500'],
            'commune' => ['nullable', 'string', 'max:100'],
            'address_reference' => ['nullable', 'string', 'max:500'],
            'notes' => ['nullable', 'string'],
        ]);

        // Si cambia el teléfono, verificar que no exista otro con ese teléfono
        if (isset($validated['phone']) && $validated['phone'] !== $customer->phone) {
            $existing = Customer::findByPhone($validated['phone'], $user->company_id);
            if ($existing && $existing->id !== $customer->id) {
                return response()->json([
                    'message' => 'Ya existe un cliente con ese teléfono.',
                    'errors' => ['phone' => ['El teléfono ya está registrado.']],
                ], 422);
            }
        }

        $customer->update($validated);

        return (new CustomerResource($customer))
            ->response()
            ->setStatusCode(200);
    }

    /**
     * Eliminar cliente (soft delete).
     */
    public function destroy(Request $request, string $uuid): JsonResponse
    {
        $user = $request->user();

        $customer = Customer::where('uuid', $uuid)
            ->where('company_id', $user->company_id)
            ->firstOrFail();

        $customer->delete();

        return response()->json(null, 204);
    }
}
