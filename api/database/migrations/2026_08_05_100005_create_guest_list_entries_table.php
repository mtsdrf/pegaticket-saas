<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('guest_list_entries', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique()->index();

            $table->unsignedBigInteger('tenant_id')->index();
            $table->foreignId('guest_list_id')->constrained('guest_lists')->cascadeOnDelete();
            $table->foreignId('sale_id')->nullable()->constrained('sales')->nullOnDelete();

            $table->string('name');
            $table->string('email');
            $table->string('document')->nullable();
            // Token de resgate público (link individual, sem login) —
            // mesmo alfabeto/propósito de Ticket::code, mas próprio pra não
            // acoplar os dois domínios.
            $table->string('token', 64)->unique();
            $table->timestamp('redeemed_at')->nullable();

            $table->unsignedBigInteger('created_by')->nullable()->index();
            $table->unsignedBigInteger('updated_by')->nullable()->index();
            $table->unsignedBigInteger('deleted_by')->nullable()->index();

            $table->timestamps();
            $table->softDeletes();

            $table->foreign('tenant_id')->references('id')->on('tenants')->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('guest_list_entries');
    }
};
