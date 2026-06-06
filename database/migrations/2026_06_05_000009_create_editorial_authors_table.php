<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('editorial_authors', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('company_id')->nullable()->constrained()->nullOnDelete();
            $table->string('display_name');
            $table->string('role_title')->nullable();
            $table->text('bio')->nullable();
            $table->foreignId('avatar_media_id')->nullable()->constrained('editorial_media')->nullOnDelete();
            $table->json('credentials')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->index(['user_id', 'is_active']);
            $table->index(['company_id', 'is_active']);
        });

        Schema::create('editorial_content_author', function (Blueprint $table) {
            $table->id();
            $table->foreignId('editorial_content_id')->constrained()->cascadeOnDelete();
            $table->foreignId('editorial_author_id')->constrained()->cascadeOnDelete();
            $table->unsignedTinyInteger('sort_order')->default(0);
            $table->boolean('is_primary')->default(false);
            $table->timestamps();

            $table->unique(['editorial_content_id', 'editorial_author_id'], 'editorial_content_author_unique');
            $table->index(['editorial_content_id', 'sort_order']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('editorial_content_author');
        Schema::dropIfExists('editorial_authors');
    }
};
