<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('theses', function (Blueprint $table) {
            $table->string('plagiarism_status', 20)->default('not_checked')->after('repository_published_at');
            $table->decimal('plagiarism_score', 5, 2)->nullable()->after('plagiarism_status');
            $table->string('plagiarism_tool', 100)->nullable()->after('plagiarism_score');
            $table->timestamp('plagiarism_checked_at')->nullable()->after('plagiarism_tool');
            $table->text('plagiarism_notes')->nullable()->after('plagiarism_checked_at');

            $table->index('plagiarism_status');
        });
    }

    public function down(): void
    {
        Schema::table('theses', function (Blueprint $table) {
            $table->dropIndex(['plagiarism_status']);
            $table->dropColumn([
                'plagiarism_status',
                'plagiarism_score',
                'plagiarism_tool',
                'plagiarism_checked_at',
                'plagiarism_notes',
            ]);
        });
    }
};
