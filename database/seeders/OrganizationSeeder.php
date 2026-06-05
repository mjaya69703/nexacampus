<?php

namespace Database\Seeders;

use App\Models\Access\Role;
use App\Models\Access\Permission;
use App\Models\Academic\AcademicYear;
use App\Models\Academic\Faculty;
use App\Models\Academic\StudentProfile;
use App\Models\Academic\StudyProgram;
use App\Models\Organization\ApprovalTemplate;
use App\Models\Organization\EdomPeriod;
use App\Models\Organization\EdomQuestion;
use App\Models\Organization\EmployeeAttendanceLocation;
use App\Models\Organization\EmployeeLeaveBalance;
use App\Models\Organization\EmployeeLeaveType;
use App\Models\Organization\EmployeeProfile;
use App\Models\Organization\LecturerPerformanceRubric;
use App\Models\Organization\LecturerWorkloadPeriod;
use App\Models\Organization\LecturerWorkloadRule;
use App\Models\Organization\OrganizationalPosition;
use App\Models\Organization\TridharmaRecord;
use App\Models\Organization\UserDevelopmentRecord;
use App\Models\Organization\WorkUnit;
use App\Models\User;
use App\Support\Organization\EmployeePositionAssignmentService;
use App\Support\Organization\TridharmaRecordService;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

class OrganizationSeeder extends Seeder
{
    private ?User $admin = null;

