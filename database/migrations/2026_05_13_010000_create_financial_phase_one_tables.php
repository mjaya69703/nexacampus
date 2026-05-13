<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tuition_fees', function (Blueprint $table) {
            $table->id();
            $table->foreignId('academic_year_id')->constrained('academic_years')->cascadeOnDelete();
            $table->foreignId('study_program_id')->constrained('study_programs')->cascadeOnDelete();
            $table->unsignedTinyInteger('semester');
            $table->decimal('base_fee', 12, 2);
            $table->decimal('lab_fee', 12, 2)->default(0);
            $table->decimal('library_fee', 12, 2)->default(0);
            $table->decimal('activity_fee', 12, 2)->default(0);
            $table->decimal('late_penalty_per_day', 12, 2)->default(0);
            $table->date('payment_deadline');
            $table->boolean('is_active')->default(true);
            $table->text('notes')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['academic_year_id', 'study_program_id', 'semester'], 'tuition_fee_year_program_sem_unique');
            $table->index(['is_active', 'payment_deadline'], 'tuition_fee_active_deadline_idx');
        });

        Schema::create('student_invoices', function (Blueprint $table) {
            $table->id();
            $table->string('invoice_number')->unique();
            $table->foreignId('student_profile_id')->constrained('student_profiles')->cascadeOnDelete();
            $table->foreignId('academic_year_id')->nullable()->constrained('academic_years')->nullOnDelete();
            $table->unsignedTinyInteger('semester')->nullable();
            $table->string('invoice_type')->default('tuition');
            $table->nullableMorphs('source');
            $table->decimal('total_amount', 12, 2)->default(0);
            $table->decimal('paid_amount', 12, 2)->default(0);
            $table->decimal('outstanding_amount', 12, 2)->default(0);
            $table->string('status')->default('draft');
            $table->date('due_date');
            $table->timestamp('paid_at')->nullable();
            $table->timestamp('issued_at')->nullable();
            $table->foreignId('issued_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('cancelled_at')->nullable();
            $table->foreignId('cancelled_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('attachment_path')->nullable();
            $table->text('notes')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['student_profile_id', 'status'], 'student_invoice_student_status_idx');
            $table->index(['invoice_type', 'status'], 'student_invoice_type_status_idx');
            $table->index(['status', 'due_date'], 'student_invoice_status_due_idx');
        });

        Schema::create('invoice_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('student_invoice_id')->constrained('student_invoices')->cascadeOnDelete();
            $table->string('item_type')->default('fee');
            $table->string('description');
            $table->decimal('amount', 12, 2);
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->timestamps();

            $table->index(['student_invoice_id', 'item_type'], 'invoice_item_invoice_type_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('invoice_items');
        Schema::dropIfExists('student_invoices');
        Schema::dropIfExists('tuition_fees');
    }
};
