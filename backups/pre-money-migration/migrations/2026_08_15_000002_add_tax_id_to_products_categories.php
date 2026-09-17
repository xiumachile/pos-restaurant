<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Agregar tax_id a products
        if (!Schema::hasColumn('products', 'tax_id')) {
            Schema::table('products', function (Blueprint $table) {
                $table->foreignId('tax_id')
                    ->after('category_id')
                    ->nullable()
                    ->constrained('taxes')
                    ->nullOnDelete();
                
                $table->index(['company_id', 'tax_id']);
            });
        }

        // Agregar tax_id a categories
        if (!Schema::hasColumn('categories', 'tax_id')) {
            Schema::table('categories', function (Blueprint $table) {
                $table->foreignId('tax_id')
                    ->after('branch_id')
                    ->nullable()
                    ->constrained('taxes')
                    ->nullOnDelete();
                
                $table->index(['company_id', 'tax_id']);
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('products', 'tax_id')) {
            Schema::table('products', function (Blueprint $table) {
                $table->dropForeign(['tax_id']);
                $table->dropIndex(['company_id', 'tax_id']);
                $table->dropColumn('tax_id');
            });
        }

        if (Schema::hasColumn('categories', 'tax_id')) {
            Schema::table('categories', function (Blueprint $table) {
                $table->dropForeign(['tax_id']);
                $table->dropIndex(['company_id', 'tax_id']);
                $table->dropColumn('tax_id');
            });
        }
    }
};
