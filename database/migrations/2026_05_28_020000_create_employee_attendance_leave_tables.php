<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('employee_attendance_sources', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('code')->unique();
            $table->string('source_type')->default('manual'); // manual, teaching, import, device
            $table->text('description')->nullable();
            $table->boolean('is_active')->default(true);
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('deleted_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['source_type', 'is_active']);
        });

        Schema::create('employee_attendance_records', function (Blueprint $table) {
            $table->id();
            $table->foreignId('employee_profile_id')->constrained('employee_profiles')->cascadeOnDelete();
            $table->foreignId('work_unit_id')->nullable()->constrained('work_units')->nullOnDelete();
            $table->unsignedBigInteger('employee_attendance_source_id')->nullable();
            $table->nullableMorphs('sourceable');
            $table->date('attendance_date');
            $table->string('status')->default('present'); // present, late, absent, leave, sick, remote
            $table->timestamp('check_in_at')->nullable();
            $table->timestamp('check_out_at')->nullable();
            $table->unsignedInteger('work_minutes')->default(0);
            $table->text('notes')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('deleted_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['employee_profile_id', 'attendance_date', 'employee_attendance_source_id'], 'employee_attendance_unique_day_source');
            $table->index(['attendance_date', 'status']);
            $table->index(['work_unit_id', 'attendance_date']);
            $table->foreign('employee_attendance_source_id', 'employee_attendance_source_fk')
                ->references('id')
                ->on('employee_attendance_sources')
                ->nullOnDelete();
        });

        Schema::create('employee_leave_types', function (Blueprint $table) {
            $table->id();
            $table->foreignId('approval_template_id')->nullable()->constrained('approval_templates')->nullOnDelete();
            $table->string('name');
            $table->string('code')->unique();
            $table->decimal('default_days_per_year', 8, 2)->default(0);
            $table->boolean('requires_approval')->default(true);
            $table->boolean('is_paid')->default(true);
            $table->boolean('is_active')->default(true);
            $table->text('description')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('deleted_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['is_active', 'requires_approval']);
        });

        Schema::create('employee_leave_balances', function (Blueprint $table) {
            $table->id();
            $table->foreignId('employee_profile_id')->constrained('employee_profiles')->cascadeOnDelete();
            $table->foreignId('employee_leave_type_id')->constrained('employee_leave_types')->cascadeOnDelete();
            $table->unsignedSmallInteger('year');
            $table->decimal('allocated_days', 8, 2)->default(0);
            $table->decimal('used_days', 8, 2)->default(0);
            $table->decimal('pending_days', 8, 2)->default(0);
            $table->decimal('carried_over_days', 8, 2)->default(0);
            $table->date('expires_at')->nullable();
            $table->text('notes')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique(['employee_profile_id', 'employee_leave_type_id', 'year'], 'employee_leave_balance_unique_year');
        });

        Schema::create('employee_leave_requests', function (Blueprint $table) {
            $table->id();
            $table->foreignId('employee_profile_id')->constrained('employee_profiles')->cascadeOnDelete();
            $table->foreignId('employee_leave_type_id')->constrained('employee_leave_types')->restrictOnDelete();
            $table->foreignId('approval_request_id')->nullable()->constrained('approval_requests')->nullOnDelete();
            $table->string('request_number')->unique();
            $table->date('starts_at');
            $table->date('ends_at');
            $table->decimal('total_days', 8, 2)->default(0);
            $table->string('status')->default('draft'); // draft, submitted, in_approval, approved, rejected, cancelled
            $table->text('reason');
            $table->text('employee_notes')->nullable();
            $table->text('admin_notes')->nullable();
            $table->foreignId('reviewed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('reviewed_at')->nullable();
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('approved_at')->nullable();
            $table->foreignId('rejected_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('rejected_at')->nullable();
            $table->foreignId('cancelled_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('cancelled_at')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('deleted_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['employee_profile_id', 'status']);
            $table->index(['starts_at', 'ends_at']);
        });

        Schema::create('employee_leave_attachments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('employee_leave_request_id')->constrained('employee_leave_requests')->cascadeOnDelete();
            $table->string('file_path');
            $table->string('file_name');
            $table->string('file_type')->nullable();
            $table->unsignedBigInteger('file_size')->nullable();
            $table->foreignId('uploaded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        $now = now();
        DB::table('employee_attendance_sources')->insert([
            [
                'name' => 'Manual Admin',
                'code' => 'MANUAL_ADMIN',
                'source_type' => 'manual',
                'description' => 'Input absensi pegawai manual dari admin.',
                'is_active' => true,
                'created_at' => $now,
                'updated_at' => $now,
            ],
            [
                'name' => 'Employee Self Service',
                'code' => 'EMPLOYEE_SELF',
                'source_type' => 'self_service',
                'description' => 'Check-in/check-out mandiri dari halaman pegawai.',
                'is_active' => true,
                'created_at' => $now,
                'updated_at' => $now,
            ],
            [
                'name' => 'Teaching Attendance',
                'code' => 'TEACHING_ATTENDANCE',
                'source_type' => 'teaching',
                'description' => 'Sumber opsional dari aktivitas mengajar dosen.',
                'is_active' => true,
                'created_at' => $now,
                'updated_at' => $now,
            ],
        ]);

        DB::table('employee_leave_types')->insert([
            [
                'name' => 'Cuti Tahunan',
                'code' => 'ANNUAL_LEAVE',
                'default_days_per_year' => 12,
                'requires_approval' => true,
                'is_paid' => true,
                'is_active' => true,
                'description' => 'Cuti tahunan pegawai.',
                'created_at' => $now,
                'updated_at' => $now,
            ],
            [
                'name' => 'Cuti Sakit',
                'code' => 'SICK_LEAVE',
                'default_days_per_year' => 0,
                'requires_approval' => true,
                'is_paid' => true,
                'is_active' => true,
                'description' => 'Cuti sakit pegawai.',
                'created_at' => $now,
                'updated_at' => $now,
            ],
            [
                'name' => 'Izin',
                'code' => 'PERMISSION_LEAVE',
                'default_days_per_year' => 0,
                'requires_approval' => true,
                'is_paid' => false,
                'is_active' => true,
                'description' => 'Izin tidak masuk kerja.',
                'created_at' => $now,
                'updated_at' => $now,
            ],
        ]);
    }

    public function down(): void
    {
        Schema::dropIfExists('employee_leave_attachments');
        Schema::dropIfExists('employee_leave_requests');
        Schema::dropIfExists('employee_leave_balances');
        Schema::dropIfExists('employee_leave_types');
        Schema::dropIfExists('employee_attendance_records');
        Schema::dropIfExists('employee_attendance_sources');
    }
};
