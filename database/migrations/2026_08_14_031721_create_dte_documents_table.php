<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('dte_documents')) {
            Schema::create('dte_documents', function (Blueprint $table) {
                $table->id();
                $table->uuid('uuid')->unique();
                $table->foreignId('company_id')->constrained('companies')->cascadeOnDelete();
                $table->foreignId('branch_id')->constrained('branches')->cascadeOnDelete();
                
                // Identificación del DTE
                $table->integer('dte_type'); // 39, 41, 33, 61, 52
                $table->integer('folio');
                
                // Referencia al pedido (nullable para NC/GD sin pedido directo)
                $table->foreignId('order_id')->nullable()->constrained('orders')->nullOnDelete();
                
                // Datos del receptor
                $table->string('receiver_rut', 20)->nullable(); // null = boleta sin RUT
                $table->string('receiver_business_name', 200)->nullable();
                
                // Montos (en CLP)
                $table->decimal('net_amount', 12, 2);    // Neto (sin IVA)
                $table->decimal('tax_amount', 12, 2);    // IVA
                $table->decimal('exempt_amount', 12, 2)->default(0); // Exento
                $table->decimal('total_amount', 12, 2);  // Total
                
                // Datos técnicos del SII
                $table->longText('sent_xml')->nullable();      // XML enviado al SII
                $table->longText('timbre_xml')->nullable();    // TED (código QR timbrado)
                $table->bigInteger('track_id')->nullable();    // Track ID de SII
                $table->string('sii_status', 30)->default('pending');
                $table->string('sii_status_description', 500)->nullable();
                
                // Fechas
                $table->date('issue_date');      // Fecha de emisión
                $table->timestamp('sent_at')->nullable(); // Enviado a SII
                $table->timestamp('accepted_at')->nullable();
                
                // Referencia a otro DTE (para Notas de Crédito)
                $table->foreignId('referenced_dte_id')->nullable()
                    ->constrained('dte_documents')->nullOnDelete();
                
                $table->timestamps();
                $table->softDeletes();

                // Unicidad: un folio por tipo por sucursal
                $table->unique(['company_id', 'branch_id', 'dte_type', 'folio']);
                
                // Índices para reportes (Libro de Ventas)
                $table->index(['company_id', 'branch_id', 'issue_date']);
                $table->index(['company_id', 'dte_type', 'sii_status']);
                $table->index(['order_id']);
                $table->index(['track_id']);
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('dte_documents');
    }
};
