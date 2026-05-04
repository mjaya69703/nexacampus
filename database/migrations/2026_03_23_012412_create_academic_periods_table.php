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
        Schema::create('academic_periods', function (Blueprint $table) {
            $table->id();

            $table->foreignId('academic_year_id')
                ->constrained('academic_years')
                ->cascadeOnUpdate()
                ->cascadeOnDelete();

            $table->string('name');
            $table->string('code', 50)->nullable();

            $table->enum('type', [
                'Student Registration',
                'Study Plan',
                'Study Plan Revision',
                'Grading',
                'Exam',
                'Remedial',
                'Academic Leave',
                'Yudisium',
                'Custom',
            ])->default('Custom');

            $table->dateTime('start_at');
            $table->dateTime('end_at');

            $table->boolean('is_active')->default(true);
            $table->text('desc')->nullable();

            // Audit
            $table->timestamps();
            $table->softDeletes();
            $table->unsignedBigInteger('created_by')->nullable();
            $table->unsignedBigInteger('updated_by')->nullable();
            $table->unsignedBigInteger('deleted_by')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('academic_periods');
    }
};
