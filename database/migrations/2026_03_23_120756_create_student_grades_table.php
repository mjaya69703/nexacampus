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
        Schema::create('student_grades', function (Blueprint $table) {
            $table->id();

            $table->foreignId('study_plan_detail_id')
                ->constrained('study_plan_details')
                ->cascadeOnUpdate()
                ->cascadeOnDelete();

            $table->decimal('final_score', 5, 2)->nullable();
            $table->string('letter_grade', 5)->nullable();   // A, AB, B, BC, C, D, E
            $table->decimal('grade_point', 3, 2)->nullable(); // 4.00, 3.50, dst

            $table->enum('result_status', [
                'Passed',
                'Failed',
                'Incomplete',
                'Withdrawn',
                'Cancelled',
            ])->nullable();

            $table->dateTime('graded_at')->nullable();
            $table->unsignedBigInteger('graded_by')->nullable();

            $table->text('notes')->nullable();

            $table->timestamps();
            $table->softDeletes();
            $table->unsignedBigInteger('created_by')->nullable();
            $table->unsignedBigInteger('updated_by')->nullable();
            $table->unsignedBigInteger('deleted_by')->nullable();

            $table->unique('study_plan_detail_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('student_grades');
    }
};
