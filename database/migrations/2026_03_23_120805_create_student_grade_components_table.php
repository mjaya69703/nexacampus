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
        Schema::create('student_grade_components', function (Blueprint $table) {
            $table->id();

            $table->foreignId('student_grade_id')
                ->constrained('student_grades')
                ->cascadeOnUpdate()
                ->cascadeOnDelete();

            $table->string('name'); // Assignment, Quiz, Mid Exam, Final Exam, Practicum
            $table->decimal('weight_percentage', 5, 2)->nullable(); // 20.00, 30.00, dst
            $table->decimal('score', 5, 2)->nullable();

            $table->text('notes')->nullable();
            $table->unsignedInteger('sort_order')->default(0);

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
        Schema::dropIfExists('student_grade_components');
    }
};
