<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('lead_matches', function (Blueprint $table) {
            $table->id();
            $table->foreignId('lead_id')->constrained()->cascadeOnDelete();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->unsignedTinyInteger('match_score')->default(0);
            $table->unsignedSmallInteger('rank')->nullable();
            $table->boolean('is_visible_to_consumer')->default(false);
            $table->boolean('is_in_marketplace')->default(false);
            $table->timestamp('unlocked_at')->nullable();
            $table->unsignedInteger('unlock_cost_credits')->default(15);
            $table->enum('crm_status', [
                'nuovo',
                'contattato',
                'visita_fissata',
                'perso',
                'chiuso',
            ])->nullable();
            $table->foreignId('assigned_by')->nullable()->constrained('users')->nullOnDelete();
            $table->json('metadata')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['lead_id', 'company_id']);
            $table->index('company_id');
            $table->index('match_score');
            $table->index('unlocked_at');
            $table->index(['company_id', 'is_in_marketplace', 'match_score'], 'lead_matches_marketplace_index');
            $table->index('crm_status');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('lead_matches');
    }
};
