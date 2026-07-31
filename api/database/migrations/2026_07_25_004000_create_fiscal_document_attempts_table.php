<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('fiscal_document_attempts', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique()->index();

            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('fiscal_document_id')->constrained('fiscal_documents')->cascadeOnDelete();

            $table->string('operation_type');
            $table->string('status');
            $table->string('provider')->nullable();
            $table->string('provider_reference')->nullable();
            $table->string('idempotency_key')->nullable();
            $table->string('payload_hash', 64)->nullable();
            $table->string('response_hash', 64)->nullable();
            $table->unsignedInteger('attempt_number')->default(1);
            $table->json('payload')->nullable();
            $table->json('response_payload')->nullable();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('completed_at')->nullable();

            $table->timestamps();

            $table->index(['tenant_id', 'created_at'], 'idx_fiscal_attempts_tenant_created');
            $table->index(['fiscal_document_id', 'created_at'], 'idx_fiscal_attempts_document_created');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('fiscal_document_attempts');
    }
};
