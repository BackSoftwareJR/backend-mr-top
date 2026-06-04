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
        Schema::create('company_profiles', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->unique()->constrained()->cascadeOnDelete();
            $table->string('display_name');
            $table->string('service_type', 128);
            $table->string('tagline', 512)->nullable();
            $table->text('description')->nullable();
            $table->json('pros')->nullable();
            $table->string('image_url', 2048)->nullable();
            $table->string('location_label', 255)->nullable();
            $table->text('contact_hint')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('company_profiles');
    }
};
