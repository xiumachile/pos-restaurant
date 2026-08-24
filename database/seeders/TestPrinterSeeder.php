<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Modules\Printers\Domain\Entities\Printer;
use Modules\Printers\Domain\ValueObjects\PrinterType;
use Modules\Printers\Domain\ValueObjects\ConnectionType;

class TestPrinterSeeder extends Seeder
{
    public function run(): void
    {
        // IDs conocidos del admin (verificados anteriormente)
        $companyId = 2;
        $branchId = 3;

        $existing = Printer::where('company_id', $companyId)
            ->where('branch_id', $branchId)
            ->count();

        if ($existing > 0) {
            $this->command->info("ℹ️  Ya existen $existing impresoras. Saltando.");
            return;
        }

        $printer = Printer::create([
            'company_id' => $companyId,
            'branch_id' => $branchId,
            'name' => 'Impresora Cocina Test',
            'type' => PrinterType::KITCHEN,
            'connection_type' => ConnectionType::TCP,
            'host' => '192.168.1.100',
            'port' => 9100,
            'paper_width' => 80,
            'auto_cut' => true,
            'open_drawer_on_print' => false,
            'is_active' => true,
        ]);

        $this->command->info("✅ Impresora creada: {$printer->uuid}");
        $this->command->info("   Name: {$printer->name}");
        $this->command->info("   Type: {$printer->type->value}");
        $this->command->info("   Connection: {$printer->connection_type->value}");
    }
}
