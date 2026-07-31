<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('clients', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique()->index();

            $table->unsignedBigInteger('tenant_id')->index();

            $table->string('name', 90);

            $table->foreignId('endereco_id')->constrained('enderecos')->cascadeOnDelete();
            $table->foreignId('dia_ideal_id')->nullable()->constrained('dia_ideais')->cascadeOnDelete();
            $table->foreignId('periodo_ideal_id')->nullable()->constrained('periodo_ideais')->cascadeOnDelete();

            $table->string('phone_primary')->nullable();
            $table->string('phone_secondary')->nullable();
            $table->text('notes')->nullable();

            $table->boolean('is_trusted')->default(true);
            $table->boolean('is_active')->default(true)->index();

            $table->unsignedBigInteger('created_by')->nullable()->index();
            $table->unsignedBigInteger('updated_by')->nullable()->index();
            $table->unsignedBigInteger('deleted_by')->nullable()->index();

            $table->timestamps();
            $table->softDeletes();

            $table->foreign('tenant_id')
                ->references('id')
                ->on('tenants')
                ->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('clients');
    }
};
