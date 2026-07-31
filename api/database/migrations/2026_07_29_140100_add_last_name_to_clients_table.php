<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Sobrenome do cliente — aditivo, nullable no banco de propósito: cadastro
 * feito pelo staff (ClientFormPage/StoreClientRequest) continua sem exigir
 * sobrenome, só o checkout público da loja passa a exigi-lo
 * (StorefrontCheckoutRequest).
 */
return new class extends Migration {
    public function up(): void
    {
        Schema::table('clients', function (Blueprint $table) {
            $table->string('last_name')->nullable()->after('name');
        });
    }

    public function down(): void
    {
        Schema::table('clients', function (Blueprint $table) {
            $table->dropColumn('last_name');
        });
    }
};
