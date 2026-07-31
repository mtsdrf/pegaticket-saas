<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Assinatura de um tenant a um plano (roadmap 1B). `status` é string no
 * banco (valores do enum App\Enums\Subscription\SubscriptionStatus).
 * accepted_terms_version/accepted_at/accepted_ip registram o aceite dos
 * termos no momento da contratação.
 */
return new class extends Migration {
    public function up(): void
    {
        Schema::create('subscriptions', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique()->index();

            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('plan_id')->constrained();
            $table->foreignId('plan_price_id')->constrained('plan_prices');
            $table->string('billing_period', 20);
            $table->string('status', 30)->index();

            $table->timestamp('trial_ends_at')->nullable();
            $table->timestamp('current_period_start')->nullable();
            $table->timestamp('current_period_end')->nullable();
            $table->timestamp('next_charge_at')->nullable()->index();
            $table->timestamp('cancel_at')->nullable();
            $table->timestamp('canceled_at')->nullable();
            $table->boolean('auto_renew')->default(true);

            $table->string('accepted_terms_version')->nullable();
            $table->timestamp('accepted_at')->nullable();
            $table->string('accepted_ip', 64)->nullable();

            $table->unsignedBigInteger('created_by')->nullable()->index();
            $table->unsignedBigInteger('updated_by')->nullable()->index();
            $table->unsignedBigInteger('deleted_by')->nullable()->index();

            $table->timestamps();
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('subscriptions');
    }
};