    public function run(): void
    {
        $admin = User::query()->where('email', 'superuser@example.com')->first();

        if (! $admin) {
            return;
        }

        $this->admin = $admin;

        $kepegawaian = WorkUnit::updateOrCreate(
            ['code' => 'HRD'],
            [
                'name' => 'Kepegawaian',
                'description' => 'Unit pengelola administrasi kepegawaian kampus.',
                'is_active' => true,
                'created_by' => $admin->id,
            ],
        );

        $akademik = WorkUnit::updateOrCreate(
            ['code' => 'BAAK'],
            [
                'name' => 'Biro Administrasi Akademik',
                'description' => 'Unit layanan administrasi akademik.',
                'is_active' => true,
                'created_by' => $admin->id,
            ],
        );

        EmployeeAttendanceLocation::updateOrCreate(
            ['code' => 'MAIN_CAMPUS'],
            [
                'name' => 'Kampus Utama',
                'address' => 'Gedung Rektorat Kampus Utama.',
                'latitude' => -6.2000000,
                'longitude' => 106.8166660,
                'radius_meters' => 500,
                'is_active' => true,
                'created_by' => $admin->id,
            ],
        );

        $staff = User::firstOrCreate(
            ['email' => 'staff@example.com'],
            [
                'first_name' => 'Staff',
                'last_name' => 'Kepegawaian',
                'photo' => 'default.jpg',
                'username' => 'staff',
                'phone' => '0800000301',
                'code' => Str::random(6),
                'password' => Hash::make('staff123'),
                'is_active' => true,
            ],
        );

        $academicLeaderRole = Role::firstOrCreate(['name' => 'academic-leader', 'guard_name' => 'web']);

        $dekanUser = User::firstOrCreate(
            ['email' => 'dekan@example.com'],
            [
                'first_name' => 'Dekan',
                'last_name' => 'Akademik',
                'photo' => 'default.jpg',
                'username' => 'dekan',
                'phone' => '0800000401',
                'code' => Str::random(6),
                'password' => Hash::make('academic123'),
                'is_active' => true,
            ],
        );

        $kaprodiUser = User::firstOrCreate(
            ['email' => 'kaprodi@example.com'],
            [
                'first_name' => 'Kaprodi',
                'last_name' => 'Akademik',
                'photo' => 'default.jpg',
                'username' => 'kaprodi',
                'phone' => '0800000402',
                'code' => Str::random(6),
                'password' => Hash::make('academic123'),
                'is_active' => true,
            ],
        );

        foreach ([$dekanUser, $kaprodiUser] as $user) {
            if (! $user->hasRole($academicLeaderRole->name)) {
                $user->assignRole($academicLeaderRole);
            }
        }

        foreach ([$admin, $staff] as $user) {
            if (Role::query()->where('name', 'admin')->exists() && ! $user->hasRole('admin')) {
                $user->assignRole('admin');
            }
        }

        if (! $admin->hasRole('academic-leader')) {
            $admin->assignRole('academic-leader');
        }

        $adminProfile = EmployeeProfile::updateOrCreate(
            ['user_id' => $admin->id],
            [
                'primary_work_unit_id' => $kepegawaian->id,
                'employee_number' => 'EMP-0001',
                'employment_type' => 'admin',
                'employment_status' => 'active',
                'join_date' => now()->subYears(2)->toDateString(),
                'notes' => 'Profil pegawai untuk administrator utama.',
                'is_active' => true,
                'created_by' => $admin->id,
            ],
        );

        $staffProfile = EmployeeProfile::updateOrCreate(
            ['user_id' => $staff->id],
            [
                'primary_work_unit_id' => $akademik->id,
                'employee_number' => 'EMP-0002',
                'employment_type' => 'tendik',
                'employment_status' => 'active',
                'join_date' => now()->subYear()->toDateString(),
                'notes' => 'Profil pegawai untuk layanan administrasi akademik.',
                'is_active' => true,
                'created_by' => $admin->id,
            ],
        );

        $dekanProfile = EmployeeProfile::updateOrCreate(
            ['user_id' => $dekanUser->id],
            [
                'primary_work_unit_id' => $akademik->id,
                'employee_number' => 'EMP-0101',
                'employment_type' => 'lecturer',
                'employment_status' => 'active',
                'join_date' => now()->subYears(5)->toDateString(),
                'notes' => 'Profil pegawai untuk akun demo Dekan.',
                'is_active' => true,
                'created_by' => $admin->id,
            ],
        );

        $kaprodiProfile = EmployeeProfile::updateOrCreate(
            ['user_id' => $kaprodiUser->id],
            [
                'primary_work_unit_id' => $akademik->id,
                'employee_number' => 'EMP-0102',
                'employment_type' => 'lecturer',
                'employment_status' => 'active',
                'join_date' => now()->subYears(4)->toDateString(),
                'notes' => 'Profil pegawai untuk akun demo Kaprodi.',
                'is_active' => true,
                'created_by' => $admin->id,
            ],
        );

        $lecturer = User::query()->where('email', 'lecturer@example.com')->first();

        if ($lecturer) {
            EmployeeProfile::updateOrCreate(
                ['user_id' => $lecturer->id],
                [
                    'primary_work_unit_id' => $akademik->id,
                    'employee_number' => 'EMP-0003',
                    'employment_type' => 'lecturer',
                    'employment_status' => 'active',
                    'join_date' => now()->subYears(3)->toDateString(),
                    'notes' => 'Profil pegawai untuk dosen tetap.',
                    'is_active' => true,
                    'created_by' => $admin->id,
                ],
            );
        }

        $kepalaUnit = OrganizationalPosition::query()->where('code', 'KEPALA_UNIT')->first();
        $staffUnit = OrganizationalPosition::query()->where('code', 'STAFF_UNIT')->first();
        $dekan = OrganizationalPosition::query()->where('code', 'DEKAN')->first();
        $kaprodi = OrganizationalPosition::query()->where('code', 'KAPRODI')->first();
        $assignmentService = app(EmployeePositionAssignmentService::class);

        if ($kepalaUnit && ! $adminProfile->positionAssignments()->where('organizational_position_id', $kepalaUnit->id)->where('work_unit_id', $kepegawaian->id)->exists()) {
            $assignmentService->create([
                'employee_profile_id' => $adminProfile->id,
                'organizational_position_id' => $kepalaUnit->id,
                'work_unit_id' => $kepegawaian->id,
                'starts_at' => now()->subYear()->toDateString(),
                'is_primary' => true,
                'is_active' => true,
                'created_by' => $admin->id,
            ]);
        }

        if ($staffUnit && ! $staffProfile->positionAssignments()->where('organizational_position_id', $staffUnit->id)->where('work_unit_id', $akademik->id)->exists()) {
            $assignmentService->create([
                'employee_profile_id' => $staffProfile->id,
                'organizational_position_id' => $staffUnit->id,
                'work_unit_id' => $akademik->id,
                'starts_at' => now()->subMonths(8)->toDateString(),
                'is_primary' => true,
                'is_active' => true,
                'created_by' => $admin->id,
            ]);
        }

        $firstFaculty = Faculty::query()->first();
        $firstProgram = StudyProgram::query()->first();

        if ($dekan && $firstFaculty && ! $adminProfile->positionAssignments()->where('organizational_position_id', $dekan->id)->where('faculty_id', $firstFaculty->id)->exists()) {
            $assignmentService->create([
                'employee_profile_id' => $adminProfile->id,
                'organizational_position_id' => $dekan->id,
                'faculty_id' => $firstFaculty->id,
                'starts_at' => now()->subMonths(6)->toDateString(),
                'is_primary' => false,
                'is_active' => true,
                'created_by' => $admin->id,
            ]);
        }

        if ($dekan && $firstFaculty && ! $dekanProfile->positionAssignments()->where('organizational_position_id', $dekan->id)->where('faculty_id', $firstFaculty->id)->exists()) {
            $assignmentService->create([
                'employee_profile_id' => $dekanProfile->id,
                'organizational_position_id' => $dekan->id,
                'faculty_id' => $firstFaculty->id,
                'starts_at' => now()->subMonths(6)->toDateString(),
                'is_primary' => true,
                'is_active' => true,
                'notes' => 'Scope demo untuk portal academic leader Dekan.',
                'created_by' => $admin->id,
            ]);
        }

        if ($kaprodi && $firstProgram && ! $staffProfile->positionAssignments()->where('organizational_position_id', $kaprodi->id)->where('study_program_id', $firstProgram->id)->exists()) {
            $assignmentService->create([
                'employee_profile_id' => $staffProfile->id,
                'organizational_position_id' => $kaprodi->id,
                'study_program_id' => $firstProgram->id,
                'starts_at' => now()->subMonths(6)->toDateString(),
                'is_primary' => false,
                'is_active' => true,
                'created_by' => $admin->id,
            ]);
        }

        if ($kaprodi && $firstProgram && ! $kaprodiProfile->positionAssignments()->where('organizational_position_id', $kaprodi->id)->where('study_program_id', $firstProgram->id)->exists()) {
            $assignmentService->create([
                'employee_profile_id' => $kaprodiProfile->id,
                'organizational_position_id' => $kaprodi->id,
                'study_program_id' => $firstProgram->id,
                'starts_at' => now()->subMonths(6)->toDateString(),
                'is_primary' => true,
                'is_active' => true,
                'notes' => 'Scope demo untuk portal academic leader Kaprodi.',
                'created_by' => $admin->id,
            ]);
        }

        $template = ApprovalTemplate::updateOrCreate(
            ['code' => 'EMPLOYEE_LEAVE_DEMO'],
            [
                'name' => 'Approval Cuti Pegawai Demo',
                'module' => 'organization',
                'description' => 'Template persetujuan satu langkah untuk cuti pegawai.',
                'is_active' => true,
                'created_by' => $admin->id,
            ],
        );

        $template->steps()->updateOrCreate(
            ['step_order' => 1],
            [
                'name' => 'Approval Kepegawaian',
                'approver_type' => 'user',
                'approver_user_id' => $admin->id,
                'is_required' => true,
                'can_reject' => true,
                'sla_hours' => 24,
                'created_by' => $admin->id,
            ],
        );

        foreach (['ANNUAL_LEAVE', 'SICK_LEAVE', 'PERMISSION_LEAVE'] as $code) {
            EmployeeLeaveType::query()->where('code', $code)->update([
                'approval_template_id' => $template->id,
                'requires_approval' => true,
                'is_active' => true,
                'updated_by' => $admin->id,
            ]);
        }

        $annualLeave = EmployeeLeaveType::query()->where('code', 'ANNUAL_LEAVE')->first();

        if ($annualLeave) {
            foreach ([$adminProfile, $staffProfile] as $profile) {
                EmployeeLeaveBalance::updateOrCreate(
                    [
                        'employee_profile_id' => $profile->id,
                        'employee_leave_type_id' => $annualLeave->id,
                        'year' => now()->year,
                    ],
                    [
                        'allocated_days' => 12,
                        'used_days' => 0,
                        'pending_days' => 0,
                        'carried_over_days' => 0,
                        'notes' => 'Saldo cuti awal periode.',
                        'created_by' => $admin->id,
                        'updated_by' => $admin->id,
                    ],
                );
            }
        }

        $this->seedWorkloadAndEdomFoundation($admin);

        $students = StudentProfile::query()->with('user')->where('is_active', true)->get();
        $this->seedOrganizationJourney();
        $this->seedDevelopmentRecords($students);
        $this->seedTridharmaJourney($students);
    }

