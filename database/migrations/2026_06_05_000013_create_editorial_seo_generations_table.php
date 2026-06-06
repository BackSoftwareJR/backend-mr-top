<?php

declare(strict_types=1);

use App\Enums\EditorialSeoGenerationStatus;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('editorial_seo_generations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('content_id')->constrained('editorial_contents')->cascadeOnDelete();
            $table->json('groq_payload')->nullable();
            $table->json('seo_pack')->nullable();
            $table->unsignedTinyInteger('score')->nullable();
            $table->enum('status', EditorialSeoGenerationStatus::values())->default(EditorialSeoGenerationStatus::Pending->value);
            $table->string('groq_model')->nullable();
            $table->string('prompt_version', 40)->nullable();
            $table->unsignedInteger('latency_ms')->nullable();
            $table->text('error_message')->nullable();
            $table->foreignId('reviewed_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('reviewed_at')->nullable();
            $table->timestamps();

            $table->index(['content_id', 'created_at']);
            $table->index(['status', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('editorial_seo_generations');
    }
};
