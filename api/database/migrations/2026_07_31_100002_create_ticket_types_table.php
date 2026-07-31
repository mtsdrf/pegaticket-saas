<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * TicketType = renomeação/adaptação de `products` (roadmap PegaTicket,
 * Fase 4A/2.8) — mesma infraestrutura técnica de imagem/estoque de
 * Product, agora pendurada em Event em vez de ProductType/ProductCategory.
 * Campos de varejo residual (product_type_id, wholesale_*, surcharge_rate,
 * is_available/stock_quantity legado) foram descartados; sku/barcode/
 * brand/unit/lote/validade/serial/min_stock/max_stock/reorder_point/
 * reorder_qty/last_purchase_cost foram MANTIDOS porque o domínio de Estoque (Stock/
 * StockBalance/StockMovement) e os relatórios (AnalyticsService/
 * ReportService) dependem deles e continuam vivos nesta rodada (mecânico,
 * sem redesenho de semântica de estoque — ver roadmap seção 2.4/4).
 */
return new class extends Migration {
    public function up(): void
    {
        Schema::create('ticket_types', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique()->index();

            $table->unsignedBigInteger('tenant_id')->index();
            $table->foreignId('event_id')
                ->constrained('events')
                ->cascadeOnDelete();

            $table->string('name');
            $table->text('description')->nullable();
            $table->decimal('price', 10, 2);

            $table->string('image_path')->nullable();
            $table->binary('image_data')->nullable();
            $table->string('image_mime')->nullable();
            $table->timestamp('image_updated_at')->nullable();

            $table->integer('quantity_available')->nullable();
            $table->integer('min_per_order')->default(1);
            $table->integer('max_per_order')->nullable();
            $table->dateTime('sales_start_at')->nullable();
            $table->dateTime('sales_end_at')->nullable();
            $table->string('status')->default('rascunho')->index();

            // Herdado de `products` para não quebrar o domínio de Estoque
            // (Stock/StockBalance/StockMovement) e relatórios existentes.
            $table->string('sku')->nullable();
            $table->string('barcode')->nullable();
            $table->string('brand')->nullable();
            $table->string('unit')->default('un');
            $table->boolean('is_lot_controlled')->default(false);
            $table->boolean('is_expiry_controlled')->default(false);
            $table->boolean('is_serial_controlled')->default(false);
            $table->decimal('min_stock', 12, 3)->nullable();
            $table->decimal('max_stock', 12, 3)->nullable();
            $table->decimal('reorder_point', 12, 3)->nullable();
            $table->decimal('reorder_qty', 12, 3)->nullable();
            $table->decimal('last_purchase_cost', 10, 2)->nullable();

            $table->unsignedBigInteger('created_by')->nullable()->index();
            $table->unsignedBigInteger('updated_by')->nullable()->index();
            $table->unsignedBigInteger('deleted_by')->nullable()->index();

            $table->timestamps();
            $table->softDeletes();

            $table->unique(['tenant_id', 'sku'], 'uniq_tenant_ticket_type_sku');

            $table->foreign('tenant_id')
                ->references('id')
                ->on('tenants')
                ->cascadeOnDelete();
        });

        if (DB::connection()->getDriverName() === 'mysql') {
            DB::statement('ALTER TABLE ticket_types MODIFY image_data LONGBLOB NULL');
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('ticket_types');
    }
};