    private function seedWorkloadAndEdomFoundation(User $admin): void
    {
        foreach ([
            'lecturer-workload-period.viewAny',
            'lecturer-workload-period.view',
            'lecturer-workload-period.create',
            'lecturer-workload-period.update',
            'lecturer-workload-period.delete',
            'lecturer-workload-rule.viewAny',
            'lecturer-workload-rule.view',
            'lecturer-workload-rule.create',
            'lecturer-workload-rule.update',
            'lecturer-workload-rule.delete',
            'lecturer-workload-submission.viewAny',
            'lecturer-workload-submission.view',
            'lecturer-workload-submission.update',
            'edom-period.viewAny',
            'edom-period.view',
            'edom-period.create',
            'edom-period.update',
            'edom-period.delete',
            'edom-question.viewAny',
            'edom-question.view',
            'edom-question.create',
            'edom-question.update',
            'edom-question.delete',
            'lecturer-performance-review.viewAny',
            'lecturer-performance-review.view',
            'lecturer-performance-review.update',
            'lecturer-performance-rubric.viewAny',
            'lecturer-performance-rubric.view',
            'lecturer-performance-rubric.create',
            'lecturer-performance-rubric.update',
            'lecturer-performance-rubric.delete',
        ] as $permissionName) {
            Permission::findOrCreate($permissionName, 'web');
        }

        if ($admin->hasRole('superuser')) {
            $admin->roles()->where('name', 'superuser')->first()?->givePermissionTo(Permission::where('guard_name', 'web')->get());
        }

        $template = ApprovalTemplate::updateOrCreate(
            ['code' => 'LECTURER_WORKLOAD_REVIEW'],
            [
                'name' => 'Review BKD Dosen',
                'module' => 'organization',
                'description' => 'Approval berlapis untuk pengajuan BKD dosen.',
                'is_active' => true,
                'created_by' => $admin->id,
            ],
        );

        $kaprodiPosition = OrganizationalPosition::query()->where('code', 'KAPRODI')->first();
        $dekanPosition = OrganizationalPosition::query()->where('code', 'DEKAN')->first();

        foreach ([
            [1, 'Review Kaprodi', 'position', null, $kaprodiPosition?->id, 48],
            [2, 'Review Dekan', 'position', null, $dekanPosition?->id, 48],
            [3, 'Finalisasi Kepegawaian Akademik', 'permission', 'lecturer-workload-submission.update', null, 48],
        ] as [$order, $name, $type, $permission, $positionId, $sla]) {
            $template->steps()->updateOrCreate(
                ['step_order' => $order],
                [
                    'name' => $name,
                    'approver_type' => $type,
                    'approver_permission' => $permission,
                    'organizational_position_id' => $positionId,
                    'is_required' => true,
                    'can_reject' => true,
                    'sla_hours' => $sla,
                    'created_by' => $admin->id,
                    'updated_by' => $admin->id,
                ],
            );
        }

        LecturerPerformanceRubric::updateOrCreate(
            ['code' => 'DEFAULT'],
            [
                'name' => 'Rubrik Performa Standar',
                'edom_weight' => 40,
                'teaching_weight' => 30,
                'attendance_weight' => 20,
                'workload_weight' => 10,
                'minimum_responses' => 3,
                'target_workload_sks' => 12,
                'is_active' => true,
                'notes' => 'Rubrik aktif bawaan untuk review performa dosen.',
                'created_by' => $admin->id,
                'updated_by' => $admin->id,
            ],
        );

        foreach ([
            ['structural', 'DEKAN', 'Dekan', 4, 4],
            ['structural', 'WAKIL_DEKAN', 'Wakil Dekan', 3, 3],
            ['structural', 'KAPRODI', 'Kaprodi', 3, 3],
            ['structural', 'SEKPRODI', 'Sekprodi', 2, 2],
            ['tridharma', 'RESEARCH', 'Penelitian Terverifikasi', 3, 6],
            ['tridharma', 'COMMUNITY_SERVICE', 'Pengabdian Terverifikasi', 2, 4],
            ['tridharma', 'PUBLICATION', 'Publikasi/Luaran Terverifikasi', 2, 4],
        ] as [$category, $code, $name, $sks, $max]) {
            LecturerWorkloadRule::updateOrCreate(
                ['category' => $category, 'source_code' => $code],
                [
                    'name' => $name,
                    'sks_value' => $sks,
                    'maximum_sks' => $max,
                    'is_active' => true,
                    'description' => 'Aturan konversi SKS untuk '.$name.'.',
                    'created_by' => $admin->id,
                    'updated_by' => $admin->id,
                ],
            );
        }

        $academicYear = AcademicYear::query()->where('is_active', true)->first() ?: AcademicYear::query()->latest('id')->first();

        LecturerWorkloadPeriod::updateOrCreate(
            ['code' => 'BKD-AKTIF'],
            [
                'academic_year_id' => $academicYear?->id,
                'name' => 'Periode BKD Aktif',
                'starts_at' => now()->subMonth()->toDateString(),
                'ends_at' => now()->addMonths(2)->toDateString(),
                'status' => 'open',
                'minimum_sks' => 12,
                'maximum_sks' => 16,
                'notes' => 'Periode pengajuan BKD berjalan.',
                'created_by' => $admin->id,
                'updated_by' => $admin->id,
            ],
        );

        EdomPeriod::updateOrCreate(
            ['code' => 'EDOM-AKTIF'],
            [
                'academic_year_id' => $academicYear?->id,
                'name' => 'Periode Evaluasi Dosen Aktif',
                'starts_at' => now()->subWeek()->toDateString(),
                'ends_at' => now()->addMonth()->toDateString(),
                'status' => 'open',
                'minimum_responses' => 1,
                'notes' => 'Periode evaluasi pembelajaran berjalan.',
                'created_by' => $admin->id,
                'updated_by' => $admin->id,
            ],
        );

        foreach ([
            ['teaching', 'Dosen menjelaskan materi dengan jelas.', 'scale', 1],
            ['teaching', 'Dosen hadir dan memulai kelas secara konsisten.', 'scale', 2],
            ['assessment', 'Penilaian dan feedback diberikan secara adil.', 'scale', 3],
            ['support', 'Dosen mudah dihubungi untuk kebutuhan akademik.', 'scale', 4],
            ['comment', 'Masukan tambahan untuk perbaikan pembelajaran.', 'text', 5],
        ] as [$category, $text, $type, $order]) {
            EdomQuestion::updateOrCreate(
                ['question_text' => $text],
                [
                    'category' => $category,
                    'answer_type' => $type,
                    'sort_order' => $order,
                    'is_required' => $type === 'scale',
                    'is_active' => true,
                    'created_by' => $admin->id,
                    'updated_by' => $admin->id,
                ],
            );
        }
    }

