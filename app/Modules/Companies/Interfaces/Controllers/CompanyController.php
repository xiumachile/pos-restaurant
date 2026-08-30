<?php

namespace Modules\Companies\Interfaces\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Modules\Companies\Domain\Entities\Company;
use Modules\Companies\Domain\Entities\CompanyCapability;
use Modules\Companies\Domain\ValueObjects\CapabilityKey;
use Modules\Companies\Interfaces\Requests\StoreCompanyRequest;
use Modules\Companies\Interfaces\Requests\UpdateCapabilitiesRequest;
use Modules\Companies\Interfaces\Requests\UpdateCompanyRequest;
use Modules\Companies\Interfaces\Resources\CompanyResource;

class CompanyController extends Controller
{
    /**
     * GET /api/v1/companies
     * Lista empresas. Super-admin ve todas, admin ve solo la suya.
     */
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();

        if ($user->role === 'super_admin') {
            $companies = Company::withCount(['branches', 'users'])->get();
        } else {
            $companies = Company::where('id', $user->company_id)
                ->withCount(['branches', 'users'])
                ->get();
        }

        return CompanyResource::collection($companies)->response();
    }

    /**
     * POST /api/v1/companies
     * Crea nueva empresa (solo super_admin).
     */
    public function store(StoreCompanyRequest $request): JsonResponse
    {
        $validated = $request->validated();

        $company = Company::create([
            'tax_id' => $validated['tax_id'],
            'legal_name' => $validated['legal_name'],
            'trade_name' => $validated['trade_name'],
            'default_locale' => $validated['default_locale'] ?? 'es-CL',
            'fallback_locale' => $validated['fallback_locale'] ?? 'es-CL',
            'is_active' => $validated['is_active'] ?? true,
            'settings' => $validated['settings'] ?? [],
        ]);

        // Habilitar todas las capabilities por defecto si se solicita
        if ($validated['enable_all_capabilities'] ?? true) {
            foreach (CapabilityKey::cases() as $capabilityKey) {
                CompanyCapability::create([
                    'company_id' => $company->id,
                    'capability_key' => $capabilityKey->value,
                    'is_enabled' => true,
                    'settings' => [],
                ]);
            }
        }

        $company->load('capabilities');

        return CompanyResource::make($company)
            ->response()
            ->setStatusCode(201);
    }

    /**
     * GET /api/v1/companies/{uuid}
     * Detalle de empresa.
     */
    public function show(Request $request, string $uuid): JsonResponse
    {
        $company = Company::where('uuid', $uuid)
            ->with(['capabilities'])
            ->withCount(['branches', 'users'])
            ->firstOrFail();

        $this->authorize('view', $company);

        return CompanyResource::make($company)->response();
    }

    /**
     * PUT /api/v1/companies/{uuid}
     * Actualiza empresa.
     */
    public function update(UpdateCompanyRequest $request, string $uuid): JsonResponse
    {
        $company = Company::where('uuid', $uuid)->firstOrFail();

        $this->authorize('update', $company);

        $validated = $request->validated();
        $company->update($validated);

        $company->load('capabilities');

        return CompanyResource::make($company)->response();
    }

    /**
     * DELETE /api/v1/companies/{uuid}
     * Soft delete de empresa.
     */
    public function destroy(Request $request, string $uuid): JsonResponse
    {
        $company = Company::where('uuid', $uuid)->firstOrFail();

        $this->authorize('delete', $company);

        $company->delete();

        return response()->json([
            'message' => 'Empresa eliminada correctamente.',
        ]);
    }

    /**
     * GET /api/v1/companies/{uuid}/capabilities
     * Lista capabilities de la empresa.
     */
    public function getCapabilities(Request $request, string $uuid): JsonResponse
    {
        $company = Company::where('uuid', $uuid)
            ->with('capabilities')
            ->firstOrFail();

        $this->authorize('viewCapabilities', $company);

        return response()->json([
            'data' => $company->capabilities->map(function ($capability) {
                return [
                    'key' => $capability->capability_key,
                    'description' => CapabilityKey::from($capability->capability_key)->description(),
                    'is_enabled' => $capability->is_enabled,
                    'settings' => $capability->settings ?? [],
                ];
            }),
        ]);
    }

    /**
     * PUT /api/v1/companies/{uuid}/capabilities
     * Actualiza todas las capabilities de la empresa (upsert masivo).
     */
    public function updateCapabilities(UpdateCapabilitiesRequest $request, string $uuid): JsonResponse
    {
        $company = Company::where('uuid', $uuid)->firstOrFail();

        $this->authorize('updateCapabilities', $company);

        $validated = $request->validated();

        foreach ($validated['capabilities'] as $cap) {
            CompanyCapability::updateOrCreate(
                [
                    'company_id' => $company->id,
                    'capability_key' => $cap['key'],
                ],
                [
                    'is_enabled' => $cap['is_enabled'],
                    'settings' => $cap['settings'] ?? [],
                ]
            );
        }

        // Invalidar cache de capabilities
        $company->invalidateCapabilitiesCache();

        $company->load('capabilities');

        return response()->json([
            'message' => 'Capabilities actualizadas correctamente.',
            'data' => $company->capabilities->map(function ($capability) {
                return [
                    'key' => $capability->capability_key,
                    'is_enabled' => $capability->is_enabled,
                    'settings' => $capability->settings ?? [],
                ];
            }),
        ]);
    }
}
