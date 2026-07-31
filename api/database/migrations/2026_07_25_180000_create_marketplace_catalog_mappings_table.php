<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('marketplace_catalog_mappings', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('tenant_id')->constrained('tenants');
            $table->foreignId('integration_id')->constrained('marketplace_integrations');
            $table->foreignId('marketplace_merchant_id')->constrained('marketplace_merchants');
            $table->string('entity_type', 40);
            $table->string('entity_key', 255);
            $table->uuid('internal_uuid')->nullable();
            $table->string('external_entity_id', 255);
            $table->json('metadata')->nullable();
            $table->timestamp('last_synced_at')->nullable();
            $table->timestamps();
            $table->softDeletes();
            $table->unsignedBigInteger('created_by')->nullable();
            $table->unsignedBigInteger('updated_by')->nullable();
            $table->unsignedBigInteger('deleted_by')->nullable();

            $table->unique(
                ['integration_id', 'marketplace_merchant_id', 'entity_type', 'entity_key'],
                'marketplace_catalog_mappings_unique_entity'
            );
            $table->index(['tenant_id', 'entity_type'], 'marketplace_catalog_mappings_tenant_type_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('marketplace_catalog_mappings');
    }
};
