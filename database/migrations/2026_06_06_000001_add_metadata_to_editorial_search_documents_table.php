<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('editorial_search_documents', function (Blueprint $table) {
            $table->string('content_type', 32)->nullable()->after('tags');
            $table->foreignId('company_id')->nullable()->after('content_type')->constrained('companies')->nullOnDelete();

            $table->index('content_type');
            $table->index('company_id');
        });
    }

    public function down(): void
    {
        Schema::table('editorial_search_documents', function (Blueprint $table) {
            $table->dropForeign(['company_id']);
            $table->dropIndex(['content_type']);
            $table->dropIndex(['company_id']);
            $table->dropColumn(['content_type', 'company_id']);
        });
    }
};
