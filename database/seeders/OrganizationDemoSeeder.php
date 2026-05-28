<?php

namespace Database\Seeders;

use App\Models\Access\Role;
use App\Models\Organization\ApprovalTemplate;
use App\Models\Organization\EmployeeAttendanceLocation;
use App\Models\Organization\EmployeeLeaveBalance;
use App\Models\Organization\EmployeeLeaveType;
use App\Models\Organization\EmployeeProfile;
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
                'description' => 'Unit demo untuk pengujian fitur kepegawaian.',
                'is_active' => true,
                'created_by' => $admin->id,
            ],
        );

        $akademik = WorkUnit::updateOrCreate(
            ['code' => 'BAAK'],
            [
                'name' => 'Biro Administrasi Akademik',
                'description' => 'Unit demo untuk pegawai administrasi akademik.',
                'is_active' => true,
                'created_by' => $admin->id,
            ],
        );

        EmployeeAttendanceLocation::updateOrCreate(
            ['code' => 'MAIN_CAMPUS'],
            [
                'name' => 'Kampus Utama',
                'address' => 'Lokasi demo. Ubah koordinat sesuai lokasi kantor/kampus untuk testing GPS.',
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

        foreach ([$admin, $staff] as $user) {
            if (Role::query()->where('name', 'admin')->exists() && ! $user->hasRole('admin')) {
                $user->assignRole('admin');
            }
        }

        $adminProfile = EmployeeProfile::updateOrCreate(
            ['user_id' => $admin->id],
            [
                'primary_work_unit_id' => $kepegawaian->id,
                'employee_number' => 'EMP-0001',
                'employment_type' => 'admin',
                'employment_status' => 'active',
                'join_date' => now()->subYears(2)->toDateString(),
                'notes' => 'Profil pegawai demo untuk superuser.',
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
                'notes' => 'Profil pegawai demo untuk testing absensi dan cuti.',
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
                    'notes' => 'Profil pegawai demo untuk dosen.',
                    'is_active' => true,
                    'created_by' => $admin->id,
                ],
            );
        }

        $kepalaUnit = OrganizationalPosition::query()->where('code', 'KEPALA_UNIT')->first();
        $staffUnit = OrganizationalPosition::query()->where('code', 'STAFF_UNIT')->first();
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

        $template = ApprovalTemplate::updateOrCreate(
            ['code' => 'EMPLOYEE_LEAVE_DEMO'],
            [
                'name' => 'Approval Cuti Pegawai Demo',
                'module' => 'organization',
                'description' => 'Template demo satu langkah untuk pengujian cuti pegawai.',
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
                        'notes' => 'Saldo cuti demo.',
                        'created_by' => $admin->id,
                        'updated_by' => $admin->id,
                    ],
                );
            }
        }
    }
}
