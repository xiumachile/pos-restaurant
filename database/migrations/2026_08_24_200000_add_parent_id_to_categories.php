<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('categories', function (Blueprint $table) {
            $table->foreignId('parent_id')
                ->nullable()
                ->after('company_id')
                ->constrained('categories')
                ->nullOnDelete();
            
            $table->unsignedTinyInteger('depth')
                ->default(0)
                ->after('parent_id')
                ->comment('0 = raíz, 1 = subcategoría (máximo 2 niveles)');
            
            $table->index(['company_id', 'branch_id', 'parent_id']);
        });
    }

    public function down(): void
    {
        Schema::table('categories', function (Blueprint $table) {
            $table->dropForeign(['parent_id']);
            $table->dropColumn(['parent_id', 'depth']);
        });
    }
};
