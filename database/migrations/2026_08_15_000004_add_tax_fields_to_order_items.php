<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasColumn('order_items', 'tax_amount')) {
            Schema::table('order_items', function (Blueprint $table) {
                $table->decimal('tax_amount', 14, 2)->default(0)->after('subtotal');
                $table->decimal('tax_rate_snapshot', 10, 4)->nullable()->after('tax_amount');
                $table->string('tax_name_snapshot', 100)->nullable()->after('tax_rate_snapshot');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('order_items', 'tax_amount')) {
            Schema::table('order_items', function (Blueprint $table) {
                $table->dropColumn(['tax_amount', 'tax_rate_snapshot', 'tax_name_snapshot']);
            });
        }
    }
};
