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
        Schema::create('leads', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->string('public_ref', 32)->nullable()->unique();
            $table->foreignId('sector_id')->constrained()->restrictOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->enum('status', [
                'draft',
                'processing',
                'routed',
                'assigned',
                'closed',
                'cancelled',
            ])->default('draft');
            $table->string('admin_status', 64)->nullable();
            $table->json('payload');
            $table->string('contact_name')->nullable();
            $table->string('contact_phone', 32)->nullable();
            $table->string('contact_email')->nullable();
            $table->string('location_label')->nullable();
            $table->unsignedInteger('budget_min')->nullable();
            $table->unsignedInteger('budget_max')->nullable();
            $table->text('need_summary')->nullable();
            $table->text('admin_notes')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index('sector_id');
            $table->index('user_id');
            $table->index('status');
            $table->index('created_at');
            $table->index(['sector_id', 'status']);
            $table->index('deleted_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('leads');
    }
};
