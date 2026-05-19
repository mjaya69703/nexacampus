<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('admission_exam_schedules')) {
            Schema::create('admission_exam_schedules', function (Blueprint $table) {
                $table->id();
                $table->foreignId('admission_period_id')->constrained('admission_periods')->cascadeOnDelete();
                $table->string('exam_type');
                $table->string('title');
                $table->date('exam_date');
                $table->time('exam_time');
                $table->string('venue')->nullable();
                $table->string('meeting_link')->nullable();
                $table->integer('quota')->default(0);
                $table->integer('registered_count')->default(0);
                $table->text('notes')->nullable();
                $table->boolean('is_active')->default(true);
                $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
                $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
                $table->foreignId('deleted_by')->nullable()->constrained('users')->nullOnDelete();
                $table->timestamps();
                $table->softDeletes();

                $table->index(['admission_period_id', 'exam_type'], 'adm_exam_period_type_idx');
                $table->index(['exam_date', 'exam_time'], 'adm_exam_datetime_idx');
            });
        }

        if (! Schema::hasTable('admission_exam_participants')) {
            Schema::create('admission_exam_participants', function (Blueprint $table) {
                $table->id();
                $table->foreignId('admission_exam_schedule_id')->constrained('admission_exam_schedules')->cascadeOnDelete();
                $table->foreignId('admission_application_id')->constrained('admission_applications')->cascadeOnDelete();
                $table->enum('attendance_status', ['registered', 'present', 'absent'])->default('registered');
                $table->text('notes')->nullable();
                $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
                $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
                $table->timestamps();

                $table->unique(['admission_exam_schedule_id', 'admission_application_id'], 'adm_exam_participant_unique');
                $table->index(['admission_application_id', 'attendance_status'], 'adm_exam_participant_app_att_idx');
            });
        }

        if (! Schema::hasTable('admission_scores')) {
            Schema::create('admission_scores', function (Blueprint $table) {
                $table->id();
                $table->foreignId('admission_application_id')->constrained('admission_applications')->cascadeOnDelete();
                $table->foreignId('admission_exam_schedule_id')->nullable()->constrained('admission_exam_schedules')->nullOnDelete();
                $table->string('score_type');
                $table->decimal('score', 6, 2);
                $table->decimal('weight', 6, 2)->default(100);
                $table->text('notes')->nullable();
                $table->foreignId('scored_by')->constrained('users')->cascadeOnDelete();
                $table->timestamps();

                $table->index(['admission_application_id', 'score_type'], 'adm_score_app_type_idx');
                $table->index(['admission_exam_schedule_id', 'score_type'], 'adm_score_exam_type_idx');
            });
        }

        if (! Schema::hasTable('admission_quotas')) {
            Schema::create('admission_quotas', function (Blueprint $table) {
                $table->id();
                $table->foreignId('admission_period_id')->constrained('admission_periods')->cascadeOnDelete();
                $table->foreignId('faculty_id')->nullable()->constrained()->nullOnDelete();
                $table->foreignId('study_program_id')->nullable()->constrained()->nullOnDelete();
                $table->string('class_type')->nullable();
                $table->integer('quota');
                $table->integer('accepted_count')->default(0);
                $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
                $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
                $table->timestamps();

                $table->index(['admission_period_id', 'study_program_id', 'class_type'], 'adm_quota_scope_idx');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('admission_quotas');
        Schema::dropIfExists('admission_scores');
        Schema::dropIfExists('admission_exam_participants');
        Schema::dropIfExists('admission_exam_schedules');
    }
};
