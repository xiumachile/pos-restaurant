<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Modules\Payments\Domain\Entities\PaymentMethod;
use Modules\Companies\Domain\Entities\Company;
use Modules\Branches\Domain\Entities\Branch;
use Modules\Payments\Domain\ValueObjects\PaymentMethodType;

class PaymentMethodSeeder extends Seeder
{
    public function run(): void
    {
        $company = Company::withoutGlobalScopes()
            ->where('trade_name', 'Wok & Mesa')
            ->first();

        if (!$company) {
            $this->command->error('Compañía Wok & Mesa no existe.');
            return;
        }

        $branch = Branch::withoutGlobalScopes()
            ->where('company_id', $company->id)
            ->where('code', 'MAIN')
            ->first();

        if (!$branch) {
            $this->command->error('Branch MAIN no existe.');
            return;
        }

        $existing = PaymentMethod::withoutGlobalScopes()
            ->where('company_id', $company->id)
            ->count();

        if ($existing > 0) {
            $this->command->info("Ya existen {$existing} métodos de pago. Saltando.");
            return;
        }

        $methods = [
            [
                'code' => 'CASH',
                'name_es' => 'Efectivo',
                'name_zh' => '现金',
                'type' => PaymentMethodType::CASH,
                'icon' => '💵',
                'requires_reference' => false,
                'sort_order' => 1,
            ],
            [
                'code' => 'CARD',
                'name_es' => 'Tarjeta de Crédito/Débito',
                'name_zh' => '信用卡/借记卡',
                'type' => PaymentMethodType::CARD,
                'icon' => '💳',
                'requires_reference' => true,
                'sort_order' => 2,
            ],
            [
                'code' => 'TRANSFER',
                'name_es' => 'Transferencia Bancaria',
                'name_zh' => '银行转账',
                'type' => PaymentMethodType::TRANSFER,
                'icon' => '🏦',
                'requires_reference' => true,
                'sort_order' => 3,
            ],
            [
                'code' => 'GIFT_CARD',
                'name_es' => 'Tarjeta de Regalo',
                'name_zh' => '礼品卡',
                'type' => PaymentMethodType::GIFT_CARD,
                'icon' => '🎁',
                'requires_reference' => true,
                'sort_order' => 4,
            ],
        ];

        foreach ($methods as $method) {
            PaymentMethod::withoutGlobalScopes()->create([
                'company_id' => $company->id,
                'branch_id' => $branch->id,
                'code' => $method['code'],
                'name_translations' => [
                    'es' => $method['name_es'],
                    'zh' => $method['name_zh'],
                ],
                'type' => $method['type'],
                'icon' => $method['icon'],
                'requires_reference' => $method['requires_reference'],
                'is_active' => true,
                'sort_order' => $method['sort_order'],
            ]);
            $this->command->info("✅ Método creado: {$method['icon']} {$method['name_es']}");
        }

        $this->command->info('✅ ' . count($methods) . ' métodos de pago creados.');
    }
}
