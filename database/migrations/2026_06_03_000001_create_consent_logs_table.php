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
        Schema::create('consent_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('session_id', 64)->nullable();
            $table->enum('consent_type', [
                'privacy_policy',
                'terms_b2c',
                'terms_b2b',
                'marketing',
                'analytics_cookies',
                'lead_sharing',
            ]);
            $table->string('policy_version', 20);
            $table->string('ip_address', 45)->nullable();
            $table->text('user_agent')->nullable();
            $table->boolean('consent_given');
            $table->char('consent_text_hash', 64);
            $table->timestamp('created_at')->useCurrent();
            $table->softDeletes();

            $table->index(['user_id', 'consent_type', 'created_at']);
            $table->index(['session_id', 'consent_type']);
            $table->index(['consent_type', 'policy_version']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('consent_logs');
    }
};
