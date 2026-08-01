<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Pivot: quais tickets foram afetados por um estorno (obrigatório no
 * estorno parcial — SaleRefundService valida isso antes de persistir).
 * Mesmo padrão estrutural de group_permissions (uuid + soft delete +
 * created_by/updated_by/deleted_by), ver
 * database/migrations/2026_01_04_021008_create_group_permissions_table.php.
 */
return new class extends Migration {
    public function up(): void
    {
        Schema::create('sale_refund_tickets', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique()->index();

            $table->unsignedBigInteger('tenant_id')->index();

            $table->foreignId('sale_refund_id')
                ->constrained('sale_refunds')
                ->cascadeOnDelete();

            $table->foreignId('ticket_id')
                ->constrained('tickets')
                ->cascadeOnDelete();

            $table->unsignedBigInteger('created_by')->nullable()->index();
            $table->unsignedBigInteger('updated_by')->nullable()->index();
            $table->unsignedBigInteger('deleted_by')->nullable()->index();

            $table->timestamps();
            $table->softDeletes();

            $table->unique(['sale_refund_id', 'ticket_id'], 'uniq_sale_refund_ticket');

            $table->foreign('tenant_id')
                ->references('id')
                ->on('tenants')
                ->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sale_refund_tickets');
    }
};
