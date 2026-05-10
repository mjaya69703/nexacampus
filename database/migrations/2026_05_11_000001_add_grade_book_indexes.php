<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('student_grades', function (Blueprint $table) {
            if (! $this->indexExists('student_grades', 'student_grades_grade_status_index')) {
                $table->index('grade_status', 'student_grades_grade_status_index');
            }

            if (! $this->indexExists('student_grades', 'student_grades_graded_at_index')) {
                $table->index('graded_at', 'student_grades_graded_at_index');
            }
        });
    }

    public function down(): void
    {
        Schema::table('student_grades', function (Blueprint $table) {
            if ($this->indexExists('student_grades', 'student_grades_graded_at_index')) {
                $table->dropIndex('student_grades_graded_at_index');
            }

            if ($this->indexExists('student_grades', 'student_grades_grade_status_index')) {
                $table->dropIndex('student_grades_grade_status_index');
            }
        });
    }

    private function indexExists(string $table, string $index): bool
    {
        return collect(Schema::getIndexes($table))
            ->contains(fn (array $existing) => $existing['name'] === $index);
    }
};
