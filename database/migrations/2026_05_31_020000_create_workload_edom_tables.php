<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('lecturer_workload_periods', function (Blueprint $table) {
            $table->id();
            $table->foreignId('academic_year_id')->nullable()->constrained('academic_years')->nullOnDelete();
            $table->foreignId('academic_period_id')->nullable()->constrained('academic_periods')->nullOnDelete();
            $table->string('name');
            $table->string('code')->unique();
            $table->date('starts_at')->nullable();
            $table->date('ends_at')->nullable();
            $table->string('status')->default('draft'); // draft, open, review, closed
            $table->decimal('minimum_sks', 8, 2)->default(12);
            $table->decimal('maximum_sks', 8, 2)->default(16);
            $table->text('notes')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('deleted_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['status', 'starts_at', 'ends_at']);
        });

        Schema::create('lecturer_workload_rules', function (Blueprint $table) {
            $table->id();
            $table->string('category'); // teaching, structural, tridharma
            $table->string('source_code');
            $table->string('name');
            $table->decimal('sks_value', 8, 2)->default(0);
            $table->decimal('maximum_sks', 8, 2)->nullable();
            $table->boolean('is_active')->default(true);
            $table->text('description')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('deleted_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['category', 'source_code']);
            $table->index(['category', 'is_active']);
        });

        Schema::create('lecturer_workload_submissions', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('lecturer_workload_period_id');
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('lecturer_profile_id')->nullable()->constrained('lecturer_profiles')->nullOnDelete();
            $table->foreignId('employee_profile_id')->nullable()->constrained('employee_profiles')->nullOnDelete();
            $table->foreignId('approval_request_id')->nullable()->constrained('approval_requests')->nullOnDelete();
            $table->string('status')->default('draft'); // draft, submitted, in_approval, approved, revision, rejected, cancelled
            $table->decimal('teaching_sks', 8, 2)->default(0);
            $table->decimal('structural_sks', 8, 2)->default(0);
            $table->decimal('tridharma_sks', 8, 2)->default(0);
            $table->decimal('total_sks', 8, 2)->default(0);
            $table->timestamp('generated_at')->nullable();
            $table->timestamp('submitted_at')->nullable();
            $table->timestamp('approved_at')->nullable();
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('rejected_at')->nullable();
            $table->foreignId('rejected_by')->nullable()->constrained('users')->nullOnDelete();
            $table->text('lecturer_notes')->nullable();
            $table->text('review_notes')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique(['lecturer_workload_period_id', 'user_id'], 'workload_submission_period_user_unique');
            $table->index(['status', 'total_sks']);
            $table->index(['lecturer_profile_id', 'status']);
            $table->foreign('lecturer_workload_period_id', 'lw_submission_period_fk')
                ->references('id')
                ->on('lecturer_workload_periods')
                ->cascadeOnDelete();
        });

        Schema::create('lecturer_workload_items', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('lecturer_workload_submission_id');
            $table->nullableMorphs('sourceable');
            $table->string('category');
            $table->string('source_code')->nullable();
            $table->string('title');
            $table->decimal('sks', 8, 2)->default(0);
            $table->json('snapshot')->nullable();
            $table->timestamps();

            $table->index(['category', 'source_code']);
            $table->foreign('lecturer_workload_submission_id', 'lw_item_submission_fk')
                ->references('id')
                ->on('lecturer_workload_submissions')
                ->cascadeOnDelete();
        });

        Schema::create('edom_periods', function (Blueprint $table) {
            $table->id();
            $table->foreignId('academic_year_id')->nullable()->constrained('academic_years')->nullOnDelete();
            $table->foreignId('academic_period_id')->nullable()->constrained('academic_periods')->nullOnDelete();
            $table->string('name');
            $table->string('code')->unique();
            $table->date('starts_at')->nullable();
            $table->date('ends_at')->nullable();
            $table->string('status')->default('draft'); // draft, open, closed
            $table->unsignedSmallInteger('minimum_responses')->default(3);
            $table->text('notes')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('deleted_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['status', 'starts_at', 'ends_at']);
        });

        Schema::create('edom_questions', function (Blueprint $table) {
            $table->id();
            $table->string('category')->default('teaching');
            $table->string('question_text');
            $table->string('answer_type')->default('scale'); // scale, text
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->boolean('is_required')->default(true);
            $table->boolean('is_active')->default(true);
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('deleted_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['category', 'is_active']);
        });

        Schema::create('edom_responses', function (Blueprint $table) {
            $table->id();
            $table->foreignId('edom_period_id')->constrained('edom_periods')->cascadeOnDelete();
            $table->foreignId('course_offering_id')->constrained('course_offerings')->cascadeOnDelete();
            $table->foreignId('lecturer_profile_id')->constrained('lecturer_profiles')->cascadeOnDelete();
            $table->foreignId('student_profile_id')->constrained('student_profiles')->cascadeOnDelete();
            $table->timestamp('submitted_at')->nullable();
            $table->timestamps();

            $table->unique(['edom_period_id', 'course_offering_id', 'lecturer_profile_id', 'student_profile_id'], 'edom_response_once_unique');
            $table->index(['lecturer_profile_id', 'course_offering_id']);
        });

        Schema::create('edom_answers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('edom_response_id')->constrained('edom_responses')->cascadeOnDelete();
            $table->foreignId('edom_question_id')->constrained('edom_questions')->cascadeOnDelete();
            $table->unsignedTinyInteger('score')->nullable();
            $table->text('text_answer')->nullable();
            $table->timestamps();

            $table->unique(['edom_response_id', 'edom_question_id']);
        });

        Schema::create('lecturer_performance_reviews', function (Blueprint $table) {
            $table->id();
            $table->foreignId('edom_period_id')->nullable()->constrained('edom_periods')->nullOnDelete();
            $table->unsignedBigInteger('lecturer_workload_period_id')->nullable();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('lecturer_profile_id')->nullable()->constrained('lecturer_profiles')->nullOnDelete();
            $table->foreignId('employee_profile_id')->nullable()->constrained('employee_profiles')->nullOnDelete();
            $table->decimal('edom_score', 8, 2)->nullable();
            $table->unsignedInteger('edom_response_count')->default(0);
            $table->decimal('teaching_compliance_score', 8, 2)->nullable();
            $table->decimal('attendance_compliance_score', 8, 2)->nullable();
            $table->decimal('workload_total_sks', 8, 2)->nullable();
            $table->decimal('final_score', 8, 2)->nullable();
            $table->string('status')->default('draft'); // draft, calculated, published
            $table->json('snapshot')->nullable();
            $table->timestamp('calculated_at')->nullable();
            $table->timestamp('published_at')->nullable();
            $table->text('review_notes')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique(['edom_period_id', 'lecturer_workload_period_id', 'user_id'], 'performance_review_period_user_unique');
            $table->index(['lecturer_profile_id', 'status']);
            $table->foreign('lecturer_workload_period_id', 'performance_workload_period_fk')
                ->references('id')
                ->on('lecturer_workload_periods')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('lecturer_performance_reviews');
        Schema::dropIfExists('edom_answers');
        Schema::dropIfExists('edom_responses');
        Schema::dropIfExists('edom_questions');
        Schema::dropIfExists('edom_periods');
        Schema::dropIfExists('lecturer_workload_items');
        Schema::dropIfExists('lecturer_workload_submissions');
        Schema::dropIfExists('lecturer_workload_rules');
        Schema::dropIfExists('lecturer_workload_periods');
    }
};
