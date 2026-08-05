<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Cota de inventário por canal de venda (roadmap "cotas por canal") —
 * opt-in: um TicketType sem nenhuma linha aqui continua vendendo pelo
 * estoque geral em qualquer canal (comportamento 100% preservado). Quando
 * existe uma linha para (ticket_type_id, channel), aquele canal fica
 * limitado a min(estoque geral restante, quota - já vendido no canal) —
 * ver App\Services\Event\TicketTypeChannelQuotaService::availableForChannel().
 * channel: storefront|staff|affiliate (App\Models\Sale\Sale::CHANNEL_*).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ticket_type_channel_quotas', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique()->index();

            $table->unsignedBigInteger('tenant_id')->index();
            $table->foreignId('ticket_type_id')
                ->constrained('ticket_types')
                ->cascadeOnDelete();

            $table->string('channel', 20);
            $table->unsignedInteger('quota');

            $table->unsignedBigInteger('created_by')->nullable()->index();
            $table->unsignedBigInteger('updated_by')->nullable()->index();
            $table->unsignedBigInteger('deleted_by')->nullable()->index();

            $table->timestamps();
            $table->softDeletes();

            $table->foreign('tenant_id')
                ->references('id')
                ->on('tenants')
                ->cascadeOnDelete();

            $table->unique(['ticket_type_id', 'channel'], 'uniq_ticket_type_channel');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ticket_type_channel_quotas');
    }
};
