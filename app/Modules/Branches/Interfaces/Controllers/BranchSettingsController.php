<?php

namespace Modules\Branches\Interfaces\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Modules\Branches\Domain\Entities\Branch;

class BranchSettingsController extends Controller
{
    /**
     * PATCH /api/v1/branch/settings
     * Actualiza la configuración de la sucursal del usuario autenticado.
     */
    public function update(Request $request): JsonResponse
    {
        $user = $request->user();
        
        $validator = Validator::make($request->all(), [
            'table_order_mode' => 'required|in:card,visual,free',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'Datos de validación inválidos.',
                'errors' => $validator->errors(),
            ], 422);
        }

        // Buscar la sucursal del usuario y actualizar
        $branch = Branch::where('id', $user->branch_id)->first();

        if (!$branch) {
            return response()->json(['message' => 'Sucursal no encontrada.'], 404);
        }

        $branch->update([
            'table_order_mode' => $request->table_order_mode,
        ]);

        return response()->json([
            'message' => 'Configuración actualizada correctamente.',
            'data' => [
                'table_order_mode' => $branch->table_order_mode,
            ],
        ]);
    }
}
