<?php

declare(strict_types=1);

use App\Enums\EditorialModerationStatus;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('editorial_moderation_queue', function (Blueprint $table) {
            $table->id();
            $table->foreignId('content_id')->constrained('editorial_contents')->cascadeOnDelete();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('assigned_reviewer_id')->nullable()->constrained('users')->nullOnDelete();
            $table->enum('status', EditorialModerationStatus::values())->default(EditorialModerationStatus::Pending->value);
            $table->text('notes')->nullable();
            $table->foreignId('submitted_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('submitted_at')->nullable();
            $table->timestamp('resolved_at')->nullable();
            $table->timestamps();

            $table->index(['status', 'submitted_at']);
            $table->index(['company_id', 'status']);
            $table->index('assigned_reviewer_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('editorial_moderation_queue');
    }
};
