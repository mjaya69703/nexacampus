<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('invoice_installment_requests', function (Blueprint $table) {
            $table->id();
            $table->foreignId('student_invoice_id')->constrained('student_invoices')->cascadeOnDelete();
            $table->foreignId('student_profile_id')->constrained('student_profiles')->cascadeOnDelete();
            $table->unsignedTinyInteger('requested_tenor');
            $table->decimal('requested_fee_amount', 12, 2)->default(0);
            $table->decimal('simulated_total_amount', 12, 2);
            $table->json('simulation_snapshot')->nullable();
            $table->string('status')->default('submitted');
            $table->text('student_reason')->nullable();
            $table->text('finance_notes')->nullable();
            $table->foreignId('reviewed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('reviewed_at')->nullable();
            $table->timestamps();

            $table->index(['student_profile_id', 'status'], 'installment_request_student_status_idx');
            $table->index(['student_invoice_id', 'status'], 'installment_request_invoice_status_idx');
        });

        Schema::create('invoice_installments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('student_invoice_id')->constrained('student_invoices')->cascadeOnDelete();
            $table->foreignId('invoice_installment_request_id')->nullable()->constrained('invoice_installment_requests')->nullOnDelete();
            $table->unsignedTinyInteger('installment_no');
            $table->decimal('amount', 12, 2);
            $table->decimal('fee_amount', 12, 2)->default(0);
            $table->decimal('paid_amount', 12, 2)->default(0);
            $table->date('due_date');
            $table->string('status')->default('pending');
            $table->timestamps();

            $table->unique(['student_invoice_id', 'installment_no'], 'invoice_installment_invoice_no_unique');
            $table->index(['student_invoice_id', 'status'], 'invoice_installment_invoice_status_idx');
            $table->index(['status', 'due_date'], 'invoice_installment_status_due_idx');
        });

        Schema::create('payments', function (Blueprint $table) {
            $table->id();
            $table->string('payment_number')->unique();
            $table->foreignId('student_invoice_id')->constrained('student_invoices')->cascadeOnDelete();
            $table->foreignId('student_profile_id')->constrained('student_profiles')->cascadeOnDelete();
            $table->foreignId('invoice_installment_id')->nullable()->constrained('invoice_installments')->nullOnDelete();
            $table->decimal('amount', 12, 2);
            $table->string('payment_method')->default('bank_transfer');
            $table->string('transaction_reference')->nullable();
            $table->string('proof_file_path')->nullable();
            $table->string('status')->default('pending');
            $table->timestamp('paid_at')->nullable();
            $table->foreignId('submitted_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('verified_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('verified_at')->nullable();
            $table->text('notes')->nullable();
            $table->text('verification_notes')->nullable();
            $table->timestamps();

            $table->index(['student_profile_id', 'paid_at'], 'payment_student_paid_idx');
            $table->index(['student_invoice_id', 'status'], 'payment_invoice_status_idx');
            $table->index(['status', 'created_at'], 'payment_status_created_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payments');
        Schema::dropIfExists('invoice_installments');
        Schema::dropIfExists('invoice_installment_requests');
    }
};
