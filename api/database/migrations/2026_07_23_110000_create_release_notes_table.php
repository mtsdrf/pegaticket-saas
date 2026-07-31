<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Release notes versionadas (roadmap A1.6) — reaproveita o padrão de
 * legal_documents (documento versionado global, sem tenant_id), mas SEM
 * tabela de aceite: é conteúdo informativo, não termo a assinar. `version`
 * é livre/nullable (não precisa seguir semver, "Julho/2026" é válido) —
 * diferente de legal_documents.version, que é obrigatório e forma unique
 * composta com type; aqui não há "type" pra compor.
 */
return new class extends Migration {
    public function up(): void
    {
        Schema::create('release_notes', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique()->index();

            $table->string('title', 150);
            $table->longText('body');
            $table->string('version', 50)->nullable();
            $table->timestamp('published_at')->nullable()->index();

            $table->unsignedBigInteger('created_by')->nullable()->index();
            $table->unsignedBigInteger('updated_by')->nullable()->index();
            $table->unsignedBigInteger('deleted_by')->nullable()->index();

            $table->timestamps();
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('release_notes');
    }
};
