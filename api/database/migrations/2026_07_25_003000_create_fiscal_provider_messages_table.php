<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('fiscal_provider_messages', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique()->index();

            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('fiscal_document_id')->constrained('fiscal_documents')->cascadeOnDelete();

            $table->string('provider');
            $table->string('provider_document_id')->nullable();
            $table->string('message_type');
            $table->string('level')->default('info');
            $table->string('provider_status')->nullable();
            $table->string('summary');
            $table->json('payload')->nullable();
            $table->timestamp('received_at')->nullable();

            $table->timestamps();

            $table->index(['tenant_id', 'created_at'], 'idx_fiscal_provider_messages_tenant_created');
            $table->index(['fiscal_document_id', 'created_at'], 'idx_fiscal_provider_messages_document_created');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('fiscal_provider_messages');
    }
};
