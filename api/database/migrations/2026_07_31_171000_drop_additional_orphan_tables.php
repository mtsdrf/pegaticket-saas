<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Schema;

/**
 * Continuação da limpeza de schema após a execução da migration anterior.
 *
 * Esta migration cobre apenas tabelas identificadas depois como órfãs/no-op
 * no produto atual, para não depender de editar uma migration já executada.
 */
return new class extends Migration
{
    public function up(): void
    {
        // Estruturas que ficaram apenas no schema/model, sem fluxo ativo.
        Schema::dropIfExists('cashback_redemptions');
        Schema::dropIfExists('cashback_earnings');

        // Legado antigo de favoritos por produto, substituído por favoritos de evento.
        Schema::dropIfExists('product_favorites');

        // Autenticação operacional planejada, mas não implementada no produto atual.
        Schema::dropIfExists('user_pins');
    }

    public function down(): void
    {
        // Limpeza destrutiva deliberada: não recriamos schema órfão/legado.
    }
};
