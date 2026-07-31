<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Promoção percentual (roadmap Delivery — gap-analysis de catálogo) —
 * `discount_type='fixed_price'` (default) preserva 100% o comportamento
 * atual ("de/por" com `promo_price` absoluto, congelado). `discount_type=
 * 'percentage'` usa `discount_percentage` calculado em cima do
 * `Product.price` VIGENTE no momento da leitura/venda (não congelado),
 * porque é % sobre um preço que pode mudar — mesma decisão documentada em
 * ProductPromotion::effectivePrice(). `promo_price` vira nullable: só é
 * obrigatório para `fixed_price` (validado em ProductPromotionRequest).
 */
return new class extends Migration {
    public function up(): void
    {
        Schema::table('product_promotions', function (Blueprint $table) {
            $table->string('discount_type', 20)->default('fixed_price')->after('product_id');
            $table->decimal('discount_percentage', 5, 2)->nullable()->after('discount_type');
            $table->decimal('promo_price', 10, 2)->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('product_promotions', function (Blueprint $table) {
            $table->dropColumn(['discount_type', 'discount_percentage']);
            $table->decimal('promo_price', 10, 2)->nullable(false)->change();
        });
    }
};
