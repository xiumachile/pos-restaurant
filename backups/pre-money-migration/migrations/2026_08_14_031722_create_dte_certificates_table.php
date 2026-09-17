<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('dte_certificates')) {
            Schema::create('dte_certificates', function (Blueprint $table) {
                $table->id();
                $table->uuid('uuid')->unique();
                $table->foreignId('company_id')->constrained('companies')->cascadeOnDelete();
                
                // Identificación del certificado
                $table->string('name', 200); // "Certificado SII Producción 2026"
                $table->string('serial_number', 100)->nullable();
                $table->string('issuer', 200)->nullable();
                
                // Contenido del certificado (PKCS#12 - .pfx)
                // Se almacena encriptado con llave de la aplicación
                $table->binary('certificate_content');
                
                // RUT del titular (empresa o persona autorizada)
                $table->string('holder_rut', 20);
                $table->string('holder_name', 200);
                
                // Vigencia
                $table->date('valid_from');
                $table->date('valid_until');
                
                // Uso (certificación o producción)
                $table->string('environment', 20)->default('certification'); // certification, production
                
                // Estado
                $table->boolean('is_active')->default(true);
                $table->timestamp('last_used_at')->nullable();
                
                $table->timestamps();
                $table->softDeletes();

                $table->index(['company_id', 'environment', 'is_active']);
                $table->index(['valid_until']);
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('dte_certificates');
    }
};
