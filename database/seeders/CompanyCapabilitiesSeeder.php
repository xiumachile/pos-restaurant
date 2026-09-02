<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Modules\Companies\Domain\Entities\Company;
use Modules\Companies\Domain\Entities\CompanyCapability;
use Modules\Companies\Domain\ValueObjects\CapabilityKey;

class CompanyCapabilitiesSeeder extends Seeder
{
    /**
     * Poblar capabilities por defecto para todas las empresas.
     * Todas las capabilities se habilitan por defecto (opt-out).
     */
    public function run(): void
    {
        $companies = Company::all();

        if ($companies->isEmpty()) {
            $this->command->warn('⚠️  No hay empresas. Ejecuta BaseTenantSeeder primero.');
            return;
        }

        foreach ($companies as $company) {
            $this->command->info("Creando capabilities para: {$company->trade_name}");

            // Todas las capabilities habilitadas por defecto
            $capabilities = [
                CapabilityKey::CAN_SPLIT_BILLS->value => true,
                CapabilityKey::CAN_MANAGE_INVENTORY->value => true,
                CapabilityKey::REQUIRES_CASHIER_SESSION->value => true,
                CapabilityKey::CAN_ACCEPT_TIPS->value => true,
                CapabilityKey::HAS_KITCHEN_DISPLAY->value => true,
                CapabilityKey::CAN_PRINT_RECEIPTS->value => true,
                CapabilityKey::SUPPORTS_LOYALTY_PROGRAM->value => true,
                CapabilityKey::CAN_MANAGE_RESERVATIONS->value => true,
            ];

            foreach ($capabilities as $key => $enabled) {
                CompanyCapability::updateOrCreate(
                    [
                        'company_id' => $company->id,
                        'capability_key' => $key,
                    ],
                    [
                        'is_enabled' => $enabled,
                        'settings' => [],
                    ]
                );
            }

            $this->command->info("✅ 8 capabilities creadas para {$company->trade_name}");
        }

        $this->command->info('');
        $this->command->info('=== RESUMEN CAPABILITIES ===');
        $this->command->info('Companies: ' . $companies->count());
        $this->command->info('Capabilities por empresa: 8');
        $this->command->info('Total capabilities: ' . ($companies->count() * 8));
    }
}
