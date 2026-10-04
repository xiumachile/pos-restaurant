<?php

namespace Modules\Tables\Interfaces\Controllers;

use App\Shared\Interfaces\Http\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Modules\Tables\Domain\Entities\Area;
use Illuminate\Support\Str;

class AreaController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();
        $areas = Area::where('branch_id', $user->branch_id)
            ->where('is_active', true)
            ->orderBy('sort_order')
            ->orderBy('code')
            ->get();

        return response()->json(['data' => $areas]);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'code' => 'required|string|max:50|regex:/^[A-Z0-9_-]+$/',
            'name_translations.es' => 'required|string|max:100',
            'name_translations.zh' => 'required|string|max:100',
            'sort_order' => 'nullable|integer|min:0',
        ]);

        $user = $request->user();

        $exists = Area::where('branch_id', $user->branch_id)
            ->where('code', strtoupper($validated['code']))
            ->whereNull('deleted_at')
            ->exists();

        if ($exists) {
            return response()->json([
                'error' => 'area_code_exists',
                'message' => 'Ya existe un área con este código en la sucursal'
            ], 422);
        }

        $area = Area::create([
            'uuid' => Str::uuid(),
            'company_id' => $user->company_id,
            'branch_id' => $user->branch_id,
            'code' => strtoupper($validated['code']),
            'name_translations' => $validated['name_translations'],
            'sort_order' => $validated['sort_order'] ?? 0,
            'is_active' => true,
        ]);

        return response()->json(['data' => $area], 201);
    }

    public function update(Request $request, string $uuid): JsonResponse
    {
        $validated = $request->validate([
            'code' => 'sometimes|string|max:50|regex:/^[A-Z0-9_-]+$/',
            'name_translations.es' => 'sometimes|string|max:100',
            'name_translations.zh' => 'sometimes|string|max:100',
            'sort_order' => 'nullable|integer|min:0',
        ]);

        $user = $request->user();
        $area = Area::where('uuid', $uuid)
            ->where('branch_id', $user->branch_id)
            ->firstOrFail();

        if (isset($validated['code'])) {
            $validated['code'] = strtoupper($validated['code']);
            $exists = Area::where('branch_id', $user->branch_id)
                ->where('code', $validated['code'])
                ->where('id', '!=', $area->id)
                ->whereNull('deleted_at')
                ->exists();

            if ($exists) {
                return response()->json([
                    'error' => 'area_code_exists',
                    'message' => 'Ya existe un área con este código'
                ], 422);
            }
        }

        $area->update($validated);
        return response()->json(['data' => $area]);
    }

    public function destroy(Request $request, string $uuid): JsonResponse
    {
        $user = $request->user();
        $area = Area::where('uuid', $uuid)
            ->where('branch_id', $user->branch_id)
            ->firstOrFail();

        // Verificar si hay mesas usando este area_code
        $tablesCount = \Modules\Tables\Domain\Entities\RestaurantTable::where('branch_id', $user->branch_id)
            ->where('area_code', $area->code)
            ->whereNull('deleted_at')
            ->count();

        if ($tablesCount > 0) {
            return response()->json([
                'error' => 'area_has_tables',
                'message' => "No se puede eliminar: hay {$tablesCount} mesa(s) usando esta área. Primero reasigna o elimina las mesas."
            ], 422);
        }

        $area->delete();
        return response()->json(['message' => 'Área eliminada correctamente']);
    }
}
