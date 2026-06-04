<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        if (Schema::getConnection()->getDriverName() !== 'mysql') {
            return;
        }

        DB::statement("ALTER TABLE consent_logs MODIFY COLUMN consent_type ENUM(
            'privacy_policy',
            'terms_b2c',
            'terms_b2b',
            'marketing',
            'analytics_cookies',
            'lead_sharing'
        ) NOT NULL");
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (Schema::getConnection()->getDriverName() !== 'mysql') {
            return;
        }

        DB::statement("ALTER TABLE consent_logs MODIFY COLUMN consent_type ENUM(
            'privacy_policy',
            'terms_b2c',
            'marketing',
            'analytics_cookies',
            'lead_sharing'
        ) NOT NULL");
    }
};
