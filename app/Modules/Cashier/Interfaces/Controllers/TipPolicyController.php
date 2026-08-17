<?php

namespace Modules\Cashier\Interfaces\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Modules\Cashier\Domain\Entities\TipPolicy;
use Modules\Cashier\Domain\ValueObjects\CardTipHandling;
use Modules\Cashier\Domain\ValueObjects\TipPolicyType;

class TipPolicyController extends Controller
{
    /**
     * GET /api/v1/cashier/tip-policy
     * Obtiene la política efectiva para la branch del usuario.
     */
    public function show(Request $request): JsonResponse
    {
        $user = $request->user();
        
        $policy = TipPolicy::resolveForBranch($user->company_id, $user->branch_id);
        
        return response()->json([
            'data' => [
                'uuid' => $policy->uuid ?? null,
                'policy_type' => $policy->policy_type->value,
                'policy_label' => $policy->policy_type->label(),
                'card_tip_handling' => $policy->card_tip_handling->value,
                'pool_split_method' => $policy->pool_split_method ?? 'equal',
                'waiter_percentage' => (float) ($policy->waiter_percentage ?? 100),
                'pool_percentage' => (float) ($policy->pool_percentage ?? 0),
                'is_custom' => $policy->exists,
                'is_active' => (bool) $policy->is_active,
            ],
        ]);
    }

    /**
     * PUT /api/v1/cashier/tip-policy
     * Actualiza o crea la política para la branch.
     */
    public function update(Request $request): JsonResponse
    {
        $user = $request->user();
        
        $validated = $request->validate([
            'policy_type' => ['required', 'in:waiter_keeps,shared_pool,percentage_split'],
            'card_tip_handling' => ['required', 'in:cash_payout,payroll,mixed'],
            'pool_split_method' => ['nullable', 'in:equal,by_hours,by_points'],
            'waiter_percentage' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'pool_percentage' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'apply_to' => ['nullable', 'in:company,branch'],
        ]);

        // Validar porcentajes si es percentage_split
        if ($validated['policy_type'] === 'percentage_split') {
            $waiter = (float) ($validated['waiter_percentage'] ?? 0);
            $pool = (float) ($validated['pool_percentage'] ?? 0);
            
            if (abs($waiter + $pool - 100) > 0.01) {
                return response()->json([
                    'error' => 'invalid_percentages',
                    'message' => 'Los porcentajes deben sumar 100%.',
                ], 422);
            }
        }

        $applyToBranch = ($validated['apply_to'] ?? 'branch') === 'branch';
        
        // Buscar política existente o crear nueva
        $policy = TipPolicy::where('company_id', $user->company_id)
            ->where('branch_id', $applyToBranch ? $user->branch_id : null)
            ->active()
            ->orderByDesc('effective_from')
            ->first();

        if ($policy) {
            // Desactivar la anterior
            $policy->update([
                'effective_to' => now(),
                'is_active' => false,
            ]);
        }

        // Crear nueva política
        $newPolicy = TipPolicy::create([
            'company_id' => $user->company_id,
            'branch_id' => $applyToBranch ? $user->branch_id : null,
            'policy_type' => TipPolicyType::from($validated['policy_type']),
            'card_tip_handling' => CardTipHandling::from($validated['card_tip_handling']),
            'pool_split_method' => $validated['pool_split_method'] ?? 'equal',
            'waiter_percentage' => $validated['waiter_percentage'] ?? 100,
            'pool_percentage' => $validated['pool_percentage'] ?? 0,
            'is_active' => true,
            'effective_from' => now(),
        ]);

        return response()->json([
            'data' => [
                'uuid' => $newPolicy->uuid,
                'policy_type' => $newPolicy->policy_type->value,
                'message' => 'Política de propinas actualizada correctamente.',
            ],
        ]);
    }
}
