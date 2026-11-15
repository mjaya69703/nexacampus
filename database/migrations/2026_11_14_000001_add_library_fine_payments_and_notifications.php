<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('book_loans', function (Blueprint $table) {
            $table->string('fine_payment_status', 20)->default('unpaid')->after('fine_paid_at');
            $table->string('fine_proof_path')->nullable()->after('fine_payment_status');
            $table->string('fine_proof_original_name')->nullable()->after('fine_proof_path');
            $table->text('fine_payment_notes')->nullable()->after('fine_proof_original_name');
            $table->dateTime('fine_submitted_at')->nullable()->after('fine_payment_notes');
            $table->dateTime('fine_verified_at')->nullable()->after('fine_submitted_at');
            $table->foreignId('fine_verified_by')
                ->nullable()
                ->after('fine_verified_at')
                ->constrained('users')
                ->nullOnDelete();
            $table->text('fine_rejection_reason')->nullable()->after('fine_verified_by');
            $table->dateTime('due_reminder_h3_sent_at')->nullable()->after('fine_rejection_reason');
            $table->dateTime('due_reminder_h1_sent_at')->nullable()->after('due_reminder_h3_sent_at');
            $table->dateTime('overdue_notified_at')->nullable()->after('due_reminder_h1_sent_at');

            $table->index('fine_payment_status');
        });

        Schema::table('book_reservations', function (Blueprint $table) {
            $table->dateTime('ready_notified_at')->nullable()->after('expires_at');
        });

        DB::table('book_loans')
            ->where('fine_paid', true)
            ->update(['fine_payment_status' => 'verified']);
    }

    public function down(): void
    {
        Schema::table('book_loans', function (Blueprint $table) {
            $table->dropIndex(['fine_payment_status']);
            $table->dropConstrainedForeignId('fine_verified_by');
            $table->dropColumn([
                'fine_payment_status',
                'fine_proof_path',
                'fine_proof_original_name',
                'fine_payment_notes',
                'fine_submitted_at',
                'fine_verified_at',
                'fine_rejection_reason',
                'due_reminder_h3_sent_at',
                'due_reminder_h1_sent_at',
                'overdue_notified_at',
            ]);
        });

        Schema::table('book_reservations', function (Blueprint $table) {
            $table->dropColumn('ready_notified_at');
        });
    }
};