    private function seedOrganizationJourney(): void
    {
        if (! Schema::hasTable('employee_profiles')) {
            return;
        }

        $profiles = EmployeeProfile::query()->with('user')->where('is_active', true)->get();
        $sourceId = Schema::hasTable('employee_attendance_sources')
            ? DB::table('employee_attendance_sources')->where('code', 'MANUAL_ADMIN')->value('id')
            : null;

        foreach ($profiles as $index => $profile) {
            if (Schema::hasTable('employee_attendance_records') && $sourceId) {
                foreach ([1, 2, 3] as $offset) {
                    $date = now()->subDays($offset + $index)->toDateString();
                    $this->upsertAndGetId('employee_attendance_records', [
                        'employee_profile_id' => $profile->id,
                        'attendance_date' => $date,
                        'employee_attendance_source_id' => $sourceId,
                    ], [
                        'work_unit_id' => $profile->primary_work_unit_id,
                        'sourceable_type' => null,
                        'sourceable_id' => null,
                        'status' => $offset === 3 ? 'late' : 'present',
                        'check_in_at' => now()->subDays($offset + $index)->setTime(8, $offset === 3 ? 25 : 0),
                        'check_out_at' => now()->subDays($offset + $index)->setTime(16, 30),
                        'work_minutes' => $offset === 3 ? 485 : 510,
                        'notes' => 'Absensi tercatat dari sumber administrasi.',
                        'created_by' => $this->admin->id,
                        'updated_by' => $this->admin->id,
                    ]);
                }
            }

            if (Schema::hasTable('employee_leave_requests')) {
                $leaveTypeId = DB::table('employee_leave_types')->where('code', 'ANNUAL_LEAVE')->value('id');
                if ($leaveTypeId) {
                    $this->upsertAndGetId('employee_leave_requests', ['request_number' => 'ELV-'.$profile->employee_number.'-001'], [
                        'employee_profile_id' => $profile->id,
                        'employee_leave_type_id' => $leaveTypeId,
                        'approval_request_id' => null,
                        'starts_at' => now()->addDays(10)->toDateString(),
                        'ends_at' => now()->addDays(11)->toDateString(),
                        'total_days' => 2,
                        'status' => 'approved',
                        'reason' => 'Cuti tahunan.',
                        'employee_notes' => 'Rencana keperluan keluarga.',
                        'admin_notes' => 'Disetujui sesuai kuota cuti.',
                        'reviewed_by' => $this->admin->id,
                        'reviewed_at' => now()->subDays(2),
                        'approved_by' => $this->admin->id,
                        'approved_at' => now()->subDay(),
                        'created_by' => $profile->user_id,
                        'updated_by' => $this->admin->id,
                    ]);
                }
            }
        }
    }

