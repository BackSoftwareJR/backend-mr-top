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
        $uuidExisted = Schema::hasColumn('users', 'uuid');

        Schema::table('users', function (Blueprint $table) use ($uuidExisted): void {
            if (! $uuidExisted) {
                $table->uuid('uuid')->nullable()->after('id');
            }

            if (! Schema::hasColumn('users', 'phone')) {
                $table->string('phone', 32)->nullable()->after('name');
            }

            if (! Schema::hasColumn('users', 'user_type')) {
                $table->enum('user_type', ['consumer', 'b2b', 'superadmin'])
                    ->default('consumer')
                    ->after('phone');
            }

            if (! Schema::hasColumn('users', 'last_login_at')) {
                $table->timestamp('last_login_at')->nullable()->after('remember_token');
            }

            if (! Schema::hasColumn('users', 'deleted_at')) {
                $table->softDeletes();
            }
        });

        Schema::table('users', function (Blueprint $table) use ($uuidExisted): void {
            if (Schema::hasColumn('users', 'uuid') && ! $uuidExisted) {
                $table->uuid('uuid')->nullable(false)->change();
                $table->unique('uuid');
            }
        });

        // Password is nullable in master SQL (OTP-first auth).
        Schema::table('users', function (Blueprint $table): void {
            $table->string('password')->nullable()->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            if (Schema::hasColumn('users', 'uuid')) {
                $table->dropUnique(['uuid']);
                $table->dropColumn('uuid');
            }

            if (Schema::hasColumn('users', 'phone')) {
                $table->dropColumn('phone');
            }

            if (Schema::hasColumn('users', 'user_type')) {
                $table->dropIndex(['user_type']);
                $table->dropColumn('user_type');
            }

            if (Schema::hasColumn('users', 'last_login_at')) {
                $table->dropColumn('last_login_at');
            }

            if (Schema::hasColumn('users', 'deleted_at')) {
                $table->dropIndex(['deleted_at']);
                $table->dropSoftDeletes();
            }
        });
    }
};
