<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('employee_profiles', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('primary_work_unit_id')->nullable()->constrained('work_units')->nullOnDelete();
            $table->string('employee_number')->nullable()->unique();
            $table->string('employment_type')->default('staff'); // lecturer, tendik, admin_staff, contract, guest, staff
            $table->string('employment_status')->default('active'); // active, inactive, suspended, resigned
            $table->date('join_date')->nullable();
            $table->date('end_date')->nullable();
            $table->text('notes')->nullable();
            $table->boolean('is_active')->default(true);
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('deleted_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();

            $table->unique('user_id');
            $table->index(['employment_type', 'employment_status']);
            $table->index(['primary_work_unit_id', 'is_active']);
        });

        Schema::create('organizational_positions', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('code')->unique();
            $table->string('category')->default('staff'); // executive, faculty, study_program, work_unit, staff, academic
            $table->string('scope_type')->default('none'); // none, faculty, study_program, work_unit
            $table->text('description')->nullable();
            $table->boolean('is_active')->default(true);
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('deleted_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['category', 'scope_type']);
            $table->index(['is_active', 'name']);
        });

        Schema::create('employee_position_assignments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('employee_profile_id')->constrained('employee_profiles')->cascadeOnDelete();
            $table->foreignId('organizational_position_id')->constrained('organizational_positions')->restrictOnDelete();
            $table->foreignId('faculty_id')->nullable()->constrained('faculties')->nullOnDelete();
            $table->foreignId('study_program_id')->nullable()->constrained('study_programs')->nullOnDelete();
            $table->foreignId('work_unit_id')->nullable()->constrained('work_units')->nullOnDelete();
            $table->date('starts_at')->nullable();
            $table->date('ends_at')->nullable();
            $table->boolean('is_primary')->default(false);
            $table->boolean('is_active')->default(true);
            $table->text('notes')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('deleted_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['employee_profile_id', 'is_active'], 'employee_position_employee_active_idx');
            $table->index(['organizational_position_id', 'is_active'], 'employee_position_position_active_idx');
            $table->index(['faculty_id', 'is_active'], 'employee_position_faculty_active_idx');
            $table->index(['study_program_id', 'is_active'], 'employee_position_program_active_idx');
            $table->index(['work_unit_id', 'is_active'], 'employee_position_unit_active_idx');
        });

        $now = now();
        DB::table('organizational_positions')->insert([
            [
                'name' => 'Rektor',
                'code' => 'REKTOR',
                'category' => 'executive',
                'scope_type' => 'none',
                'description' => 'Pimpinan tertinggi kampus.',
                'is_active' => true,
                'created_at' => $now,
                'updated_at' => $now,
            ],
            [
                'name' => 'Wakil Rektor',
                'code' => 'WAKIL_REKTOR',
                'category' => 'executive',
                'scope_type' => 'none',
                'description' => 'Pimpinan wakil rektor untuk area operasional tertentu.',
                'is_active' => true,
                'created_at' => $now,
                'updated_at' => $now,
            ],
            [
                'name' => 'Dekan',
                'code' => 'DEKAN',
                'category' => 'faculty',
                'scope_type' => 'faculty',
                'description' => 'Pimpinan fakultas dengan scope fakultas.',
                'is_active' => true,
                'created_at' => $now,
                'updated_at' => $now,
            ],
            [
                'name' => 'Wakil Dekan',
                'code' => 'WAKIL_DEKAN',
                'category' => 'faculty',
                'scope_type' => 'faculty',
                'description' => 'Wakil pimpinan fakultas dengan scope fakultas.',
                'is_active' => true,
                'created_at' => $now,
                'updated_at' => $now,
            ],
            [
                'name' => 'Kaprodi',
                'code' => 'KAPRODI',
                'category' => 'study_program',
                'scope_type' => 'study_program',
                'description' => 'Kepala program studi dengan scope program studi.',
                'is_active' => true,
                'created_at' => $now,
                'updated_at' => $now,
            ],
            [
                'name' => 'Sekprodi',
                'code' => 'SEKPRODI',
                'category' => 'study_program',
                'scope_type' => 'study_program',
                'description' => 'Sekretaris program studi dengan scope program studi.',
                'is_active' => true,
                'created_at' => $now,
                'updated_at' => $now,
            ],
            [
                'name' => 'Kepala Unit',
                'code' => 'KEPALA_UNIT',
                'category' => 'work_unit',
                'scope_type' => 'work_unit',
                'description' => 'Pimpinan unit kerja operasional.',
                'is_active' => true,
                'created_at' => $now,
                'updated_at' => $now,
            ],
            [
                'name' => 'Staff Unit',
                'code' => 'STAFF_UNIT',
                'category' => 'work_unit',
                'scope_type' => 'work_unit',
                'description' => 'Staff operasional pada unit kerja.',
                'is_active' => true,
                'created_at' => $now,
                'updated_at' => $now,
            ],
            [
                'name' => 'Tendik',
                'code' => 'TENDIK',
                'category' => 'staff',
                'scope_type' => 'work_unit',
                'description' => 'Tenaga kependidikan pada unit kerja.',
                'is_active' => true,
                'created_at' => $now,
                'updated_at' => $now,
            ],
            [
                'name' => 'Dosen',
                'code' => 'DOSEN',
                'category' => 'academic',
                'scope_type' => 'none',
                'description' => 'Dosen sebagai pegawai akademik; aktivitas mengajar tetap memakai lecturer profile dan course offering.',
                'is_active' => true,
                'created_at' => $now,
                'updated_at' => $now,
            ],
        ]);
    }

    public function down(): void
    {
        Schema::dropIfExists('employee_position_assignments');
        Schema::dropIfExists('organizational_positions');
        Schema::dropIfExists('employee_profiles');
    }
};
