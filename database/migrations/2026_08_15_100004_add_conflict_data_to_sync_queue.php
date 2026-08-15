<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasColumn('sync_queue', 'conflict_data')) {
            Schema::table('sync_queue', function (Blueprint $table) {
                $table->json('conflict_data')->nullable()->after('error_message');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('sync_queue', 'conflict_data')) {
            Schema::table('sync_queue', function (Blueprint $table) {
                $table->dropColumn('conflict_data');
            });
        }
    }
};
