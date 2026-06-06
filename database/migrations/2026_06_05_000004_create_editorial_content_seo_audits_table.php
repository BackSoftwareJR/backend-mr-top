<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('editorial_content_seo_audits', function (Blueprint $table) {
            $table->id();
            $table->foreignId('editorial_content_id')->constrained('editorial_contents')->cascadeOnDelete();
            $table->unsignedInteger('revision_number')->nullable();
            $table->json('seo_pack');
            $table->unsignedTinyInteger('seo_score')->nullable();
            $table->boolean('approved')->default(false);
            $table->foreignId('approved_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('approved_at')->nullable();
            $table->text('auditor_notes')->nullable();
            $table->timestamps();

            $table->index(['editorial_content_id', 'approved']);
            $table->index('created_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('editorial_content_seo_audits');
    }
};
