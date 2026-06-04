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
        Schema::create('impersonation_sessions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('admin_user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('partner_user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('personal_access_token_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('start_audit_log_id')->nullable()->constrained('audit_logs')->nullOnDelete();
            $table->timestamp('started_at');
            $table->timestamp('expires_at');
            $table->timestamp('ended_at')->nullable();
            $table->timestamps();

            $table->index(['admin_user_id', 'ended_at']);
            $table->index(['expires_at', 'ended_at']);
            $table->index('personal_access_token_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('impersonation_sessions');
    }
};
