<?php
require __DIR__ . '/../vendor/autoload.php';
$app = require_once __DIR__ . '/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

$session = DB::table('cash_sessions')->where('status', 'open')->first();
if (!$session) {
    echo "No hay sesión abierta\n";
    exit;
}

echo "Sesión: {$session->session_number}\n";
echo "Opening: \${$session->opening_amount}\n\n";

$totalTips = DB::table('payments')
    ->where('cash_session_id', $session->id)
    ->where('status', 'completed')
    ->where('tip_amount', '>', 0)
    ->sum('tip_amount');

$paidOut = DB::table('tip_payouts')
    ->where('cash_session_id', $session->id)
    ->where('is_voided', false)
    ->sum('amount');

$pending = $totalTips - $paidOut;

echo "Propinas totales: \${$totalTips}\n";
echo "Ya entregadas: \${$paidOut}\n";
echo "Pendientes: \${$pending}\n\n";

if ($pending > 0) {
    echo "Creando entrega forzada de \${$pending}...\n";
    
    DB::table('tip_payouts')->insert([
        'uuid' => (string) Str::uuid(),
        'company_id' => $session->company_id,
        'branch_id' => $session->branch_id,
        'cash_session_id' => $session->id,
        'waiter_id' => $session->user_id,
        'processed_by' => $session->user_id,
        'amount' => $pending,
        'payment_method' => 'cash',
        'policy_type' => 'waiter_keeps',
        'notes' => 'Entrega forzada para desbloquear cierre',
        'is_voided' => false,
        'created_at' => now(),
        'updated_at' => now(),
    ]);
    
    echo "✅ Entrega creada. Ahora puedes cerrar la caja.\n";
} else {
    echo "✅ No hay propinas pendientes.\n";
}
