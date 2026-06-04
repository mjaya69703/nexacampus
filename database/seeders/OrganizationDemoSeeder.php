<?php

namespace Database\Seeders;

use App\Models\Access\Role;
use App\Models\Access\Permission;
use App\Models\Academic\AcademicYear;
use App\Models\Academic\Faculty;
use App\Models\Academic\StudyProgram;
use App\Models\Organization\ApprovalTemplate;
use App\Models\Organization\EdomPeriod;
use App\Models\Organization\EdomQuestion;
use App\Models\Organization\EmployeeAttendanceLocation;
use App\Models\Organization\EmployeeLeaveBalance;
use App\Models\Organization\EmployeeLeaveType;
use App\Models\Organization\EmployeeProfile;
use App\Models\Organization\LecturerWorkloadPeriod;
use App\Models\Organization\LecturerWorkloadRule;
use App\Models\Organization\OrganizationalPosition;
use App\Models\Organization\WorkUnit;
use App\Models\User;
use App\Support\Organization\EmployeePositionAssignmentService;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class OrganizationDemoSeeder extends Seeder
{
    public function run(): void
    {
        $admin = User::query()->where('email', 'superuser@example.com')->first();

        if (! $admin) {
            return;
        }

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
                'description' => 'Approval internal untuk pengajuan BKD dosen.',
                'is_active' => true,
                'created_by' => $admin->id,
            ],
        );

        $template->steps()->updateOrCreate(
            ['step_order' => 1],
            [
                'name' => 'Review Kepegawaian Akademik',
                'approver_type' => 'permission',
                'approver_permission' => 'lecturer-workload-submission.update',
                'is_required' => true,
                'can_reject' => true,
                'sla_hours' => 48,
                'created_by' => $admin->id,
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
}
