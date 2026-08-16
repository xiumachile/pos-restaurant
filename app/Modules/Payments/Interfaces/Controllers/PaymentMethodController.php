<?php

namespace Modules\Payments\Interfaces\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Modules\Payments\Domain\Entities\PaymentMethod;

class PaymentMethodController extends Controller
{
    /**
     * GET /api/v1/payment-methods
     * Lista métodos de pago activos disponibles para la sucursal.
     */
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();

        $methods = PaymentMethod::forBranch($user->branch_id)
            ->active()
            ->orderBy('sort_order')
            ->get();

        return response()->json(['data' => $methods]);
    }
}
