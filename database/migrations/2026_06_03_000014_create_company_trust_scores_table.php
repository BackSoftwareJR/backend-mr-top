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
        Schema::create('company_trust_scores', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('trust_test_id')->constrained()->cascadeOnDelete();
            $table->decimal('score', 5, 2)->default(0);
            $table->json('breakdown')->nullable();
            $table->timestamp('scored_at');
            $table->timestamps();

            $table->index('company_id');
            $table->index('trust_test_id');
            $table->index('score');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('company_trust_scores');
    }
};
