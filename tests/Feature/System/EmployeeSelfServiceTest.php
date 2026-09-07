<?php

use App\Models\Organization\EmployeeAttendanceLocation;
use App\Models\Organization\EmployeeAttendanceRecord;
use App\Models\Organization\EmployeeLeaveRequest;
use App\Models\Organization\EmployeeLeaveType;
use App\Models\Organization\EmployeeProfile;
use App\Models\Organization\TridharmaRecord;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Spatie\Permission\Models\Role;

function employeeTestSetup(array $roles = ['admin']): User
{
    DB::table('systems')->updateOrInsert(['id' => 1], [
        'app_name' => 'NexaCampus Test',
        'app_version' => 'test',
        'app_description' => 'NexaCampus Test',
        'app_url' => 'https://example.test',
        'app_email' => 'campus@example.test',
        'is_installed' => true,
        'updated_at' => now(),
        'created_at' => now(),
    ]);

    foreach ($roles as $role) {
        Role::firstOrCreate(['name' => $role, 'guard_name' => 'web']);
    }

    $user = User::factory()->create();
    $user->assignRole($roles);

    EmployeeProfile::create(['user_id' => $user->id, 'is_active' => true]);

    return $user;
}

it('redirects guests away from employee pages to login', function () {
    employeeTestSetup();

    $this->get('/employee/attendance')->assertRedirect('/auth/login');
    $this->get('/employee/leaves')->assertRedirect('/auth/login');
    $this->get('/employee/tridharma')->assertRedirect('/auth/login');
});

it('forbids employee pages without an active employee profile', function () {
    DB::table('systems')->updateOrInsert(['id' => 1], [
        'app_name' => 'NexaCampus Test',
        'app_version' => 'test',
        'app_description' => 'NexaCampus Test',
        'app_url' => 'https://example.test',
        'app_email' => 'campus@example.test',
        'is_installed' => true,
        'updated_at' => now(),
        'created_at' => now(),
    ]);

    $user = User::factory()->create();
    Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web']);
    $user->assignRole('admin');

    $this->actingAs($user)->withSession(['active_role' => 'admin'])->get('/employee/attendance')->assertForbidden();
    $this->actingAs($user)->withSession(['active_role' => 'admin'])->get('/employee/leaves')->assertRedirect('/employee/attendance?tab=cuti');
    $this->actingAs($user)->withSession(['active_role' => 'admin'])->get('/employee/tridharma')->assertForbidden();
});

it('redirects the legacy leaves url into the kehadiran cuti tab', function () {
    $user = employeeTestSetup();

    $this->actingAs($user)
        ->get('/employee/leaves')
        ->assertRedirect('/employee/attendance?tab=cuti');
});

it('renders the kehadiran dashboard with the cuti tab through Inertia', function () {
    $user = employeeTestSetup();

    EmployeeLeaveType::create([
        'name' => 'Tahunan', 'code' => 'ANNUAL', 'default_days_per_year' => 12,
        'requires_approval' => false, 'is_active' => true,
    ]);

    $this->actingAs($user)
        ->get('/employee/attendance?tab=cuti')
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('Shared/Employee/Attendance')
            ->where('initialTab', 'cuti')
            ->has('shell')
            ->has('shell.menus')
            ->has('leaveTypes')
            ->has('balances')
            ->has('leaveRequests'));
});

it('submits a leave request and auto-approves types without approval', function () {
    $user = employeeTestSetup();

    $type = EmployeeLeaveType::create([
        'name' => 'Tahunan', 'code' => 'ANNUAL', 'default_days_per_year' => 0,
        'requires_approval' => false, 'is_active' => true,
    ]);

    $this->actingAs($user)
        ->post('/employee/leaves', [
            'employee_leave_type_id' => $type->id,
            'starts_at' => now()->addDay()->toDateString(),
            'ends_at' => now()->addDays(2)->toDateString(),
            'reason' => 'Keperluan keluarga',
        ])
        ->assertRedirect('/employee/leaves')
        ->assertSessionHas('success');

    expect(EmployeeLeaveRequest::where('employee_leave_type_id', $type->id)->exists())->toBeTrue();
});