    private function seedDevelopmentRecords($students): void
    {
        if (! Schema::hasTable('user_development_records')) {
            return;
        }

        $users = collect([
            User::query()->where('email', 'lecturer@example.com')->first(),
            User::query()->where('email', 'staff@example.com')->first(),
            $this->admin,
        ])->merge($students->map(fn (StudentProfile $student) => $student->user))->filter();

        foreach ($users as $index => $user) {
            $record = UserDevelopmentRecord::updateOrCreate([
                'user_id' => $user->id,
                'type' => $index % 2 === 0 ? 'certification' : 'training',
                'title' => $index % 2 === 0 ? 'Sertifikasi Transformasi Digital Kampus' : 'Pelatihan Layanan Akademik Terpadu',
            ], [
                'organizer' => 'NexaCampus Academy',
                'credential_number' => 'NC-DEV-'.str_pad((string) $user->id, 5, '0', STR_PAD_LEFT),
                'start_date' => now()->subMonths(2)->toDateString(),
                'end_date' => now()->subMonths(2)->addDays(2)->toDateString(),
                'expires_at' => now()->addYears(2)->toDateString(),
                'cost' => $index % 2 === 0 ? 750000 : 0,
                'description' => 'Riwayat pengembangan kompetensi untuk profil dan verifikasi admin.',
                'is_verified' => $index !== 1,
                'verified_by' => $index !== 1 ? $this->admin->id : null,
                'verified_at' => $index !== 1 ? now()->subMonth() : null,
                'verification_notes' => $index !== 1 ? 'Dokumen telah diverifikasi.' : 'Menunggu pemeriksaan dokumen.',
            ]);

            if (Schema::hasTable('user_development_attachments')) {
                $this->upsertAndGetId('user_development_attachments', [
                    'user_development_record_id' => $record->id,
                    'document_type' => 'certificate',
                ], [
                    'file_path' => 'samples/user-developments/'.$record->id.'.pdf',
                    'file_name' => Str::slug($record->title).'.pdf',
                    'file_size' => 182400,
                ]);
            }
        }
    }

