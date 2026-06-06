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
            $table->foreign('rubric_id')
                ->references('id')
                ->on('editorial_rubrics')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('editorial_contents', function (Blueprint $table) {
            $table->dropForeign(['rubric_id']);
        });
    }
};
