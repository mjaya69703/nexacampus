<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('financial_clearance_policies', function (Blueprint $table) {
            $table->id();
            $table->string('invoice_type');
            $table->string('hold_type');
            $table->string('mode')->default('blocking'); // warning, blocking
            $table->unsignedSmallInteger('grace_days')->default(7);
            $table->boolean('is_active')->default(true);
            $table->text('description')->nullable();
            $table->timestamps();

            $table->unique(['invoice_type', 'hold_type'], 'financial_policy_invoice_hold_unique');
            $table->index(['invoice_type', 'is_active'], 'financial_policy_invoice_active_idx');
        });

        Schema::create('financial_holds', function (Blueprint $table) {
            $table->id();
            $table->foreignId('student_profile_id')->constrained('student_profiles')->cascadeOnDelete();
            $table->foreignId('student_invoice_id')->nullable()->constrained('student_invoices')->nullOnDelete();
            $table->foreignId('financial_clearance_policy_id')->nullable()->constrained('financial_clearance_policies')->nullOnDelete();
            $table->string('hold_type');
            $table->string('status')->default('active'); // active, released, waived
            $table->text('reason')->nullable();
            $table->timestamp('starts_at')->nullable();
            $table->timestamp('blocked_at')->nullable();
            $table->timestamp('waived_until')->nullable();
            $table->timestamp('released_at')->nullable();
            $table->foreignId('released_by')->nullable()->constrained('users')->nullOnDelete();
            $table->text('release_notes')->nullable();
            $table->timestamps();

            $table->index(['student_profile_id', 'status'], 'financial_hold_student_status_idx');
            $table->index(['hold_type', 'status'], 'financial_hold_type_status_idx');
            $table->index(['student_invoice_id', 'status'], 'financial_hold_invoice_status_idx');
        });

        $now = now();
        DB::table('financial_clearance_policies')->insert([
            [
                'invoice_type' => 'tuition',
                'hold_type' => 'registration',
                'mode' => 'blocking',
                'grace_days' => 7,
                'is_active' => true,
                'description' => 'Overdue tuition blocks semester registration after grace period.',
                'created_at' => $now,
                'updated_at' => $now,
            ],
            [
                'invoice_type' => 'tuition',
                'hold_type' => 'study_plan',
                'mode' => 'blocking',
                'grace_days' => 7,
                'is_active' => true,
                'description' => 'Overdue tuition blocks KRS/study plan after grace period.',
                'created_at' => $now,
                'updated_at' => $now,
            ],
            [
                'invoice_type' => 'registration',
                'hold_type' => 'study_plan',
                'mode' => 'blocking',
                'grace_days' => 7,
                'is_active' => true,
                'description' => 'Overdue registration invoice blocks KRS/study plan after grace period.',
                'created_at' => $now,
                'updated_at' => $now,
            ],
            [
                'invoice_type' => 'exam',
                'hold_type' => 'exam_card',
                'mode' => 'blocking',
                'grace_days' => 7,
                'is_active' => true,
                'description' => 'Overdue exam invoice blocks exam card after grace period.',
                'created_at' => $now,
                'updated_at' => $now,
            ],
        ]);
    }

    public function down(): void
    {
        Schema::dropIfExists('financial_holds');
        Schema::dropIfExists('financial_clearance_policies');
    }
};
