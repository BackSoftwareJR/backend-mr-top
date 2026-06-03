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
        Schema::create('transactions', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->string('public_ref', 32)->nullable()->unique();
            $table->foreignId('company_id')->constrained()->restrictOnDelete();
            $table->foreignId('wallet_id')->constrained()->restrictOnDelete();
            $table->foreignId('lead_match_id')->nullable()->constrained()->nullOnDelete();
            $table->enum('type', [
                'recharge',
                'lead_unlock',
                'subscription',
                'lead_bundle',
                'commission',
                'refund',
            ]);
            $table->integer('amount_cents');
            $table->integer('credits_delta')->default(0);
            $table->enum('status', ['pending', 'completed', 'failed', 'void'])->default('pending');
            $table->enum('payment_method', ['card', 'sepa', 'transfer', 'wallet'])->nullable();
            $table->string('reference', 64)->nullable();
            $table->string('description', 512)->nullable();
            $table->json('metadata')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();

            $table->index('company_id');
            $table->index('wallet_id');
            $table->index('status');
            $table->index('type');
            $table->index('created_at');
            $table->index('lead_match_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('transactions');
    }
};
