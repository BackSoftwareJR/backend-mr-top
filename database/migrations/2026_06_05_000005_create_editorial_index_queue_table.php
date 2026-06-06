<?php

declare(strict_types=1);

use App\Enums\EditorialIndexQueueAction;
use App\Enums\EditorialIndexQueueStatus;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('editorial_index_queue', function (Blueprint $table) {
            $table->id();
            $table->foreignId('editorial_content_id')->constrained('editorial_contents')->cascadeOnDelete();
            $table->enum('action', EditorialIndexQueueAction::values());
            $table->enum('status', EditorialIndexQueueStatus::values())->default(EditorialIndexQueueStatus::Pending->value);
            $table->timestamp('scheduled_at')->nullable();
            $table->timestamp('processed_at')->nullable();
            $table->text('error_message')->nullable();
            $table->timestamps();

            $table->index(['status', 'scheduled_at']);
            $table->index(['editorial_content_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('editorial_index_queue');
    }
};
