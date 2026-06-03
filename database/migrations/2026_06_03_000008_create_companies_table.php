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
        Schema::create('companies', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('sector_id')->constrained()->restrictOnDelete();
            $table->string('organization_name');
            $table->string('legal_name');
            $table->string('vat_number', 16)->nullable();
            $table->string('sdi_code', 7)->nullable();
            $table->string('city', 128)->nullable();
            $table->enum('vetting_status', [
                'draft',
                'in_progress',
                'pending_review',
                'approved',
                'rejected',
                'suspended',
            ])->default('draft');
            $table->enum('tier', ['starter', 'growth', 'enterprise'])->nullable();
            $table->json('dynamic_attributes')->nullable();
            $table->json('schedule')->nullable();
            $table->timestamp('approved_at')->nullable();
            $table->timestamp('rejected_at')->nullable();
            $table->text('rejection_reason')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index('vetting_status');
            $table->index('vat_number');
            $table->index(['sector_id', 'vetting_status']);
            $table->index('deleted_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('companies');
    }
};
