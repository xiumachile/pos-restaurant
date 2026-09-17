<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('print_jobs', function (Blueprint $table) {
            $table->string('claimed_by', 100)->nullable()->after('status');
            $table->timestamp('claimed_at')->nullable()->after('claimed_by');
            
            $table->index(['status', 'claimed_at']);
        });
    }

    public function down(): void
    {
        Schema::table('print_jobs', function (Blueprint $table) {
            $table->dropIndex(['status', 'claimed_at']);
            $table->dropColumn(['claimed_by', 'claimed_at']);
        });
    }
};
