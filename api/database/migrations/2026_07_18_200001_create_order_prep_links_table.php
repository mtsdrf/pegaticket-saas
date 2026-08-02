<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Link temporário de preparo do pedido (roadmap Loja) — token curto e
 * expira sozinho, aberto sem login pelo celular (QR code). Mesmo desvio
 * deliberado de coupon_redemptions/final_customer_tenant_links: sem
 * BaseModel/soft delete/created_by — só o sistema cria essas linhas, nunca
 * são editadas por staff, e cada geração é independente (podem coexistir,
 * expiram sozinhas).
 */
return new class extends Migration {
    public function up(): void
    {
        Schema::create('sale_prep_links', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique()->index();

            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('sale_id')->constrained('sales')->cascadeOnDelete();

            $table->string('token', 64)->unique()->index();
            $table->timestamp('expires_at');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sale_prep_links');
    }
};
