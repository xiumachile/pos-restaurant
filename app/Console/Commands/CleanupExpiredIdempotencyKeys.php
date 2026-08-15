<?php

namespace App\Console\Commands;

use App\Shared\Domain\Entities\IdempotencyKey;
use Illuminate\Console\Command;

/**
 * Comando para limpiar keys de idempotencia expiradas.
 * 
 * Ejecutar diariamente vía cron:
 * 0 2 * * * php artisan idempotency:cleanup-expired
 */
class CleanupExpiredIdempotencyKeys extends Command
{
    protected $signature = 'idempotency:cleanup-expired 
                            {--days=1 : Eliminar keys con más de X días de expiradas}
                            {--dry-run : Solo mostrar qué se eliminaría, sin ejecutar}';

    protected $description = 'Elimina keys de idempotencia expiradas de la base de datos';

    public function handle(): int
    {
        $days = (int) $this->option('days');
        $dryRun = $this->option('dry-run');

        $this->info("Buscando keys expiradas hace más de {$days} día(s)...");

        $query = IdempotencyKey::where('expires_at', '<', now()->subDays($days));
        $count = $query->count();

        if ($count === 0) {
            $this->info('No hay keys expiradas para eliminar.');
            return Command::SUCCESS;
        }

        if ($dryRun) {
            $this->warn("[DRY RUN] Se eliminarían {$count} keys expiradas.");
            return Command::SUCCESS;
        }

        $deleted = $query->delete();
        $this->info("✅ {$deleted} keys expiradas eliminadas.");

        return Command::SUCCESS;
    }
}