it('renders the tridharma index and creates a draft with team members', function () {
    $user = employeeTestSetup();
    $teammate = User::factory()->create();

    $this->actingAs($user)
        ->get('/employee/tridharma')
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('Shared/Employee/Tridharma/Index')
            ->has('shell')
            ->has('records')
            ->has('stats'));

    $response = $this->actingAs($user)->post('/employee/tridharma', [
        'type' => 'research',
        'title' => 'Riset Pembelajaran Adaptif',
        'scheme' => 'Mandiri',
        'action' => 'draft',
        'team' => [
            ['kind' => 'internal', 'user_id' => $teammate->id, 'role' => 'member'],
            ['kind' => 'external', 'name' => 'Mitra Kampus', 'institution' => 'PT Contoh', 'email' => 'mitra@example.test', 'role' => 'partner'],
        ],
    ]);

    $record = TridharmaRecord::where('title', 'Riset Pembelajaran Adaptif')->first();

    expect($record)->not->toBeNull()
        ->and($record->status)->toBe('draft')
        ->and($record->members)->toHaveCount(3);

    $response->assertRedirect(route('employee.tridharma.show', $record));
});

it('manages tridharma milestones, outputs, members, and documents', function () {
    Storage::fake();
    $user = employeeTestSetup();

    $record = TridharmaRecord::create([
        'user_id' => $user->id,
        'type' => 'research',
        'title' => 'Riset Uji',
        'status' => 'draft',
        'created_by' => $user->id,
    ]);
    $record->members()->create(['user_id' => $user->id, 'role' => 'leader', 'is_external' => false, 'sort_order' => 1]);

    $this->actingAs($user)
        ->get(route('employee.tridharma.show', $record))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('Shared/Employee/Tridharma/Show')
            ->has('record')
            ->has('record.milestones')
            ->has('record.members'));

    // Milestone.
    $this->actingAs($user)
        ->post(route('employee.tridharma.milestones.store', $record), [
            'title' => 'Pengumpulan data',
            'progress_percentage' => 20,
        ])
        ->assertRedirect(route('employee.tridharma.show', $record));

    $milestone = $record->milestones()->first();
    expect($milestone)->not->toBeNull()->and($milestone->status)->toBe('in_progress');

    // Output + dokumen.
    $this->actingAs($user)
        ->post(route('employee.tridharma.outputs.store', $record), [
            'output_type' => 'article',
            'title' => 'Artikel uji',
            'status' => 'draft',
            'document' => UploadedFile::fake()->create('luaran.pdf', 100, 'application/pdf'),
        ])
        ->assertRedirect(route('employee.tridharma.show', $record));

    expect($record->outputs()->exists())->toBeTrue();
    expect($record->outputs()->first()->attachments)->toHaveCount(1);

    // Anggota eksternal + hapus.
    $this->actingAs($user)
        ->post(route('employee.tridharma.members.store', $record), [
            'kind' => 'external',
            'member_name' => 'Narasumber',
            'role' => 'partner',
        ])
        ->assertRedirect(route('employee.tridharma.show', $record));

    $member = $record->members()->where('member_name', 'Narasumber')->first();
    expect($member)->not->toBeNull();

    $this->actingAs($user)
        ->delete(route('employee.tridharma.members.destroy', [$record, $member->id]))
        ->assertRedirect(route('employee.tridharma.show', $record));

    expect($record->members()->whereKey($member->id)->exists())->toBeFalse();

    // Dokumen bukti.
    $this->actingAs($user)
        ->post(route('employee.tridharma.attachments.store', $record), [
            'document' => UploadedFile::fake()->create('bukti.pdf', 100, 'application/pdf'),
        ])
        ->assertRedirect(route('employee.tridharma.show', $record));

    expect($record->attachments()->where('document_type', 'evidence')->exists())->toBeTrue();
});

it('renders lecturer tridharma pages through Inertia without an employee profile', function () {
    DB::table('systems')->updateOrInsert(['id' => 1], [
        'app_name' => 'NexaCampus Test',
        'app_version' => 'test',
        'app_description' => 'NexaCampus Test',
        'app_url' => 'https://example.test',
        'app_email' => 'campus@example.test',
        'is_installed' => true,
        'updated_at' => now(),
        'created_at' => now(),
    ]);
    Role::firstOrCreate(['name' => 'lecturer', 'guard_name' => 'web']);

    $user = User::factory()->create();
    $user->assignRole('lecturer');

    $this->actingAs($user)
        ->withSession(['active_role' => 'lecturer'])
        ->get('/lecturer/tridharma')
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('Shared/Employee/Tridharma/Index')
            ->has('shell')
            ->has('records'));

    $this->actingAs($user)
        ->withSession(['active_role' => 'lecturer'])
        ->get('/lecturer/tridharma/create')
        ->assertOk()
            ->assertInertia(fn ($page) => $page->component('Shared/Employee/Tridharma/Create'));
});

