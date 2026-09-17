<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('dte_folio_ranges')) {
            Schema::create('dte_folio_ranges', function (Blueprint $table) {
                $table->id();
                $table->uuid('uuid')->unique();
                $table->foreignId('company_id')->constrained('companies')->cascadeOnDelete();
                $table->foreignId('branch_id')->constrained('branches')->cascadeOnDelete();
                
                // Tipo de DTE (39=Boleta, 33=Factura, 41=Boleta Exenta, 61=NC, 52=GD)
                $table->integer('dte_type');
                
                // Rango autorizado por SII
                $table->integer('folio_initial'); // Folio inicial del CAF
                $table->integer('folio_final');   // Folio final del CAF
                $table->integer('folio_current'); // Último folio consumido
                
                // CAF XML firmado por el SII (autorización de folios)
                $table->text('caf_xml');
                
                // Metadatos del CAF
                $table->date('authorization_date'); // Fecha en que SII autorizó
                $table->string('authorized_rut', 20)->nullable(); // RUT que solicitó folios
                
                // Estado
                $table->boolean('is_active')->default(true);
                $table->timestamp('closed_at')->nullable(); // Cerrado (cuando se agota)
                
                $table->timestamps();
                $table->softDeletes();

                $table->unique(['company_id', 'branch_id', 'dte_type', 'folio_initial']);
                $table->index(['company_id', 'branch_id', 'dte_type', 'is_active']);
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('dte_folio_ranges');
    }
};
