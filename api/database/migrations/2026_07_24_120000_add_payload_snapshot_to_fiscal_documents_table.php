<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('fiscal_documents', function (Blueprint $table) {
            // Snapshot fiscal estruturado do documento enquanto ainda não
            // existe XML oficial/autorizado. Serve de base para emissão
            // real futura sem precisar recalcular tudo do pedido toda vez.
            $table->json('payload_snapshot')->nullable()->after('provider_document_id');
        });
    }

    public function down(): void
    {
        Schema::table('fiscal_documents', function (Blueprint $table) {
            $table->dropColumn('payload_snapshot');
        });
    }
};
