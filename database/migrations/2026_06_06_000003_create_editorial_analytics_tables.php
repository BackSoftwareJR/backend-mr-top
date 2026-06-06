<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('editorial_content_daily_stats', function (Blueprint $table) {
            $table->id();
            $table->foreignId('content_id')->constrained('editorial_contents')->cascadeOnDelete();
            $table->date('date');
            $table->unsignedInteger('page_views')->default(0);
            $table->unsignedInteger('unique_visitors')->default(0);
            $table->unsignedInteger('bot_views')->default(0);
            $table->timestamps();

            $table->unique(['content_id', 'date']);
            $table->index(['date', 'content_id']);
        });

        // Lightweight dedupe rows for unique-visitor counting. No raw IP stored — only
        // a daily SHA-256 hash of (ip + user_agent + app key). Retain 90 days then purge
        // via scheduled `editorial:purge-view-events` (see routes/console.php).
        Schema::create('editorial_view_events', function (Blueprint $table) {
            $table->id();
            $table->foreignId('content_id')->constrained('editorial_contents')->cascadeOnDelete();
            $table->date('date');
            $table->char('visitor_hash', 64);
            $table->timestamp('created_at')->useCurrent();

            $table->unique(['content_id', 'date', 'visitor_hash']);
            $table->index('date');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('editorial_view_events');
        Schema::dropIfExists('editorial_content_daily_stats');
    }
};
