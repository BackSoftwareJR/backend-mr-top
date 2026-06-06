<?php

declare(strict_types=1);

use App\Enums\EditorialAuthorType;
use App\Enums\EditorialContentStatus;
use App\Enums\EditorialContentType;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('editorial_contents', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->string('slug', 160)->unique();
            $table->enum('content_type', EditorialContentType::values());
            $table->enum('status', EditorialContentStatus::values())->default(EditorialContentStatus::Draft->value);
            $table->string('title', 200);
            $table->string('subtitle', 300)->nullable();
            $table->text('excerpt')->nullable();
            $table->json('body_blocks');
            $table->json('type_payload')->nullable();
            $table->json('seo_pack')->nullable();
            $table->string('rubric_slug', 80)->nullable();
            $table->unsignedBigInteger('rubric_id')->nullable();
            $table->json('tags')->nullable();
            $table->foreignId('sector_id')->constrained()->restrictOnDelete();
            $table->enum('author_type', EditorialAuthorType::values())->default(EditorialAuthorType::Admin->value);
            $table->string('author_name')->nullable();
            $table->string('author_role_title')->nullable();
            $table->foreignId('company_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('hero_media_id')->nullable()->constrained('editorial_media')->nullOnDelete();
            $table->unsignedInteger('word_count')->default(0);
            $table->unsignedTinyInteger('read_minutes')->default(0);
            $table->boolean('featured')->default(false);
            $table->boolean('noindex')->default(false);
            $table->timestamp('published_at')->nullable();
            $table->timestamp('scheduled_at')->nullable();
            $table->timestamp('unpublished_at')->nullable();
            $table->foreignId('published_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('reviewed_at')->nullable();
            $table->foreignId('reviewed_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->char('locale', 5)->default('it-IT');
            $table->string('canonical_path', 255)->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['status', 'published_at']);
            $table->index(['content_type', 'status', 'featured']);
            $table->index(['rubric_slug', 'status', 'published_at']);
            $table->index(['company_id', 'status']);
            $table->index('rubric_id');
            $table->index('deleted_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('editorial_contents');
    }
};
