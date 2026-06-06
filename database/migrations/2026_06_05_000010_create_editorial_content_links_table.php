<?php

declare(strict_types=1);

use App\Enums\EditorialContentLinkType;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('editorial_content_links', function (Blueprint $table) {
            $table->id();
            $table->foreignId('source_content_id')->constrained('editorial_contents')->cascadeOnDelete();
            $table->foreignId('target_content_id')->constrained('editorial_contents')->cascadeOnDelete();
            $table->enum('link_type', EditorialContentLinkType::values())->default(EditorialContentLinkType::Related->value);
            $table->string('anchor_text', 200)->nullable();
            $table->decimal('relevance_score', 6, 4)->nullable();
            $table->timestamps();

            $table->unique(
                ['source_content_id', 'target_content_id', 'link_type'],
                'editorial_content_links_unique',
            );
            $table->index('target_content_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('editorial_content_links');
    }
};
