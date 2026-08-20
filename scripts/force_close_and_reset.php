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

echo "Cerrando sesión: {$session->session_number}\n";

$cashSales = DB::table('payments')->where('cash_session_id', $session->id)->where('status', 'completed')->where('method_code', 'CASH')->sum('amount');
$cashTips = DB::table('payments')->where('cash_session_id', $session->id)->where('status', 'completed')->where('method_code', 'CASH')->sum('tip_amount');
$totalTipsPaid = DB::table('tip_payouts')->where('cash_session_id', $session->id)->where('is_voided', false)->sum('amount');

$brutExpected = $session->opening_amount + $cashSales + $cashTips;
$expected = $brutExpected - $totalTipsPaid;

DB::table('cash_sessions')->where('id', $session->id)->update([
    'status' => 'closed',
    'closing_amount' => $expected,
    'expected_amount' => $expected,
    'difference' => 0,
    'closing_notes' => 'Cierre forzado para pruebas',
    'closed_at' => now(),
]);

echo "✅ Sesión cerrada. NETO: \${$expected}\n";
