<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Resíduo do domínio de comércio/delivery, sem sentido para venda de
 * ingressos: link de rastreio por WhatsApp (entrega física), tempo
 * estimado de preparo (cozinha/produção) e compra mínima do canal
 * público (varejo).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tenant_settings', function (Blueprint $table) {
            $table->dropColumn(['send_tracking_link_whatsapp', 'minimum_order_value', 'estimated_preparation_minutes']);
        });
    }

    public function down(): void
    {
        Schema::table('tenant_settings', function (Blueprint $table) {
            $table->boolean('send_tracking_link_whatsapp')->default(false);
            $table->decimal('minimum_order_value', 10, 2)->nullable();
            $table->unsignedInteger('estimated_preparation_minutes')->nullable();
        });
    }
};
