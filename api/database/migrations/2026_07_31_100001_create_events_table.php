<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('events', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique()->index();

            $table->unsignedBigInteger('tenant_id')->index();
            $table->foreignId('event_category_id')
                ->nullable()
                ->constrained('event_categories')
                ->nullOnDelete();

            $table->string('name');
            $table->string('slug');
            $table->string('description_short')->nullable();
            $table->text('description_full')->nullable();

            $table->string('cover_image_path')->nullable();
            $table->binary('cover_image_data')->nullable();
            $table->string('cover_image_mime')->nullable();
            $table->timestamp('cover_image_updated_at')->nullable();

            $table->string('type')->default('ingresso');
            $table->string('location_name')->nullable();
            $table->string('location_address')->nullable();

            $table->dateTime('starts_at');
            $table->dateTime('ends_at');

            $table->string('visibility')->default('public')->index();
            $table->string('status')->default('rascunho')->index();

            $table->unsignedBigInteger('created_by')->nullable()->index();
            $table->unsignedBigInteger('updated_by')->nullable()->index();
            $table->unsignedBigInteger('deleted_by')->nullable()->index();

            $table->timestamps();
            $table->softDeletes();

            $table->unique(['tenant_id', 'slug'], 'uniq_tenant_event_slug');

            $table->foreign('tenant_id')
                ->references('id')
                ->on('tenants')
                ->cascadeOnDelete();
        });

        if (DB::connection()->getDriverName() === 'mysql') {
            DB::statement('ALTER TABLE events MODIFY cover_image_data LONGBLOB NULL');
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('events');
    }
};
