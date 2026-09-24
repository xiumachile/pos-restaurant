<?php

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Modules\Companies\Domain\Entities\Company;
use Modules\Identity\Domain\Entities\User;

/**
 * P2-002: Validar que el manejo de idempotencia funciona bajo concurrencia REAL.
 * 
 * NOTA: No usamos RefreshDatabase aquí porque pcntl_fork crea nuevas conexiones
 * que no pueden ver las transacciones no confirmadas del proceso padre.
 * En su lugar, hacemos commit explícito de los datos de setup y limpieza manual.
 */
test('real concurrent requests handle idempotency unique constraint correctly', function () {
    if (!function_exists('pcntl_fork')) {
        $this->markTestSkipped('La extensión pcntl es requerida para pruebas de concurrencia real.');
    }

    // 1. Setup: Crear datos y hacer commit para que sean visibles en las conexiones de los hijos
    $company = Company::create([
        'tax_id' => 'CONC-' . uniqid(),
        'legal_name' => 'Concurrency Test',
        'trade_name' => 'Conc Test',
    ]);
    
    $user = User::create([
        'company_id' => $company->id,
        'name' => 'Conc User',
        'email' => 'conc-' . uniqid() . '@test.com',
        'password' => bcrypt('password123'),
        'role' => 'cashier',
    ]);
    
    // Hacer commit para que los hijos puedan ver estos registros y satisfacer las foreign keys
    DB::commit();

    $idempotencyKey = Str::uuid()->toString();
    $resultsFile = storage_path('logs/concurrency_results_' . uniqid() . '.txt');
    
    if (!is_dir(storage_path('logs'))) {
        mkdir(storage_path('logs'), 0755, true);
    }

    $numProcesses = 5;
    $pids = [];

    for ($i = 0; $i < $numProcesses; $i++) {
        $pid = pcntl_fork();
        if ($pid == -1) {
            throw new \Exception('No se pudo crear el proceso hijo (fork)');
        } elseif ($pid == 0) {
            // === PROCESO HIJO ===
            try {
                // Reconectar a la BD para obtener una conexión independiente
                DB::reconnect();
                
                // Simular el claim atómico (INSERT con restricción UNIQUE)
                DB::table('idempotency_keys')->insert([
                    'key' => $idempotencyKey,
                    'request_hash' => 'hash-' . $i,
                    'endpoint' => '/api/test',
                    'user_id' => $user->id,
                    'company_id' => $company->id,
                    'branch_id' => null,
                    'expires_at' => now()->addHours(24),
                    'processing_until' => now()->addSeconds(60),
                    'response_code' => null,
                    'response_body' => null,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
                
                file_put_contents($resultsFile, "SUCCESS_{$i}\n", FILE_APPEND | LOCK_EX);
            } catch (\Illuminate\Database\QueryException $e) {
                $msg = $e->getMessage();
                if (stripos($msg, 'unique') !== false || stripos($msg, '23505') !== false) {
                    file_put_contents($resultsFile, "CONFLICT_{$i}\n", FILE_APPEND | LOCK_EX);
                } else {
                    file_put_contents($resultsFile, "QUERY_ERROR_{$i}: " . substr($msg, 0, 200) . "\n", FILE_APPEND | LOCK_EX);
                }
            } catch (\Exception $e) {
                file_put_contents($resultsFile, "EXCEPTION_{$i}: " . get_class($e) . " - " . substr($e->getMessage(), 0, 200) . "\n", FILE_APPEND | LOCK_EX);
            }
            
            exit(0);
        } else {
            $pids[] = $pid;
        }
    }

    // Esperar a que todos los procesos hijos terminen
    foreach ($pids as $pid) {
        pcntl_waitpid($pid, $status);
    }

    // 2. Leer y analizar los resultados
    $results = file_exists($resultsFile) ? file($resultsFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) : [];
    
    $successCount = count(preg_grep('/^SUCCESS_/', $results));
    $conflictCount = count(preg_grep('/^CONFLICT_/', $results));
    $errorResults = preg_grep('/^(QUERY_ERROR_|EXCEPTION_)/', $results);
    $errorCount = count($errorResults);

    if (file_exists($resultsFile)) {
        if ($errorCount > 0) {
            echo "\n--- DEBUG ERRORS ---\n";
            foreach ($errorResults as $err) {
                echo $err . "\n";
            }
            echo "--------------------\n";
        }
        unlink($resultsFile);
    }

    // 3. Limpieza manual (ya que no usamos RefreshDatabase con rollback automático)
    DB::table('idempotency_keys')->where('key', $idempotencyKey)->delete();
    DB::table('users')->where('id', $user->id)->delete();
    DB::table('companies')->where('id', $company->id)->delete();

    // 4. Aserciones
    expect($errorCount)->toBe(0, 'No deberían ocurrir errores inesperados. Revisa el output de DEBUG.')
        ->and($successCount)->toBe(1, 'Exactamente UN proceso debería lograr reclamar la idempotency-key exitosamente')
        ->and($conflictCount)->toBe($numProcesses - 1, 'Los procesos restantes deberían recibir una violación de restricción única (simulando 409 Conflict)');
});
