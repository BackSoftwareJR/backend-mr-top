<?php

declare(strict_types=1);

use App\Enums\EditorialContentStatus;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('editorial_workflow_events', function (Blueprint $table) {
            $table->id();
            $table->foreignId('content_id')->constrained('editorial_contents')->cascadeOnDelete();
            $table->foreignId('actor_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->enum('from_status', EditorialContentStatus::values())->nullable();
            $table->enum('to_status', EditorialContentStatus::values());
            $table->text('note')->nullable();
            $table->timestamp('created_at')->useCurrent();

            $table->index(['content_id', 'created_at']);
            $table->index('actor_user_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('editorial_workflow_events');
    }
};
