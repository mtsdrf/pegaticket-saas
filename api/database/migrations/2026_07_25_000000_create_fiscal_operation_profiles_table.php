<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('fiscal_operation_profiles', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique()->index();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->string('operation_nature', 20)->index();
            $table->string('document_type', 10)->index();
            $table->string('default_cfop', 10)->nullable();
            $table->json('scope')->nullable();
            $table->text('description')->nullable();
            $table->boolean('is_active')->default(true);
            $table->unsignedBigInteger('created_by')->nullable()->index();
            $table->unsignedBigInteger('updated_by')->nullable()->index();
            $table->unsignedBigInteger('deleted_by')->nullable()->index();
            $table->timestamps();
            $table->softDeletes();
            $table->index(['tenant_id', 'operation_nature', 'document_type', 'is_active'], 'idx_fiscal_op_profile_tenant_nature_doc_active');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('fiscal_operation_profiles');
    }
};