it('shows approved and pending leaves inside the attendance history', function () {
    $user = employeeTestSetup();
    $employee = $user->employeeProfile;

    $type = EmployeeLeaveType::create([
        'name' => 'Tahunan', 'code' => 'ANNUAL', 'default_days_per_year' => 12,
        'requires_approval' => false, 'is_active' => true,
    ]);

    EmployeeLeaveRequest::create([
        'employee_profile_id' => $employee->id,
        'employee_leave_type_id' => $type->id,
        'request_number' => 'ELV-TEST-0001',
        'starts_at' => now()->subDays(2)->toDateString(),
        'ends_at' => now()->subDays(1)->toDateString(),
        'total_days' => 2,
        'status' => 'approved',
        'reason' => 'Cuti disetujui',
    ]);

    $this->actingAs($user)
        ->get('/employee/attendance')
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('Shared/Employee/Attendance')
            ->has('records')
            ->where('records', fn ($records) => collect($records)->contains(
                fn ($row) => ($row['kind'] ?? null) === 'leave'
                    && $row['status'] === 'leave_approved'
                    && $row['dateLabel'] === now()->subDays(1)->format('d M Y')
            )));
});

it('searches internal users for the tridharma team', function () {
    $user = employeeTestSetup();
    $teammate = User::factory()->create(['first_name' => 'SitiZahra', 'last_name' => 'UnikSekali']);

    $response = $this->actingAs($user)->getJson('/employee/users/search?q=sitizahra');

    $response->assertOk()->assertJsonFragment(['email' => $teammate->email]);
});

it('records self-service check-in and check-out inside the office radius', function () {
    Storage::fake('public');
    $user = employeeTestSetup();

    EmployeeAttendanceLocation::create([
        'name' => 'Kantor Pusat',
        'code' => 'KTR',
        'latitude' => -6.2,
        'longitude' => 106.8,
        'radius_meters' => 500,
        'is_active' => true,
    ]);

    $this->actingAs($user)
        ->get('/employee/attendance')
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('Shared/Employee/Attendance')
            ->has('shell')
            ->has('today')
            ->has('locations')
            ->has('records'));

    $payload = [
        'photo' => UploadedFile::fake()->image('absen.jpg', 640, 480),
        'latitude' => -6.2,
        'longitude' => 106.8,
        'accuracy' => 10,
    ];

    $this->actingAs($user)
        ->post('/employee/attendance/check-in', $payload)
        ->assertRedirect('/employee/attendance')
        ->assertSessionHas('success');

    $record = EmployeeAttendanceRecord::first();
    expect($record)->not->toBeNull()
        ->and($record->check_in_at)->not->toBeNull()
        ->and($record->location_status)->toBe('inside_radius');

    $this->actingAs($user)
        ->post('/employee/attendance/check-out', [
            'photo' => UploadedFile::fake()->image('pulang.jpg', 640, 480),
            'latitude' => -6.2,
            'longitude' => 106.8,
            'accuracy' => 12,
        ])
        ->assertRedirect('/employee/attendance')
        ->assertSessionHas('success');

    expect($record->fresh()->check_out_at)->not->toBeNull();
});

it('rejects self-service check-in outside the office radius', function () {
    Storage::fake('public');
    $user = employeeTestSetup();

    EmployeeAttendanceLocation::create([
        'name' => 'Kantor Pusat',
        'code' => 'KTR',
        'latitude' => -6.2,
        'longitude' => 106.8,
        'radius_meters' => 100,
        'is_active' => true,
    ]);

    $this->actingAs($user)
        ->post('/employee/attendance/check-in', [
            'photo' => UploadedFile::fake()->image('absen.jpg', 640, 480),
            'latitude' => -6.9,
            'longitude' => 107.6,
            'accuracy' => 10,
        ])
        ->assertSessionHasErrors('latitude');

    expect(EmployeeAttendanceRecord::exists())->toBeFalse();
});
