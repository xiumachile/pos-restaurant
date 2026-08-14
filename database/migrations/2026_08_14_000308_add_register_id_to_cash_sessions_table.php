<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasColumn('cash_sessions', 'register_id')) {
            Schema::table('cash_sessions', function (Blueprint $table) {
                $table->foreignId('register_id')
                    ->after('user_id')
                    ->nullable()
                    ->constrained('cash_registers')
                    ->nullOnDelete();
                
                $table->index(['branch_id', 'register_id', 'status']);
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('cash_sessions', 'register_id')) {
            Schema::table('cash_sessions', function (Blueprint $table) {
                $table->dropForeign(['register_id']);
                $table->dropIndex(['branch_id', 'register_id', 'status']);
                $table->dropColumn('register_id');
            });
        }
    }
};
