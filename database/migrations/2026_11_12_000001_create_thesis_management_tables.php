<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('theses', function (Blueprint $table) {
            $table->id();
            $table->foreignId('student_profile_id')->constrained('student_profiles')->restrictOnDelete();
            $table->foreignId('academic_year_id')->constrained('academic_years')->restrictOnDelete();
            $table->enum('type', ['skripsi', 'tugas_akhir'])->default('skripsi');
            $table->string('title');
            $table->string('topic')->nullable();
            $table->text('abstract')->nullable();
            $table->string('keywords')->nullable();
            $table->enum('status', [
                'draft', 'submitted', 'in_progress', 'defense_ready',
                'defended', 'completed', 'rejected', 'cancelled',
            ])->default('draft');
            $table->string('proposal_file_path')->nullable();
            $table->string('proposal_file_name')->nullable();
            $table->timestamp('proposal_submitted_at')->nullable();
            $table->timestamp('approved_at')->nullable();
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('rejected_at')->nullable();
            $table->text('rejection_reason')->nullable();
            $table->decimal('final_score', 5, 2)->nullable();
            $table->string('final_grade', 3)->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->boolean('repository_published')->default(false);
            $table->timestamp('repository_published_at')->nullable();
            $table->text('notes')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('deleted_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['student_profile_id', 'status']);
            $table->index('status');
            $table->index('academic_year_id');
        });

        Schema::create('thesis_supervisors', function (Blueprint $table) {
            $table->id();
            $table->foreignId('thesis_id')->constrained('theses')->cascadeOnDelete();
            $table->foreignId('lecturer_profile_id')->constrained('lecturer_profiles')->restrictOnDelete();
            $table->enum('role', ['primary', 'secondary'])->default('primary');
            $table->date('assigned_at')->nullable();
            $table->boolean('is_active')->default(true);
            $table->text('notes')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['thesis_id', 'lecturer_profile_id']);
            $table->index('lecturer_profile_id');
        });

        Schema::create('thesis_milestones', function (Blueprint $table) {
            $table->id();
            $table->foreignId('thesis_id')->constrained('theses')->cascadeOnDelete();
            $table->string('title');
            $table->text('description')->nullable();
            $table->unsignedSmallInteger('sequence')->default(1);
            $table->enum('status', ['pending', 'in_progress', 'submitted', 'revision', 'approved'])->default('pending');
            $table->date('due_date')->nullable();
            $table->timestamp('submitted_at')->nullable();
            $table->timestamp('reviewed_at')->nullable();
            $table->foreignId('reviewed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->text('feedback')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['thesis_id', 'sequence']);
            $table->index('status');
        });

        Schema::create('thesis_documents', function (Blueprint $table) {
            $table->id();
            $table->foreignId('thesis_id')->constrained('theses')->cascadeOnDelete();
            $table->foreignId('uploaded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->enum('category', ['proposal', 'chapter', 'revision', 'final', 'presentation', 'other'])->default('other');
            $table->string('title');
            $table->string('file_path');
            $table->string('file_name');
            $table->unsignedBigInteger('file_size')->nullable();
            $table->string('mime_type')->nullable();
            $table->unsignedInteger('version')->default(1);
            $table->text('notes')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['thesis_id', 'category']);
        });

        Schema::create('thesis_defenses', function (Blueprint $table) {
            $table->id();
            $table->foreignId('thesis_id')->constrained('theses')->cascadeOnDelete();
            $table->enum('type', ['proposal', 'result'])->default('result');
            $table->dateTime('scheduled_at');
            $table->unsignedSmallInteger('duration_minutes')->default(90);
            $table->string('room')->nullable();
            $table->string('meeting_link')->nullable();
            $table->enum('status', ['scheduled', 'completed', 'cancelled', 'postponed'])->default('scheduled');
            $table->enum('result', ['passed', 'passed_with_revision', 'failed'])->nullable();
            $table->decimal('score', 5, 2)->nullable();
            $table->string('grade', 3)->nullable();
            $table->text('notes')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['thesis_id', 'type']);
            $table->index('status');
        });

        Schema::create('thesis_defense_examiners', function (Blueprint $table) {
            $table->id();
            $table->foreignId('thesis_defense_id')->constrained('thesis_defenses')->cascadeOnDelete();
            $table->foreignId('lecturer_profile_id')->constrained('lecturer_profiles')->restrictOnDelete();
            $table->enum('role', ['chair', 'secretary', 'examiner', 'supervisor'])->default('examiner');
            $table->decimal('score', 5, 2)->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['thesis_defense_id', 'lecturer_profile_id'], 'thesis_defense_examiner_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('thesis_defense_examiners');
        Schema::dropIfExists('thesis_defenses');
        Schema::dropIfExists('thesis_documents');
        Schema::dropIfExists('thesis_milestones');
        Schema::dropIfExists('thesis_supervisors');
        Schema::dropIfExists('theses');
    }
};
