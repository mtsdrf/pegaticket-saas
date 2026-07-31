<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('fiscal_documents', function (Blueprint $table) {
            $table->string('series', 20)->nullable()->after('document_type');
            $table->unsignedInteger('document_number')->nullable()->after('series');

            $table->unique(
                ['tenant_id', 'document_type', 'series', 'document_number'],
                'uniq_fiscal_doc_tenant_type_series_number'
            );
        });
    }

    public function down(): void
    {
        Schema::table('fiscal_documents', function (Blueprint $table) {
            $table->dropUnique('uniq_fiscal_doc_tenant_type_series_number');
            $table->dropColumn(['series', 'document_number']);
        });
    }
};
