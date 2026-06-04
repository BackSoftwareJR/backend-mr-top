<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('payment_intents', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->string('public_ref', 64)->unique();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->unsignedInteger('amount_cents');
            $table->unsignedInteger('credits');
            $table->string('status', 16)->default('pending');
            $table->string('payment_method', 16)->nullable();
            $table->string('provider', 32)->nullable();
            $table->string('provider_ref', 128)->nullable();
            $table->uuid('idempotency_key')->nullable();
            $table->string('client_secret', 128)->nullable();
            $table->foreignId('transaction_id')->nullable()->constrained()->nullOnDelete();
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();

            $table->index(['company_id', 'status']);
            $table->index('idempotency_key');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('payment_intents');
    }
};
