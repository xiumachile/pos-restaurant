<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Fase 3.2: Vincular compras con documentos contables (boleta/factura SII).
     * 
     * Tipos de documento según SII Chile:
     * - boleta (39): compra a persona natural sin RUT
     * - factura (33): compra con RUT del proveedor
     * - factura_exenta (34): productos exentos de IVA
     * - nota_entrada: ajustes internos
     * - otro: otros documentos
     */
    public function up(): void
    {
        Schema::table('raw_ingredient_purchases', function (Blueprint $table) {
            $table->string('document_type', 30)->nullable()->after('purchase_date');
            $table->string('document_number', 50)->nullable()->after('document_type');
            $table->string('supplier_name', 150)->nullable()->after('document_number');
            $table->string('supplier_rut', 20)->nullable()->after('supplier_name');

            // Índice para buscar por documento (contabilidad)
            $table->index(['document_type', 'document_number'], 'purchases_document_index');
        });
    }

    public function down(): void
    {
        Schema::table('raw_ingredient_purchases', function (Blueprint $table) {
            $table->dropIndex('purchases_document_index');
            $table->dropColumn(['document_type', 'document_number', 'supplier_name', 'supplier_rut']);
        });
    }
};
