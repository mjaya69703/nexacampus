<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('student_transfer_requests', function (Blueprint $table) {
            $table->id();
            $table->string('request_number')->unique();
            $table->foreignId('student_profile_id')->constrained('student_profiles')->cascadeOnDelete();
            $table->foreignId('from_study_program_id')->nullable()->constrained('study_programs')->nullOnDelete();
            $table->foreignId('to_study_program_id')->constrained('study_programs')->cascadeOnDelete();
            $table->unsignedTinyInteger('current_semester')->nullable();
            $table->unsignedTinyInteger('recommended_semester')->nullable();
            $table->string('transfer_type')->default('study_program');
            $table->text('reason');
            $table->string('attachment_path')->nullable();
            $table->string('attachment_name')->nullable();
            $table->string('status')->default('submitted'); // submitted, under_review, revision_requested, approved, approved_pending_payment, rejected, applied, cancelled
            $table->text('student_notes')->nullable();
            $table->text('admin_notes')->nullable();
            $table->text('academic_evaluation_notes')->nullable();
            $table->text('credit_mapping_notes')->nullable();
            $table->text('finance_notes')->nullable();
            $table->decimal('transfer_fee_amount', 12, 2)->default(0);
            $table->date('transfer_fee_due_date')->nullable();
            $table->foreignId('transfer_fee_invoice_id')->nullable()->constrained('student_invoices')->nullOnDelete();
            $table->foreignId('reviewed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('reviewed_at')->nullable();
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('approved_at')->nullable();
            $table->foreignId('applied_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('applied_at')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['student_profile_id', 'status'], 'student_transfer_student_status_idx');
            $table->index(['from_study_program_id', 'to_study_program_id'], 'student_transfer_program_idx');
            $table->index(['status', 'created_at'], 'student_transfer_status_created_idx');
        });

        Schema::create('student_transfer_status_histories', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('student_transfer_request_id');
            $table->string('from_status')->nullable();
            $table->string('to_status');
            $table->text('notes')->nullable();
            $table->foreignId('changed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['student_transfer_request_id', 'created_at'], 'student_transfer_history_req_created_idx');
            $table->foreign('student_transfer_request_id', 'student_transfer_history_req_fk')
                ->references('id')
                ->on('student_transfer_requests')
                ->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('student_transfer_status_histories');
        Schema::dropIfExists('student_transfer_requests');
    }
};