    private function seedTridharmaJourney($students): void
    {
        if (! Schema::hasTable('tridharma_records')) {
            return;
        }

        foreach ([
            'tridharma-record.viewAny',
            'tridharma-record.view',
            'tridharma-record.create',
            'tridharma-record.update',
            'tridharma-record.delete',
            'tridharma-record.verify',
            'tridharma-record.approve',
            'tridharma-record.complete',
        ] as $permissionName) {
            Permission::findOrCreate($permissionName, 'web');
        }

        if ($this->admin->hasRole('superuser')) {
            $this->admin->roles()->where('name', 'superuser')->first()?->givePermissionTo(Permission::where('guard_name', 'web')->get());
        }

        app(TridharmaRecordService::class)->ensureDefaultApprovalTemplate($this->admin);

        $lecturer = User::query()->where('email', 'lecturer@example.com')->with(['lecturerProfile', 'employeeProfile'])->first();
        $staff = User::query()->where('email', 'staff@example.com')->with(['lecturerProfile', 'employeeProfile'])->first();
        $studentUser = $students->first()?->user?->load(['lecturerProfile', 'employeeProfile']);

        if ($studentUser) {
            TridharmaRecord::withTrashed()
                ->where('user_id', $studentUser->id)
                ->where('title', 'Dashboard Layanan Akademik Terpadu untuk Perguruan Tinggi')
                ->forceDelete();
        }

        $records = collect([
            [
                'user' => $lecturer,
                'type' => 'research',
                'title' => 'Model Prediksi Retensi Mahasiswa Berbasis Data Akademik',
                'scheme' => 'Hibah Internal',
                'status' => 'approved',
                'funding_amount' => 18000000,
                'funding_source' => 'LPPM Kampus',
            ],
            [
                'user' => $staff,
                'type' => 'community_service',
                'title' => 'Pelatihan Literasi Digital untuk Administrasi Sekolah',
                'scheme' => 'Pengabdian Institusi',
                'status' => 'active',
                'funding_amount' => 7500000,
                'funding_source' => 'Unit Kepegawaian',
            ],
            [
                'user' => $this->admin->load(['lecturerProfile', 'employeeProfile']),
                'type' => 'publication',
                'title' => 'Dashboard Layanan Akademik Terpadu untuk Perguruan Tinggi',
                'scheme' => 'Publikasi Institusi',
                'status' => 'completed',
                'funding_amount' => 0,
                'funding_source' => 'Internal Kampus',
            ],
        ])->filter(fn ($item) => $item['user']);

        foreach ($records as $index => $item) {
            $user = $item['user'];
            $record = TridharmaRecord::updateOrCreate([
                'user_id' => $user->id,
                'type' => $item['type'],
                'title' => $item['title'],
            ], [
                'lecturer_profile_id' => $user->lecturerProfile?->id,
                'employee_profile_id' => $user->employeeProfile?->id,
                'scheme' => $item['scheme'],
                'abstract' => 'Kegiatan Tridharma yang melibatkan pengelolaan proposal, tim, anggaran, luaran, dan dokumen pendukung.',
                'starts_at' => now()->subMonths(3)->toDateString(),
                'ends_at' => now()->addMonths(3 + $index)->toDateString(),
                'status' => $item['status'],
                'funding_amount' => $item['funding_amount'],
                'funding_source' => $item['funding_source'],
                'is_verified' => $index !== 1,
                'verified_by' => $index !== 1 ? $this->admin->id : null,
                'verified_at' => $index !== 1 ? now()->subMonth() : null,
                'verification_notes' => $index !== 1 ? 'Record telah diverifikasi.' : 'Menunggu verifikasi lapangan.',
                'approved_by' => $this->admin->id,
                'approved_at' => now()->subMonths(2),
                'completed_by' => $item['status'] === 'completed' ? $this->admin->id : null,
                'completed_at' => $item['status'] === 'completed' ? now()->subWeek() : null,
                'created_by' => $user->id,
                'updated_by' => $this->admin->id,
            ]);

            $record->members()->updateOrCreate(
                ['user_id' => $user->id, 'role' => 'leader'],
                ['is_external' => false, 'sort_order' => 1],
            );
            $record->members()->updateOrCreate(
                ['member_name' => 'Mitra Eksternal', 'role' => 'partner'],
                ['institution' => 'Institusi Mitra', 'email' => 'mitra@example.com', 'is_external' => true, 'sort_order' => 2],
            );

            if ($studentUser && $index === 0) {
                $record->members()->updateOrCreate(
                    ['user_id' => $studentUser->id, 'role' => 'student_collaborator'],
                    ['is_external' => false, 'sort_order' => 3],
                );
            }

            foreach ([['Proposal', 25, 'completed'], ['Pelaksanaan', 70, $item['status'] === 'completed' ? 'completed' : 'in_progress'], ['Luaran', $item['status'] === 'completed' ? 100 : 30, $item['status'] === 'completed' ? 'completed' : 'in_progress']] as $order => [$title, $progress, $status]) {
                $record->milestones()->updateOrCreate(
                    ['title' => $title],
                    [
                        'description' => 'Target '.$title,
                        'due_date' => now()->addWeeks($order + 1)->toDateString(),
                        'status' => $status,
                        'progress_percentage' => $progress,
                        'completed_at' => $status === 'completed' ? now()->subDays($order + 1) : null,
                        'completed_by' => $status === 'completed' ? $user->id : null,
                        'sort_order' => $order + 1,
                    ],
                );
            }

            foreach ([['Honorarium', 5000000, 2500000], ['Operasional', 3000000, 1250000]] as [$category, $planned, $realized]) {
                $record->budgets()->updateOrCreate(
                    ['category' => $category],
                    ['description' => 'Anggaran '.$category, 'planned_amount' => $planned, 'realized_amount' => $realized],
                );
            }

            $record->outputs()->updateOrCreate(
                ['output_type' => $item['type'] === 'publication' ? 'article' : 'report', 'title' => 'Luaran '.$item['title']],
                [
                    'publisher' => $item['type'] === 'publication' ? 'Jurnal Teknologi Pendidikan' : 'Repository LPPM',
                    'indexing' => $item['type'] === 'publication' ? 'Sinta' : null,
                    'doi' => $item['type'] === 'publication' ? '10.0000/nexacampus.'.$record->id : null,
                    'url' => 'https://example.com/tridharma/'.$record->id,
                    'published_at' => $item['status'] === 'completed' ? now()->subDays(10)->toDateString() : null,
                    'status' => $item['status'] === 'completed' ? 'published' : 'draft',
                ],
            );

            $record->attachments()->updateOrCreate(
                ['document_type' => 'proposal'],
                [
                    'file_path' => 'samples/tridharma/'.$record->id.'-proposal.pdf',
                    'file_name' => Str::slug($record->title).'-proposal.pdf',
                    'mime_type' => 'application/pdf',
                    'file_size' => 225280,
                    'uploaded_by' => $user->id,
                ],
            );
        }
    }

    private function upsertAndGetId(string $table, array $keys, array $values): int
    {
        $now = now();
        $payload = array_merge($values, ['updated_at' => $now]);

        if (Schema::hasColumn($table, 'created_at')) {
            $payload['created_at'] = $now;
        }

        DB::table($table)->updateOrInsert($keys, $payload);

        return (int) DB::table($table)->where($keys)->value('id');
    }
}
