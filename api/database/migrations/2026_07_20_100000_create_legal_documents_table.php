<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Termos de Uso / Política de Privacidade versionados (roadmap 1A —
 * endurecimento de produção). Cada linha é uma versão publicada de um
 * documento legal; `is_active` marca a versão vigente por tipo (o app
 * assume no máximo uma ativa por `type`, garantido em nível de aplicação
 * pelo seeder/serviço, não por unique parcial). O aceite do usuário é
 * gravado em `legal_acceptances`.
 */
return new class extends Migration {
    public function up(): void
    {
        Schema::create('legal_documents', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique()->index();

            $table->string('type', 20)->index(); // terms | privacy
            $table->string('version', 50);
            $table->longText('content');
            $table->timestamp('published_at')->nullable();
            $table->boolean('is_active')->default(false)->index();

            $table->unsignedBigInteger('created_by')->nullable()->index();
            $table->unsignedBigInteger('updated_by')->nullable()->index();
            $table->unsignedBigInteger('deleted_by')->nullable()->index();

            $table->timestamps();
            $table->softDeletes();

            $table->unique(['type', 'version'], 'uniq_legal_document_type_version');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('legal_documents');
    }
};
