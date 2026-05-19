<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('invoice_adjustments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('student_invoice_id')->constrained('student_invoices')->cascadeOnDelete();
            $table->string('adjustment_type');
            $table->decimal('amount', 12, 2);
            $table->nullableMorphs('source');
            $table->text('reason')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['student_invoice_id', 'adjustment_type'], 'invoice_adjustment_invoice_type_idx');
            $table->index(['adjustment_type', 'created_at'], 'invoice_adjustment_type_created_idx');
        });

        Schema::create('student_credit_balances', function (Blueprint $table) {
            $table->id();
            $table->foreignId('student_profile_id')->constrained('student_profiles')->cascadeOnDelete();
            $table->decimal('balance', 12, 2)->default(0);
            $table->timestamps();

            $table->unique('student_profile_id', 'student_credit_balance_student_unique');
        });

        Schema::create('student_credit_transactions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('student_profile_id')->constrained('student_profiles')->cascadeOnDelete();
            $table->foreignId('student_invoice_id')->nullable()->constrained('student_invoices')->nullOnDelete();
            $table->foreignId('payment_id')->nullable()->constrained('payments')->nullOnDelete();
            $table->string('transaction_type');
            $table->decimal('amount', 12, 2);
            $table->text('notes')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['student_profile_id', 'transaction_type'], 'student_credit_tx_student_type_idx');
            $table->index(['student_invoice_id', 'transaction_type'], 'student_credit_tx_invoice_type_idx');
        });

        Schema::create('scholarships', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->text('description')->nullable();
            $table->string('type')->default('partial');
            $table->string('discount_type')->default('percentage');
            $table->decimal('discount_percentage', 5, 2)->nullable();
            $table->decimal('fixed_amount', 12, 2)->nullable();
            $table->unsignedSmallInteger('duration_semesters')->default(1);
            $table->text('requirements')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->softDeletes();

            $table->index(['is_active', 'type'], 'scholarship_active_type_idx');
        });

        Schema::create('student_scholarships', function (Blueprint $table) {
            $table->id();
            $table->foreignId('student_profile_id')->constrained('student_profiles')->cascadeOnDelete();
            $table->foreignId('scholarship_id')->constrained('scholarships')->cascadeOnDelete();
            $table->foreignId('academic_year_id')->nullable()->constrained('academic_years')->nullOnDelete();
            $table->unsignedTinyInteger('semester')->nullable();
            $table->date('start_date')->nullable();
            $table->date('end_date')->nullable();
            $table->string('status')->default('active');
            $table->text('notes')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['student_profile_id', 'scholarship_id', 'academic_year_id', 'semester'], 'student_scholarship_scope_unique');
            $table->index(['student_profile_id', 'status'], 'student_scholarship_student_status_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('student_scholarships');
        Schema::dropIfExists('scholarships');
        Schema::dropIfExists('student_credit_transactions');
        Schema::dropIfExists('student_credit_balances');
        Schema::dropIfExists('invoice_adjustments');
    }
};
