<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('editorial_search_documents', function (Blueprint $table) {
            $table->id();
            $table->foreignId('content_id')->unique()->constrained('editorial_contents')->cascadeOnDelete();
            $table->string('title', 200);
            $table->text('excerpt')->nullable();
            $table->longText('body_text')->nullable();
            $table->string('rubric', 120)->nullable();
            $table->json('tags')->nullable();
            $table->timestamp('published_at')->nullable();
            $table->timestamp('indexed_at')->nullable();
            $table->timestamps();

            $table->index('published_at');
            $table->index('indexed_at');
            $table->index('rubric');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('editorial_search_documents');
    }
};
