<?php

namespace App\Shared\Domain\Console\Commands;

use Illuminate\Console\Command;
use Modules\Orders\Domain\Entities\Order;
use Modules\Companies\Domain\Entities\Company;

/**
 * Comando de ejemplo que genera reportes diarios.
 * 
 * REQUIERE company_id como parámetro para establecer contexto.
 * Si no se proporciona company_id, o el company no existe,
 * retorna error con mensaje claro.
 */
class GenerateDailyReportCommand extends Command
{
    protected $signature = 'reports:daily {--company= : ID de la empresa}';
    protected $description = 'Genera reporte diario de órdenes';

    public function handle(): int
    {
        $companyId = $this->option('company');

        if (!$companyId) {
            $this->error('Debe proporcionar --company=ID');
            $this->error('Ejemplo: php artisan reports:daily --company=123');
            return Command::FAILURE;
        }

        // Buscar company con manejo de errores
        try {
            $company = Company::withoutGlobalScopes()->findOrFail($companyId);
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            $this->error("Empresa con ID {$companyId} no encontrada");
            $this->error('Verifique que el ID sea válido');
            return Command::FAILURE;
        }
        
        app(\App\Shared\Application\TenantContext::class)->setCompany(
            companyId: $company->id,
            branchId: null, // Todas las branches
            userId: null,
            locale: 'es-CL',
            role: 'admin',
            terminalId: null
        );

        // Ahora podemos consultar Orders con contexto
        $orders = Order::whereDate('created_at', today())->get();
        
        $this->info("Generando reporte para empresa {$company->trade_name}");
        $this->info("Total de órdenes hoy: {$orders->count()}");

        return Command::SUCCESS;
    }
}
