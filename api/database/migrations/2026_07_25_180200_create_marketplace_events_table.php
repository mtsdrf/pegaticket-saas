<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('marketplace_events', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique()->index();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('integration_id')->constrained('marketplace_integrations')->cascadeOnDelete();
            $table->foreignId('marketplace_merchant_id')->nullable()->constrained('marketplace_merchants')->nullOnDelete();
            $table->string('external_event_id')->nullable()->index();
            $table->string('external_order_id')->nullable()->index();
            $table->string('event_type', 120)->index();
            $table->string('event_full_code', 180)->nullable();
            $table->json('payload');
            $table->string('status', 30)->default('pending')->index();
            $table->timestamp('occurred_at')->nullable()->index();
            $table->timestamp('acknowledged_at')->nullable();
            $table->timestamp('processed_at')->nullable();
            $table->text('error_message')->nullable();
            $table->unsignedBigInteger('created_by')->nullable()->index();
            $table->unsignedBigInteger('updated_by')->nullable()->index();
            $table->unsignedBigInteger('deleted_by')->nullable()->index();
            $table->timestamps();
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('marketplace_events');
    }
};
