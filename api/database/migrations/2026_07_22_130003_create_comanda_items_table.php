<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Item de comanda (roadmap Balcão, Fases 1+2). unit_price é congelado no
 * momento de adicionar (via ProductPricingService, mesmo serviço do Order).
 * station_id é resolvido da categoria do produto no momento de adicionar e
 * congelado (não recalcula se a categoria mudar depois). prep_status é uma
 * pequena máquina de estados (queued→sent_to_station→preparing→ready→
 * delivered_to_table, mais cancelled a partir de qualquer estado não-terminal
 * com motivo). tenant_id incluído (diferente do order_items ser sempre via
 * order) para a query de fila de estação (tickets) ser tenant-scoped direto.
 */
return new class extends Migration {
    public function up(): void
    {
        Schema::create('comanda_items', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique()->index();

            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('comanda_id')->constrained('comandas')->cascadeOnDelete();
            $table->foreignId('product_id')->constrained('products')->cascadeOnDelete();
            $table->foreignId('station_id')->nullable()->constrained('stations')->nullOnDelete();

            $table->decimal('qty', 10, 3);
            $table->decimal('unit_price', 10, 2);
            $table->text('notes')->nullable();

            $table->string('prep_status', 30)->default('queued')->index();

            $table->timestamp('sent_to_station_at')->nullable();
            $table->timestamp('preparing_at')->nullable();
            $table->timestamp('ready_at')->nullable();
            $table->timestamp('delivered_at')->nullable();
            $table->timestamp('cancelled_at')->nullable();

            $table->unsignedBigInteger('added_by')->nullable()->index();
            $table->text('cancelled_reason')->nullable();

            $table->unsignedBigInteger('created_by')->nullable()->index();
            $table->unsignedBigInteger('updated_by')->nullable()->index();
            $table->unsignedBigInteger('deleted_by')->nullable()->index();

            $table->timestamps();
            $table->softDeletes();

            $table->index(['comanda_id', 'prep_status']);
            $table->index(['station_id', 'prep_status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('comanda_items');
    }
};
