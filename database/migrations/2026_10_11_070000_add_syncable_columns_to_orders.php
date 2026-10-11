<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasColumn('orders', 'sync_status')) {
            Schema::table('orders', function (Blueprint $table) {
                $table->string('sync_status', 20)->default('pending')->after('status');
                $table->integer('version')->default(1)->after('sync_status');
                $table->timestamp('last_synced_at')->nullable()->after('version');
            });
        }
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->dropColumn(['sync_status', 'version', 'last_synced_at']);
        });
    }
};
