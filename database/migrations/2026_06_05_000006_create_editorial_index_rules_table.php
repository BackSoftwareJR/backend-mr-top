<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('editorial_index_rules', function (Blueprint $table) {
            $table->id();
            $table->string('rubric_slug', 80)->nullable()->unique();
            $table->boolean('include_in_sitemap')->default(true);
            $table->boolean('include_in_internal_search')->default(true);
            $table->boolean('noindex_default')->default(false);
            $table->boolean('exclude_from_crawl')->default(false);
            $table->boolean('is_active')->default(true);
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index('is_active');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('editorial_index_rules');
    }
};
