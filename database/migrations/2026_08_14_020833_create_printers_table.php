<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('printers', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('company_id')->constrained('companies')->cascadeOnDelete();
            $table->foreignId('branch_id')->constrained('branches')->cascadeOnDelete();
            
            // Configuración básica
            $table->string('name', 100); // "Cocina WOK", "Bar", "Caja Principal"
            $table->string('type', 30); // kitchen, bar, receipt
            $table->string('connection_type', 30); // tcp, usb, bluetooth
            
            // Configuración de conexión TCP
            $table->string('host', 100)->nullable(); // 192.168.1.100
            $table->integer('port')->default(9100);
            
            // Configuración de conexión USB/Bluetooth
            $table->string('device_path', 255)->nullable(); // /dev/usb/lp0 o MAC address
            
            // Configuración de impresión
            $table->integer('paper_width')->default(80); // 80mm o 58mm
            $table->boolean('auto_cut')->default(true);
            $table->boolean('open_drawer_on_print')->default(false);
            
            // Estado
            $table->boolean('is_active')->default(true);
            $table->timestamp('last_printed_at')->nullable();
            $table->integer('print_count')->default(0);
            
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['company_id', 'branch_id', 'name']);
            $table->index(['company_id', 'branch_id', 'type']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('printers');
    }
};
