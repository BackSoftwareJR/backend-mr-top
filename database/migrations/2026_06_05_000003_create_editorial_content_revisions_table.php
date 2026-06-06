<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('editorial_content_revisions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('editorial_content_id')->constrained('editorial_contents')->cascadeOnDelete();
            $table->unsignedInteger('revision_number');
            $table->json('snapshot');
            $table->json('body_blocks');
            $table->json('seo_pack')->nullable();
            $table->foreignId('created_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('change_summary', 500)->nullable();
            $table->timestamp('created_at')->useCurrent();

            $table->unique(['editorial_content_id', 'revision_number']);
            $table->index('editorial_content_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('editorial_content_revisions');
    }
};
