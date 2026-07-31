<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * API pública + webhooks de saída (roadmap A6, item 20). Chave de acesso
 * do tenant para consumir a API pública (`/api/v1/public/*`), distinta do
 * JWT de usuário staff. Só o hash é persistido (`Hash::make`, igual senha)
 * — o texto puro (`mk_live_...`) aparece 1x na resposta do POST e nunca
 * mais depois. Ver App\Http\Middleware\ApiKeyAccess.
 */
return new class extends Migration {
    public function up(): void
    {
        Schema::create('tenant_api_keys', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique()->index();

            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();

            $table->string('name');
            $table->string('key_hash')->unique();
            $table->timestamp('last_used_at')->nullable();
            $table->timestamp('revoked_at')->nullable();

            $table->unsignedBigInteger('created_by')->nullable()->index();
            $table->unsignedBigInteger('updated_by')->nullable()->index();
            $table->unsignedBigInteger('deleted_by')->nullable()->index();

            $table->timestamps();
            $table->softDeletes();

            $table->index(['tenant_id', 'revoked_at'], 'idx_tenant_api_keys_tenant_revoked');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tenant_api_keys');
    }
};
