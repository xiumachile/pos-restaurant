<?php
require __DIR__ . '/../vendor/autoload.php';
$app = require_once __DIR__ . '/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use Illuminate\Support\Facades\DB;

$session = DB::table('cash_sessions')->where('status', 'open')->first();
if (!$session) {
    echo "No hay sesión abierta\n";
    exit;
}

echo "Forzando cierre de sesión: {$session->session_number}\n\n";

$cashSales = DB::table('payments')
    ->where('cash_session_id', $session->id)
    ->where('status', 'completed')
    ->where('method_code', 'CASH')
    ->sum('amount');

$cashTips = DB::table('payments')
    ->where('cash_session_id', $session->id)
    ->where('status', 'completed')
    ->where('method_code', 'CASH')
    ->sum('tip_amount');

$totalTipsPaid = DB::table('tip_payouts')
    ->where('cash_session_id', $session->id)
    ->where('is_voided', false)
    ->sum('amount');

$brutExpected = $session->opening_amount + $cashSales + $cashTips;
$expected = $brutExpected - $totalTipsPaid;

echo "Cálculo:\n";
echo "  Opening: \${$session->opening_amount}\n";
echo "  + Ventas efectivo: \${$cashSales}\n";
echo "  + Propinas efectivo: \${$cashTips}\n";
echo "  = BRUTO: \${$brutExpected}\n";
echo "  - Propinas entregadas: \${$totalTipsPaid}\n";
echo "  = NETO: \${$expected}\n\n";

DB::table('cash_sessions')
    ->where('id', $session->id)
    ->update([
        'status' => 'closed',
        'closing_amount' => $expected,
        'expected_amount' => $expected,
        'difference' => 0,
        'closing_notes' => 'Cierre forzado - validación eliminada',
        'closed_at' => now(),
    ]);

echo "✅ Sesión cerrada con NETO: \${$expected}\n";
