<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('invoice_schedules', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('invoice_kind')->default('tuition'); // tuition, custom
            $table->string('generation_mode')->default('active_students'); // single, selected_students, active_students
            $table->foreignId('student_profile_id')->nullable()->constrained('student_profiles')->nullOnDelete();
            $table->json('student_profile_ids')->nullable();
            $table->foreignId('academic_year_id')->nullable()->constrained('academic_years')->nullOnDelete();
            $table->unsignedTinyInteger('semester')->nullable();
            $table->string('invoice_type')->default('tuition');
            $table->json('items')->nullable();
            $table->date('due_date');
            $table->timestamp('publish_at');
            $table->boolean('issue_immediately')->default(true);
            $table->string('status')->default('pending'); // pending, running, completed, failed, cancelled
            $table->boolean('is_active')->default(true);
            $table->unsignedInteger('created_count')->default(0);
            $table->unsignedInteger('skipped_count')->default(0);
            $table->unsignedInteger('failed_count')->default(0);
            $table->json('last_result')->nullable();
            $table->timestamp('last_run_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->text('notes')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['status', 'publish_at']);
            $table->index(['is_active', 'publish_at']);
        });

        Schema::table('student_invoices', function (Blueprint $table) {
            $table->foreignId('invoice_schedule_id')->nullable()->after('source_id')->constrained('invoice_schedules')->nullOnDelete();
            $table->timestamp('issued_notified_at')->nullable()->after('issued_by');
            $table->timestamp('overdue_notified_at')->nullable()->after('paid_at');
        });
    }

    public function down(): void
    {
        Schema::table('student_invoices', function (Blueprint $table) {
            $table->dropConstrainedForeignId('invoice_schedule_id');
            $table->dropColumn(['issued_notified_at', 'overdue_notified_at']);
        });

        Schema::dropIfExists('invoice_schedules');
    }
};
