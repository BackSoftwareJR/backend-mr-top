<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('lead_matches', function (Blueprint $table) {
            $table->string('public_ref', 32)->nullable()->unique()->after('id');
        });

        DB::table('lead_matches')
            ->whereNull('public_ref')
            ->orderBy('id')
            ->chunkById(100, function ($rows): void {
                foreach ($rows as $row) {
                    DB::table('lead_matches')
                        ->where('id', $row->id)
                        ->update(['public_ref' => sprintf('ML-%d', $row->id)]);
                }
            });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('lead_matches', function (Blueprint $table) {
            $table->dropUnique(['public_ref']);
            $table->dropColumn('public_ref');
        });
    }
};
