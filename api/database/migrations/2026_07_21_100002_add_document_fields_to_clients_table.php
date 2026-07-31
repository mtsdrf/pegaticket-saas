<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Identidade documental do cliente (CPF/CNPJ + inscrição estadual). Não é
 * um recurso fiscal — é usado como identificação obrigatória do cliente em
 * StoreClientRequest/ClientService/ClientResource, independente de emissão
 * de nota fiscal (que o PegaTicket não emite na v1). Recriado a partir da
 * migration fiscal original (`add_fiscal_fields_to_clients_table`) removida
 * na Fase 2 da migração PegaTicket -> PegaTicket.
 */
return new class extends Migration {
    public function up(): void
    {
        Schema::table('clients', function (Blueprint $table) {
            $table->string('cpf_cnpj', 14)->nullable()->after('name');
            $table->string('ie')->nullable()->after('cpf_cnpj');
            $table->string('ie_indicator', 20)->nullable()->after('ie'); // contribuinte|isento|nao_contribuinte
        });
    }

    public function down(): void
    {
        Schema::table('clients', function (Blueprint $table) {
            $table->dropColumn(['cpf_cnpj', 'ie', 'ie_indicator']);
        });
    }
};
