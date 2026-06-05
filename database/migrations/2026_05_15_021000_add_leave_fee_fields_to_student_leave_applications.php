<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('student_leave_applications', function (Blueprint $table) {
            $table->decimal('leave_fee_amount', 12, 2)->default(0)->after('admin_notes');
            $table->date('leave_fee_due_date')->nullable()->after('leave_fee_amount');
            $table->foreignId('leave_fee_invoice_id')
                ->nullable()
                ->after('leave_fee_due_date')
                ->constrained('student_invoices')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('student_leave_applications', function (Blueprint $table) {
            $table->dropConstrainedForeignId('leave_fee_invoice_id');
            $table->dropColumn(['leave_fee_amount', 'leave_fee_due_date']);
        });
    }
};
