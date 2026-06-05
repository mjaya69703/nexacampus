<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('academic_advisor_assignments', function (Blueprint $table) {
            if (! $this->indexExists('academic_advisor_assignments', 'advisor_assignments_active_lookup_idx')) {
                $table->index(
                    ['lecturer_profile_id', 'is_active', 'start_date', 'end_date'],
                    'advisor_assignments_active_lookup_idx'
                );
            }

            if (! $this->indexExists('academic_advisor_assignments', 'advisor_assignments_student_period_idx')) {
                $table->index(
                    ['student_profile_id', 'academic_year_id', 'is_active', 'start_date', 'end_date'],
                    'advisor_assignments_student_period_idx'
                );
            }
        });
    }

    public function down(): void
    {
        Schema::table('academic_advisor_assignments', function (Blueprint $table) {
            if ($this->indexExists('academic_advisor_assignments', 'advisor_assignments_student_period_idx')) {
                $table->dropIndex('advisor_assignments_student_period_idx');
            }

            if ($this->indexExists('academic_advisor_assignments', 'advisor_assignments_active_lookup_idx')) {
                $table->dropIndex('advisor_assignments_active_lookup_idx');
            }
        });
    }

    private function indexExists(string $table, string $index): bool
    {
        return collect(Schema::getIndexes($table))
            ->contains(fn (array $item) => ($item['name'] ?? null) === $index);
    }
};
