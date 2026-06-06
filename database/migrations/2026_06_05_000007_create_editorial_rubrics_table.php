<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('editorial_rubrics', function (Blueprint $table) {
            $table->id();
            $table->foreignId('parent_id')->nullable()->constrained('editorial_rubrics')->nullOnDelete();
            $table->string('slug', 80)->unique();
            $table->string('name', 120);
            $table->text('description')->nullable();
            $table->unsignedInteger('sort_order')->default(0);
            $table->boolean('is_active')->default(true);
            $table->json('default_index_rules')->nullable();
            $table->timestamps();

            $table->index('parent_id');
            $table->index(['is_active', 'sort_order']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('editorial_rubrics');
    }
};
