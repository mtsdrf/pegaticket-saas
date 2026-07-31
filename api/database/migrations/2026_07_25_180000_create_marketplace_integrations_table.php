<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('marketplace_integrations', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique()->index();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->string('provider', 40)->index();
            $table->string('name');
            $table->string('environment', 20)->default('sandbox');
            $table->string('auth_mode', 40)->default('centralized');
            $table->string('status', 30)->default('disconnected')->index();
            $table->boolean('is_active')->default(true)->index();
            $table->string('client_id')->nullable();
            $table->text('client_secret')->nullable();
            $table->text('authorization_code')->nullable();
            $table->text('access_token')->nullable();
            $table->text('refresh_token')->nullable();
            $table->string('merchant_id')->nullable();
            $table->string('webhook_url')->nullable();
            $table->text('polling_merchant_ids')->nullable();
            $table->timestamp('access_token_expires_at')->nullable();
            $table->timestamp('refresh_token_expires_at')->nullable();
            $table->timestamp('last_connected_at')->nullable();
            $table->timestamp('last_synced_at')->nullable();
            $table->timestamp('last_polled_at')->nullable();
            $table->timestamp('last_error_at')->nullable();
            $table->text('last_error_message')->nullable();
            $table->json('settings')->nullable();
            $table->unsignedBigInteger('created_by')->nullable()->index();
            $table->unsignedBigInteger('updated_by')->nullable()->index();
            $table->unsignedBigInteger('deleted_by')->nullable()->index();
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['tenant_id', 'provider'], 'uniq_marketplace_tenant_provider');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('marketplace_integrations');
    }
};
