<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            // UUID público
            $table->uuid('uuid')->unique()->after('id');

            // Multiempresa (sección 8.2)
            $table->foreignId('company_id')
                ->nullable()
                ->after('uuid')
                ->constrained('companies')
                ->nullOnDelete();

            $table->foreignId('branch_id')
                ->nullable()
                ->after('company_id')
                ->constrained('branches')
                ->nullOnDelete();

            // PIN POS (sección 15 - login rápido 4-6 dígitos)
            $table->string('pos_pin_hash', 255)
                ->nullable()
                ->after('password');

            // Rol POS (sección 15)
            $table->string('role', 50)
                ->default('waiter')
                ->after('pos_pin_hash')
                ->comment('admin, manager, cashier, waiter, kitchen');

            // i18n (sección 9.3)
            $table->string('locale', 10)
                ->default('es-CL')
                ->after('role');

            // Estado
            $table->boolean('is_active')
                ->default(true)
                ->after('locale');

            // Índices multiempresa
            $table->index(['company_id', 'branch_id']);
            $table->index(['company_id', 'is_active']);
            $table->index('role');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropIndex(['company_id', 'branch_id']);
            $table->dropIndex(['company_id', 'is_active']);
            $table->dropIndex(['role']);

            $table->dropForeign(['company_id']);
            $table->dropForeign(['branch_id']);

            $table->dropColumn([
                'uuid',
                'company_id',
                'branch_id',
                'pos_pin_hash',
                'role',
                'locale',
                'is_active',
            ]);
        });
    }
};
