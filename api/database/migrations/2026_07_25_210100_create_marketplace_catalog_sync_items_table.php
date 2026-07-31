<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('marketplace_catalog_sync_items', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique('mk_cat_sync_items_uuid_unique');
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('marketplace_catalog_sync_id')
                ->constrained('marketplace_catalog_syncs', indexName: 'mk_cat_sync_items_sync_fk')
                ->cascadeOnDelete();
            $table->foreignId('product_id')
                ->nullable()
                ->constrained('products', indexName: 'mk_cat_sync_items_product_fk')
                ->nullOnDelete();
            $table->string('entity_type', 30)->index('mk_cat_sync_items_entity_type_idx');
            $table->string('entity_key')->index('mk_cat_sync_items_entity_key_idx');
            $table->string('external_entity_id')->nullable()->index('mk_cat_sync_items_external_id_idx');
            $table->string('batch_id')->nullable()->index('mk_cat_sync_items_batch_idx');
            $table->string('status', 30)->default('pending')->index('mk_cat_sync_items_status_idx');
            $table->json('request_payload')->nullable();
            $table->json('response_payload')->nullable();
            $table->timestamp('processed_at')->nullable();
            $table->text('error_message')->nullable();
            $table->unsignedBigInteger('created_by')->nullable()->index('mk_cat_sync_items_created_by_idx');
            $table->unsignedBigInteger('updated_by')->nullable()->index('mk_cat_sync_items_updated_by_idx');
            $table->unsignedBigInteger('deleted_by')->nullable()->index('mk_cat_sync_items_deleted_by_idx');
            $table->timestamps();
            $table->softDeletes();

            $table->unique(
                ['marketplace_catalog_sync_id', 'entity_type', 'entity_key'],
                'uniq_marketplace_catalog_sync_entity'
            );
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('marketplace_catalog_sync_items');
    }
};
