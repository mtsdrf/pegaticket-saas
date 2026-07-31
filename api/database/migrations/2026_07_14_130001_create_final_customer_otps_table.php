<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Código de login sem senha (OTP por e-mail, 6 dígitos, validade de 10
 * minutos). `code_hash` guarda sha512 do código (nunca texto puro, mesmo
 * padrão de `tenant_user_invites.token_hash`). `attempts` protege contra
 * força bruta no verify (bloqueia depois de N tentativas erradas). Sem
 * uuid/soft-delete: tabela auxiliar efêmera de suporte a autenticação, nunca
 * exposta por rota nem referenciada fora de App\Services\Portal\PortalAuthService
 * — mesmo espírito de app/Models/Auth/{RefreshToken,TokenBlacklist} (também
 * fora do padrão BaseModel).
 */
return new class extends Migration {
    public function up(): void
    {
        Schema::create('final_customer_otps', function (Blueprint $table) {
            $table->id();

            $table->foreignId('final_customer_id')
                ->constrained('final_customers')
                ->cascadeOnDelete();

            $table->string('code_hash');
            $table->timestamp('expires_at');
            $table->timestamp('consumed_at')->nullable();
            $table->unsignedTinyInteger('attempts')->default(0);

            $table->timestamp('created_at')->useCurrent();

            $table->index(['final_customer_id', 'consumed_at'], 'idx_final_customer_otp_active');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('final_customer_otps');
    }
};
