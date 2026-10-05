<?php

namespace Modules\Orders\Interfaces\Controllers;

use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Foundation\Validation\ValidatesRequests;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller as BaseController;
use Modules\Orders\Domain\Entities\OrderNumberingConfig;

class OrderNumberingSettingsController extends BaseController
{
    use AuthorizesRequests, ValidatesRequests;

    public function show(Request $request): JsonResponse
    {
        $branchId = $request->user()->branch_id;

        $config = OrderNumberingConfig::where('branch_id', $branchId)->first();

        if (!$config) {
            $config = OrderNumberingConfig::create([
                'branch_id' => $branchId,
                'is_enabled' => false,
                'prefix' => 'ORD',
                'reset_frequency' => 'daily',
                'current_sequence' => 1,
            ]);
        }

        return response()->json([
            'data' => [
                'is_enabled' => $config->is_enabled,
                'prefix' => $config->prefix,
                'reset_frequency' => $config->reset_frequency,
                'current_sequence' => $config->current_sequence,
                'last_reset_date' => $config->last_reset_date?->toDateString(),
            ]
        ]);
    }

    public function update(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'is_enabled' => 'required|boolean',
            'prefix' => 'required|string|max:10|regex:/^[A-Z0-9]+$/',
            'reset_frequency' => 'required|in:daily,monthly',
        ]);

        $branchId = $request->user()->branch_id;

        $config = OrderNumberingConfig::where('branch_id', $branchId)->first();

        if (!$config) {
            $config = new OrderNumberingConfig(['branch_id' => $branchId]);
        }

        $config->fill($validated);
        $config->save();

        return response()->json([
            'message' => 'Configuración actualizada',
            'data' => [
                'is_enabled' => $config->is_enabled,
                'prefix' => $config->prefix,
                'reset_frequency' => $config->reset_frequency,
                'current_sequence' => $config->current_sequence,
                'last_reset_date' => $config->last_reset_date?->toDateString(),
            ]
        ]);
    }

    public function resetSequence(Request $request): JsonResponse
    {
        $branchId = $request->user()->branch_id;

        $config = OrderNumberingConfig::where('branch_id', $branchId)->firstOrFail();

        $config->current_sequence = 1;
        $config->last_reset_date = now();
        $config->save();

        return response()->json([
            'message' => 'Secuencia reseteada',
            'data' => [
                'current_sequence' => $config->current_sequence,
                'last_reset_date' => $config->last_reset_date->toDateString(),
            ]
        ]);
    }
}
