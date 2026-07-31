<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('client_client_categories', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique()->index();

            $table->unsignedBigInteger('client_id')->index();
            $table->unsignedBigInteger('client_category_id')->index();

            $table->unsignedBigInteger('created_by')->nullable()->index();
            $table->unsignedBigInteger('updated_by')->nullable()->index();
            $table->unsignedBigInteger('deleted_by')->nullable()->index();

            $table->timestamps();
            $table->softDeletes();

            // MySQL-safe: evita duplicidade ativa. Para "reativar", restaurar (deleted_at = null)
            $table->unique(['client_id', 'client_category_id'], 'uniq_client_client_category');

            $table->foreign('client_id')->references('id')->on('clients')->cascadeOnDelete();
            $table->foreign('client_category_id')->references('id')->on('client_categories')->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('client_client_categories');
    }
};
