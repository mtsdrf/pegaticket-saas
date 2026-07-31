<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('marketplace_merchants', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique()->index();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('integration_id')->constrained('marketplace_integrations')->cascadeOnDelete();
            $table->string('external_id')->index();
            $table->string('name');
            $table->boolean('is_active')->default(true)->index();
            $table->json('status_payload')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamp('last_seen_at')->nullable();
            $table->unsignedBigInteger('created_by')->nullable()->index();
            $table->unsignedBigInteger('updated_by')->nullable()->index();
            $table->unsignedBigInteger('deleted_by')->nullable()->index();
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['integration_id', 'external_id'], 'uniq_marketplace_merchant_external');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('marketplace_merchants');
    }
};
