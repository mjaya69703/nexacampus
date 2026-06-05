<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('student_complaint_categories', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('code')->unique();
            $table->foreignId('default_work_unit_id')->nullable()->constrained('work_units')->nullOnDelete();
            $table->text('description')->nullable();
            $table->unsignedSmallInteger('default_sla_hours')->default(48);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->softDeletes();

            $table->index(['is_active', 'name']);
        });

        Schema::create('student_complaints', function (Blueprint $table) {
            $table->id();
            $table->string('ticket_number')->unique();
            $table->foreignId('student_profile_id')->constrained('student_profiles')->cascadeOnDelete();
            $table->foreignId('student_complaint_category_id')->nullable()->constrained('student_complaint_categories')->nullOnDelete();
            $table->foreignId('assigned_work_unit_id')->nullable()->constrained('work_units')->nullOnDelete();
            $table->foreignId('assigned_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('subject');
            $table->text('description');
            $table->string('priority')->default('normal'); // low, normal, high, urgent
            $table->string('status')->default('submitted'); // submitted, in_review, waiting_student, responded, resolved, closed, rejected, reopened
            $table->timestamp('due_at')->nullable();
            $table->timestamp('last_message_at')->nullable();
            $table->timestamp('resolved_at')->nullable();
            $table->timestamp('closed_at')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['status', 'due_at']);
            $table->index(['student_profile_id', 'status']);
            $table->index(['assigned_work_unit_id', 'status']);
            $table->index(['assigned_user_id', 'status']);
        });

        Schema::create('student_complaint_messages', function (Blueprint $table) {
            $table->id();
            $table->foreignId('student_complaint_id')->constrained('student_complaints')->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('sender_type'); // student, admin
            $table->text('message');
            $table->boolean('is_internal_note')->default(false);
            $table->timestamps();

            $table->index(['student_complaint_id', 'created_at'], 'scm_complaint_created_idx');
        });

        Schema::create('student_complaint_attachments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('student_complaint_id')->constrained('student_complaints')->cascadeOnDelete();
            $table->unsignedBigInteger('student_complaint_message_id')->nullable();
            $table->string('file_path');
            $table->string('file_name');
            $table->string('mime_type')->nullable();
            $table->unsignedBigInteger('file_size')->default(0);
            $table->timestamps();

            $table->index('student_complaint_id');
            $table->foreign('student_complaint_message_id', 'sca_message_id_fk')
                ->references('id')
                ->on('student_complaint_messages')
                ->cascadeOnDelete();
        });

        Schema::create('student_complaint_status_histories', function (Blueprint $table) {
            $table->id();
            $table->foreignId('student_complaint_id')->constrained('student_complaints')->cascadeOnDelete();
            $table->string('from_status')->nullable();
            $table->string('to_status');
            $table->text('notes')->nullable();
            $table->foreignId('changed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['student_complaint_id', 'created_at'], 'sch_complaint_created_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('student_complaint_status_histories');
        Schema::dropIfExists('student_complaint_attachments');
        Schema::dropIfExists('student_complaint_messages');
        Schema::dropIfExists('student_complaints');
        Schema::dropIfExists('student_complaint_categories');
    }
};
