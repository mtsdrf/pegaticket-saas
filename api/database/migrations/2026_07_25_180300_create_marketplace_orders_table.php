<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('marketplace_orders', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique()->index();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('integration_id')->constrained('marketplace_integrations')->cascadeOnDelete();
            $table->foreignId('marketplace_merchant_id')->nullable()->constrained('marketplace_merchants')->nullOnDelete();
            $table->string('external_id')->index();
            $table->string('display_id')->nullable();
            $table->string('order_number')->nullable();
            $table->string('status', 120)->nullable()->index();
            $table->string('customer_name')->nullable();
            $table->decimal('total_amount', 10, 2)->nullable();
            $table->json('payload');
            $table->timestamp('raw_updated_at')->nullable();
            $table->timestamp('last_synced_at')->nullable();
            $table->unsignedBigInteger('created_by')->nullable()->index();
            $table->unsignedBigInteger('updated_by')->nullable()->index();
            $table->unsignedBigInteger('deleted_by')->nullable()->index();
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['integration_id', 'external_id'], 'uniq_marketplace_order_external');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('marketplace_orders');
    }
};
