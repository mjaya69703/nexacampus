<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('graduation_applications', function (Blueprint $table) {
            $table->id();
            $table->string('application_number')->unique();
            $table->foreignId('student_profile_id')->constrained('student_profiles')->cascadeOnDelete();
            $table->foreignId('academic_period_id')->nullable()->constrained('academic_periods')->nullOnDelete();
            $table->string('graduation_period')->nullable();
            $table->string('thesis_title')->nullable();
            $table->text('reason')->nullable();
            $table->string('attachment_path')->nullable();
            $table->string('attachment_name')->nullable();
            $table->string('status')->default('submitted'); // submitted, under_review, revision_requested, approved, rejected, finalized, cancelled
            $table->json('eligibility_snapshot')->nullable();
            $table->json('admin_checklist')->nullable();
            $table->text('student_notes')->nullable();
            $table->text('admin_notes')->nullable();
            $table->foreignId('reviewed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('reviewed_at')->nullable();
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('approved_at')->nullable();
            $table->foreignId('finalized_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('finalized_at')->nullable();
            $table->date('graduation_date')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['student_profile_id', 'status'], 'graduation_student_status_idx');
            $table->index(['status', 'created_at'], 'graduation_status_created_idx');
        });

        Schema::create('graduation_status_histories', function (Blueprint $table) {
            $table->id();
            $table->foreignId('graduation_application_id')->constrained('graduation_applications')->cascadeOnDelete();
            $table->string('from_status')->nullable();
            $table->string('to_status');
            $table->text('notes')->nullable();
            $table->foreignId('changed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['graduation_application_id', 'created_at'], 'graduation_history_app_created_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('graduation_status_histories');
        Schema::dropIfExists('graduation_applications');
    }
};
