<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('student_advisor_notes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('academic_advisor_assignment_id')->constrained('academic_advisor_assignments')->cascadeOnUpdate()->cascadeOnDelete();
            $table->foreignId('student_profile_id')->constrained('student_profiles')->cascadeOnUpdate()->cascadeOnDelete();
            $table->foreignId('lecturer_profile_id')->constrained('lecturer_profiles')->cascadeOnUpdate()->cascadeOnDelete();
            $table->string('topic');
            $table->text('notes');
            $table->text('recommendation')->nullable();
            $table->date('follow_up_at')->nullable();
            $table->string('status')->default('Open');
            $table->boolean('visible_to_student')->default(true);
            $table->timestamps();
            $table->softDeletes();
            $table->unsignedBigInteger('created_by')->nullable();
            $table->unsignedBigInteger('updated_by')->nullable();
            $table->unsignedBigInteger('deleted_by')->nullable();

            $table->index(['student_profile_id', 'status'], 'student_advisor_notes_student_status_idx');
            $table->index(['lecturer_profile_id', 'follow_up_at'], 'student_advisor_notes_lecturer_follow_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('student_advisor_notes');
    }
};
