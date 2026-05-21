<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('student_leave_applications', function (Blueprint $table) {
            $table->id();
            $table->string('application_number')->unique();
            $table->foreignId('student_profile_id')->constrained('student_profiles')->cascadeOnDelete();
            $table->foreignId('academic_year_id')->nullable()->constrained('academic_years')->nullOnDelete();
            $table->unsignedTinyInteger('semester')->nullable();
            $table->unsignedTinyInteger('duration_semesters')->default(1);
            $table->string('reason_category')->default('personal');
            $table->text('reason');
            $table->string('attachment_path')->nullable();
            $table->string('attachment_name')->nullable();
            $table->string('status')->default('submitted'); // submitted, under_review, revision_requested, approved, rejected, activated, returned, cancelled
            $table->text('student_notes')->nullable();
            $table->text('admin_notes')->nullable();
            $table->foreignId('reviewed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('reviewed_at')->nullable();
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('approved_at')->nullable();
            $table->foreignId('activated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('activated_at')->nullable();
            $table->foreignId('returned_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('returned_at')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['student_profile_id', 'status']);
            $table->index(['academic_year_id', 'semester'], 'student_leave_academic_semester_idx');
            $table->index(['status', 'created_at']);
        });

        Schema::create('student_leave_status_histories', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('student_leave_application_id');
            $table->string('from_status')->nullable();
            $table->string('to_status');
            $table->text('notes')->nullable();
            $table->foreignId('changed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['student_leave_application_id', 'created_at'], 'student_leave_history_app_created_idx');
            $table->foreign('student_leave_application_id', 'student_leave_history_app_fk')
                ->references('id')
                ->on('student_leave_applications')
                ->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('student_leave_status_histories');
        Schema::dropIfExists('student_leave_applications');
    }
};
