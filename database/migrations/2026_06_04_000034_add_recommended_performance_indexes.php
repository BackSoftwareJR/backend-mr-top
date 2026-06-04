<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Recommended indexes from docs/10_SQL_REVIEW_&_GAPS.md §3.
     */
    public function up(): void
    {
        Schema::table('lead_matches', function (Blueprint $table) {
            $table->index(
                ['lead_id', 'is_visible_to_consumer', 'match_score'],
                'lead_matches_lead_b2c_index'
            );
            $table->index(
                ['company_id', 'crm_status', 'unlocked_at'],
                'lead_matches_company_crm_index'
            );
        });

        Schema::table('companies', function (Blueprint $table) {
            $table->index('city', 'companies_city_index');
        });

        Schema::table('notifications', function (Blueprint $table) {
            $table->index(
                ['notifiable_type', 'notifiable_id', 'read_at'],
                'notifications_unread_index'
            );
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('lead_matches', function (Blueprint $table) {
            $table->dropIndex('lead_matches_lead_b2c_index');
            $table->dropIndex('lead_matches_company_crm_index');
        });

        Schema::table('companies', function (Blueprint $table) {
            $table->dropIndex('companies_city_index');
        });

        Schema::table('notifications', function (Blueprint $table) {
            $table->dropIndex('notifications_unread_index');
        });
    }
};
