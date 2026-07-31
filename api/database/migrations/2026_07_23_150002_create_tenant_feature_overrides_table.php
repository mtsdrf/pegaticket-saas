<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Feature flag por tenant individual (roadmap A5, item 19) — camada
 * adicional sobre o gate de plano (plan_functionalities). is_enabled=true
 * LIBERA uma functionality mesmo que o plano do tenant não inclua (acesso
 * antecipado/piloto); is_enabled=false BLOQUEIA uma functionality que o
 * plano libera (desativação pontual). Ausência de linha = comportamento
 * inalterado (só o gate de plano decide, como hoje). Ver
 * PermissionService::resolveTenantAllowedFunctionalities() — único ponto
 * que resolve a precedência override > plano.
 */
return new class extends Migration {
    public function up(): void
    {
        Schema::create('tenant_feature_overrides', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique()->index();

            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('functionality_id')->constrained()->cascadeOnDelete();
            $table->boolean('is_enabled')->default(true);

            $table->unsignedBigInteger('created_by')->nullable()->index();
            $table->unsignedBigInteger('updated_by')->nullable()->index();
            $table->unsignedBigInteger('deleted_by')->nullable()->index();

            $table->timestamps();
            $table->softDeletes();

            $table->unique(['tenant_id', 'functionality_id'], 'uniq_tenant_functionality_override');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tenant_feature_overrides');
    }
};
