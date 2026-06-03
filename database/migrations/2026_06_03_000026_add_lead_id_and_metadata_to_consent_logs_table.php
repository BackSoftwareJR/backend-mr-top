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
        Schema::table('consent_logs', function (Blueprint $table) {
            if (! Schema::hasColumn('consent_logs', 'lead_id')) {
                $table->foreignId('lead_id')
                    ->nullable()
                    ->after('user_id')
                    ->constrained('leads')
                    ->nullOnDelete();
            }

            if (! Schema::hasColumn('consent_logs', 'metadata')) {
                $table->json('metadata')->nullable()->after('consent_text_hash');
            }
        });

        Schema::table('consent_logs', function (Blueprint $table) {
            if (Schema::hasColumn('consent_logs', 'lead_id')) {
                $table->index(['lead_id', 'consent_type', 'created_at']);
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('consent_logs', function (Blueprint $table) {
            if (Schema::hasColumn('consent_logs', 'lead_id')) {
                $table->dropForeign(['lead_id']);
                $table->dropIndex(['lead_id', 'consent_type', 'created_at']);
                $table->dropColumn('lead_id');
            }

            if (Schema::hasColumn('consent_logs', 'metadata')) {
                $table->dropColumn('metadata');
            }
        });
    }
};
