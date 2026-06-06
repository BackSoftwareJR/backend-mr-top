<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('editorial_contents', function (Blueprint $table) {
            $table->string('external_ref', 120)->nullable()->unique()->after('uuid');
        });
    }

    public function down(): void
    {
        Schema::table('editorial_contents', function (Blueprint $table) {
            $table->dropUnique(['external_ref']);
            $table->dropColumn('external_ref');
        });
    }
};
