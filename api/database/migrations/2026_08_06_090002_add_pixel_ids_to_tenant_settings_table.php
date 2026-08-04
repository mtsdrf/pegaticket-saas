<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Pixels de marketing por tenant (Fase 6, fatia 3) — opt-in, nullable.
 * Quando preenchidos, o frontend público da loja (`StorefrontLayout`)
 * injeta o script do Meta Pixel/GA4 condicionalmente; tenant sem nenhum
 * dos dois não carrega nenhum script extra. Provedores suportados nesta
 * rodada: Meta Pixel e Google Analytics 4 — decisão técnica não validada
 * com o usuário (poderia crescer para TikTok Pixel, GTM etc. depois).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tenant_settings', function (Blueprint $table) {
            $table->string('meta_pixel_id', 40)->nullable()->after('affiliate_default_commission_percentage');
            $table->string('google_analytics_id', 40)->nullable()->after('meta_pixel_id');
        });
    }

    public function down(): void
    {
        Schema::table('tenant_settings', function (Blueprint $table) {
            $table->dropColumn(['meta_pixel_id', 'google_analytics_id']);
        });
    }
};
