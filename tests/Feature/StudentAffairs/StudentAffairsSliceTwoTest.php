<?php

use App\Enums\AchievementCategory;
use App\Enums\AchievementLevel;
use App\Enums\AchievementRank;
use App\Enums\AchievementStatus;
use App\Enums\EventRegistrationStatus;
use App\Enums\FacilityBookingStatus;
use App\Enums\MemberPosition;
use App\Enums\OrganizationType;
use App\Enums\PointEntryCategory;
use App\Enums\StudentEventCategory;
use App\Enums\StudentEventStatus;
use App\Models\Academic\Faculty;
use App\Models\Academic\StudentProfile;
use App\Models\Academic\StudyProgram;
use App\Models\Campus\Building;
use App\Models\Campus\Room;
use App\Models\Campus\RoomReservation;
use App\Models\Settings\System;
use App\Models\StudentAffairs\StudentAchievement;
use App\Models\StudentAffairs\StudentEvent;
use App\Models\StudentAffairs\StudentEventRegistration;
use App\Models\StudentAffairs\StudentOrganization;
use App\Models\StudentAffairs\StudentOrganizationMember;
use App\Models\StudentAffairs\StudentPointEntry;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Spatie\Permission\Models\Permission as SpatiePermission;
use Spatie\Permission\Models\Role as SpatieRole;

uses(RefreshDatabase::class);

/**
 * @return array<string, mixed>
 */
function studentAffairsSliceTwoSetup(): array
{
    System::create([
        'app_name' => 'NexaCampus Test',
        'app_version' => '1.0.0',
        'app_description' => 'Test',
        'app_url' => 'http://localhost',
        'app_email' => 'test@localhost',
    ])->forceFill(['is_installed' => true])->save();

    foreach (['admin', 'student', 'viewer'] as $role) {
        SpatieRole::firstOrCreate(['name' => $role, 'guard_name' => 'web']);
    }

    foreach (studentAffairsSliceTwoPermissions() as $permission) {
        SpatiePermission::firstOrCreate(['name' => $permission, 'guard_name' => 'web']);
    }

    SpatieRole::where('name', 'admin')->firstOrFail()
        ->givePermissionTo(studentAffairsSliceTwoPermissions());

    $admin = User::factory()->create([
        'first_name' => 'Ayu',
        'last_name' => 'Kemahasiswaan',
        'username' => 'ayu.kemahasiswaan.slice2',
    ]);
    $admin->assignRole('admin');

    $studentUser = User::factory()->create([
        'first_name' => 'Rania',
        'last_name' => 'Putri',
        'username' => 'rania.putri.slice2',
    ]);
    $studentUser->assignRole('student');

    $otherStudent = User::factory()->create([
        'first_name' => 'Nadia',
        'last_name' => 'Senior',
        'username' => 'nadia.senior.slice2',
    ]);
    $otherStudent->assignRole('student');

    $viewer = User::factory()->create([
        'first_name' => 'Vera',
        'last_name' => 'Pengamat',
        'username' => 'vera.pengamat.slice2',
    ]);
    $viewer->assignRole('viewer');

    $faculty = Faculty::create(['name' => 'Fakultas Sains dan Teknologi', 'short_name' => 'FST', 'code' => 'FST', 'is_active' => true]);
    $program = StudyProgram::create([
        'faculty_id' => $faculty->id,
        'name' => 'Teknik Informatika',
        'short_name' => 'Informatika',
        'code' => 'TI',
        'degree' => 'S1',
        'is_active' => true,
    ]);

    foreach ([[$studentUser, '2020010001'], [$otherStudent, '2020010002']] as [$user, $nim]) {
        StudentProfile::create([
            'user_id' => $user->id,
            'study_program_id' => $program->id,
            'nim' => $nim,
            'entry_year' => 2020,
            'academic_status' => 'Aktif',
            'current_semester' => 6,
            'is_active' => true,
        ]);
    }

    $organization = StudentOrganization::create([
        'name' => 'Himpunan Mahasiswa Teknik Informatika',
        'type' => OrganizationType::HIMA->value,
        'faculty_id' => $faculty->id,
        'study_program_id' => $program->id,
        'description' => 'Wadah pengembangan keilmuan mahasiswa Informatika.',
        'is_active' => true,
    ]);

    StudentOrganizationMember::create([
        'student_organization_id' => $organization->id,
        'user_id' => $studentUser->id,
        'position' => MemberPosition::CHAIRMAN->value,
        'period' => '2026/2027',
        'joined_at' => '2026-08-01',
        'is_active' => true,
    ]);

    $building = Building::create([
        'code' => 'GDG-A',
        'name' => 'Gedung A',
        'address' => 'Jl. Kampus No. 1',
        'floor_count' => 4,
        'is_active' => true,
    ]);

    $room = Room::create([
        'building_id' => $building->id,
        'name' => 'Ruang 101',
        'code' => 'A-101',
        'floor' => '1',
        'capacity' => 40,
        'type' => 'Classroom',
        'is_active' => true,
    ]);

    $smallRoom = Room::create([
        'building_id' => $building->id,
        'name' => 'Ruang Diskusi 301',
        'code' => 'B-301',
        'floor' => '3',
        'capacity' => 10,
        'type' => 'Classroom',
        'is_active' => true,
    ]);

    return compact(
        'admin', 'studentUser', 'otherStudent', 'viewer',
        'faculty', 'program', 'organization', 'building', 'room', 'smallRoom',
    );
}

/**
 * @return array<int, string>
 */
function studentAffairsSliceTwoPermissions(): array
{
    $permissions = [];

    $map = [
        'student-event' => ['viewAny', 'create', 'update', 'delete', 'view', 'publish'],
        'student-point' => ['viewAny', 'create', 'delete'],
        'student-facility-booking' => ['viewAny', 'update', 'delete', 'approve', 'view'],
    ];

    foreach ($map as $resource => $actions) {
        foreach ($actions as $action) {
            $permissions[] = "{$resource}.{$action}";
        }
    }

    return $permissions;
}

function actingSliceTwoAdmin(User $admin)
{
    return test()->actingAs($admin)->withSession(['active_role' => 'admin']);
}

function actingSliceTwoStudent(User $student)
{
    return test()->actingAs($student)->withSession(['active_role' => 'student']);
}

/**
 * @param  array<string, mixed>  $setup
 * @param  array<string, mixed>  $overrides
 */
function makeSliceTwoEvent(array $setup, array $overrides = []): StudentEvent
{
    return StudentEvent::create(array_merge([
        'title' => 'Seminar Uji '.fake()->unique()->numerify('###'),
        'slug' => 'seminar-uji-'.fake()->unique()->numerify('###'),
        'category' => StudentEventCategory::SEMINAR->value,
        'description' => 'Event uji kemahasiswaan.',
        'student_organization_id' => $setup['organization']->id,
        'organizer' => 'BEM Uji',
        'location' => 'Aula Uji',
        'starts_at' => now()->addDays(7),
        'ends_at' => now()->addDays(7)->addHours(3),
        'registration_deadline' => now()->addDays(5),
        'quota' => 50,
        'points' => 15,
        'status' => StudentEventStatus::PUBLISHED->value,
        'is_featured' => false,
    ], $overrides));
}

/**
 * @param  array<string, mixed>  $setup
 * @param  array<string, mixed>  $overrides
 */
function makeSliceTwoBooking(array $setup, array $overrides = []): RoomReservation
{
    return RoomReservation::create(array_merge([
        'reservation_number' => 'RES-UJI-'.fake()->unique()->numerify('#####'),
        'room_id' => $setup['room']->id,
        'user_id' => $setup['studentUser']->id,
        'student_organization_id' => $setup['organization']->id,
        'title' => 'Rapat Uji '.fake()->unique()->numerify('###'),
        'purpose' => 'Kegiatan uji.',
        'reservation_date' => now()->addDays(2)->toDateString(),
        'start_time' => '09:00',
        'end_time' => '11:00',
        'attendee_count' => 20,
        'status' => FacilityBookingStatus::PENDING->value,
    ], $overrides));
}

/**
 * @param  array<string, mixed>  $setup
 */
function makeSliceTwoPointEntry(array $setup, User $user, int $points, string $description = 'Poin uji'): StudentPointEntry
{
    return StudentPointEntry::create([
        'user_id' => $user->id,
        'points' => $points,
        'category' => PointEntryCategory::MANUAL->value,
        'description' => $description,
        'awarded_at' => now()->toDateString(),
        'awarded_by' => $setup['admin']->id,
    ]);
}

it('menampilkan daftar event mahasiswa admin dengan statistik', function () {
    $setup = studentAffairsSliceTwoSetup();
    makeSliceTwoEvent($setup);

    actingSliceTwoAdmin($setup['admin'])
        ->get(route('admin.student-affairs.events.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Admin/StudentAffairs/Events/Index')
            ->where('can.create', true)
            ->where('can.publish', true)
            ->has('data.rows', 1)
            ->where('data.rows.0.categoryLabel', 'Seminar')
            ->where('data.rows.0.statusLabel', 'Dipublikasikan')
            ->where('stats.total', 1)
            ->where('stats.published', 1));
});

it('membuat dan memperbarui event serta mempublikasikannya', function () {
    $setup = studentAffairsSliceTwoSetup();

    actingSliceTwoAdmin($setup['admin'])
        ->post(route('admin.student-affairs.events.store'), [
            'title' => 'Workshop Kepemimpinan Mahasiswa',
            'category' => StudentEventCategory::WORKSHOP->value,
            'student_organization_id' => $setup['organization']->id,
            'organizer' => 'HIMA TI',
            'location' => 'Lab 203',
            'starts_at' => now()->addDays(10)->format('Y-m-d H:i:s'),
            'ends_at' => now()->addDays(10)->addHours(2)->format('Y-m-d H:i:s'),
            'registration_deadline' => now()->addDays(8)->format('Y-m-d H:i:s'),
            'quota' => 40,
            'points' => 10,
            'status' => StudentEventStatus::DRAFT->value,
            'is_featured' => false,
            'description' => 'Workshop uji.',
        ])
        ->assertRedirect(route('admin.student-affairs.events.index'));

    $event = StudentEvent::where('title', 'Workshop Kepemimpinan Mahasiswa')->firstOrFail();
    expect($event->slug)->toBe('workshop-kepemimpinan-mahasiswa');

    actingSliceTwoAdmin($setup['admin'])
        ->put(route('admin.student-affairs.events.update', $event->id), [
            'title' => 'Workshop Kepemimpinan Mahasiswa',
            'category' => StudentEventCategory::WORKSHOP->value,
            'student_organization_id' => $setup['organization']->id,
            'organizer' => 'HIMA TI',
            'location' => 'Lab 203',
            'starts_at' => now()->addDays(10)->format('Y-m-d H:i:s'),
            'ends_at' => now()->addDays(10)->addHours(2)->format('Y-m-d H:i:s'),
            'registration_deadline' => now()->addDays(8)->format('Y-m-d H:i:s'),
            'quota' => 45,
            'points' => 12,
            'status' => StudentEventStatus::DRAFT->value,
            'is_featured' => true,
            'description' => 'Workshop uji diperbarui.',
        ])
        ->assertRedirect(route('admin.student-affairs.events.index'));

    expect($event->refresh())
        ->quota->toBe(45)
        ->points->toBe(12)
        ->is_featured->toBeTrue();

    actingSliceTwoAdmin($setup['admin'])
        ->post(route('admin.student-affairs.events.publish', $event->id))
        ->assertRedirect();

    expect($event->refresh()->status)->toBe(StudentEventStatus::PUBLISHED);
});

it('mencatat kehadiran event dan memberi poin otomatis secara idempoten', function () {
    $setup = studentAffairsSliceTwoSetup();
    $event = makeSliceTwoEvent($setup, ['points' => 15]);

    $registration = StudentEventRegistration::create([
        'student_event_id' => $event->id,
        'user_id' => $setup['studentUser']->id,
        'status' => EventRegistrationStatus::REGISTERED->value,
        'registered_at' => now(),
    ]);

    actingSliceTwoAdmin($setup['admin'])
        ->post(route('admin.student-affairs.events.attendance', [$event->id, $registration->id]), [
            'status' => EventRegistrationStatus::ATTENDED->value,
        ])
        ->assertRedirect();

    expect($registration->refresh()->points_awarded)->toBe(15);
    expect(StudentPointEntry::where('user_id', $setup['studentUser']->id)->sum('points'))->toBe(15);

    actingSliceTwoAdmin($setup['admin'])
        ->post(route('admin.student-affairs.events.attendance', [$event->id, $registration->id]), [
            'status' => EventRegistrationStatus::ATTENDED->value,
        ])
        ->assertRedirect();

    expect(StudentPointEntry::where('user_id', $setup['studentUser']->id)->count())->toBe(1);

    actingSliceTwoAdmin($setup['admin'])
        ->post(route('admin.student-affairs.events.attendance', [$event->id, $registration->id]), [
            'status' => EventRegistrationStatus::REGISTERED->value,
        ])
        ->assertRedirect();

    expect($registration->refresh()->points_awarded)->toBe(0);
    expect(StudentPointEntry::where('user_id', $setup['studentUser']->id)->sum('points'))->toBe(0);
});

it('menampilkan leaderboard dan memberi poin manual dari admin', function () {
    $setup = studentAffairsSliceTwoSetup();
    makeSliceTwoPointEntry($setup, $setup['studentUser'], 30, 'Kontribusi organisasi');
    makeSliceTwoPointEntry($setup, $setup['otherStudent'], 10, 'Kontribusi event');

    actingSliceTwoAdmin($setup['admin'])
        ->get(route('admin.student-affairs.points.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Admin/StudentAffairs/Points/Index')
            ->where('can.create', true)
            ->has('leaderboard', 2)
            ->where('leaderboard.0.total', 30)
            ->where('leaderboard.1.total', 10)
            ->where('stats.totalPoints', 40)
            ->has('data.rows', 2));

    actingSliceTwoAdmin($setup['admin'])
        ->post(route('admin.student-affairs.points.store'), [
            'user_id' => $setup['otherStudent']->id,
            'points' => 25,
            'category' => PointEntryCategory::MANUAL->value,
            'description' => 'Penyesuaian poin manual',
            'awarded_at' => now()->toDateString(),
        ])
        ->assertRedirect();

    expect(StudentPointEntry::where('user_id', $setup['otherStudent']->id)->sum('points'))->toBe(35);
});

it('hanya menampilkan booking organisasi pada daftar booking fasilitas admin', function () {
    $setup = studentAffairsSliceTwoSetup();
    $booking = makeSliceTwoBooking($setup);

    RoomReservation::create([
        'reservation_number' => 'RES-BIASa-001',
        'room_id' => $setup['room']->id,
        'user_id' => $setup['studentUser']->id,
        'title' => 'Reservasi Umum',
        'reservation_date' => now()->addDays(4)->toDateString(),
        'start_time' => '13:00',
        'end_time' => '15:00',
        'attendee_count' => 10,
        'status' => 'pending',
    ]);

    actingSliceTwoAdmin($setup['admin'])
        ->get(route('admin.student-affairs.facility-bookings.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Admin/StudentAffairs/FacilityBookings/Index')
            ->where('can.approve', true)
            ->has('data.rows', 1)
            ->where('data.rows.0.id', $booking->id)
            ->where('data.rows.0.organization', $setup['organization']->name)
            ->where('stats.total', 1));
});

it('menyetujui booking organisasi dan menolak bila bentrok jadwal', function () {
    $setup = studentAffairsSliceTwoSetup();
    $booking = makeSliceTwoBooking($setup);

    RoomReservation::create([
        'reservation_number' => 'RES-KONFLIK-001',
        'room_id' => $setup['room']->id,
        'user_id' => $setup['admin']->id,
        'title' => 'Kegiatan Disetujui',
        'reservation_date' => $booking->reservation_date->format('Y-m-d'),
        'start_time' => '10:00',
        'end_time' => '12:00',
        'attendee_count' => 10,
        'status' => 'approved',
    ]);

    actingSliceTwoAdmin($setup['admin'])
        ->post(route('admin.student-affairs.facility-bookings.approve', $booking->id))
        ->assertRedirect()
        ->assertSessionHas('error');

    expect($booking->refresh()->status)->toBe(FacilityBookingStatus::PENDING->value);

    $booking->update(['start_time' => '13:00', 'end_time' => '15:00']);

    actingSliceTwoAdmin($setup['admin'])
        ->post(route('admin.student-affairs.facility-bookings.approve', $booking->id))
        ->assertRedirect()
        ->assertSessionHas('success');

    expect($booking->refresh())
        ->status->toBe(FacilityBookingStatus::APPROVED->value)
        ->approved_by->toBe($setup['admin']->id);
});

it('menolak booking wajib alasan dan membatalkan booking pending', function () {
    $setup = studentAffairsSliceTwoSetup();
    $booking = makeSliceTwoBooking($setup);

    actingSliceTwoAdmin($setup['admin'])
        ->post(route('admin.student-affairs.facility-bookings.reject', $booking->id), [])
        ->assertSessionHasErrors('rejection_reason');

    actingSliceTwoAdmin($setup['admin'])
        ->post(route('admin.student-affairs.facility-bookings.reject', $booking->id), [
            'rejection_reason' => 'Ruangan dipakai kegiatan akademik.',
        ])
        ->assertRedirect();

    expect($booking->refresh())
        ->status->toBe(FacilityBookingStatus::REJECTED->value)
        ->rejection_reason->toBe('Ruangan dipakai kegiatan akademik.');

    $second = makeSliceTwoBooking($setup, ['title' => 'Booking Batal']);

    actingSliceTwoAdmin($setup['admin'])
        ->post(route('admin.student-affairs.facility-bookings.cancel', $second->id))
        ->assertRedirect();

    expect($second->refresh()->status)->toBe(FacilityBookingStatus::CANCELLED->value);
});

it('menampilkan event yang dipublikasikan di portal mahasiswa', function () {
    $setup = studentAffairsSliceTwoSetup();
    $published = makeSliceTwoEvent($setup, ['title' => 'Event Terbuka']);
    makeSliceTwoEvent($setup, ['title' => 'Event Draf', 'slug' => 'event-draf', 'status' => StudentEventStatus::DRAFT->value]);

    actingSliceTwoStudent($setup['studentUser'])
        ->get(route('student.student-affairs.events'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Student/StudentAffairs/Events')
            ->has('events', 1)
            ->where('events.0.id', $published->id)
            ->where('events.0.canRegister', true)
            ->where('stats.open', 1));
});

it('mendaftar dan membatalkan pendaftaran event dari portal mahasiswa', function () {
    $setup = studentAffairsSliceTwoSetup();
    $event = makeSliceTwoEvent($setup);

    actingSliceTwoStudent($setup['studentUser'])
        ->post(route('student.student-affairs.events.register', $event->id))
        ->assertRedirect();

    expect(StudentEventRegistration::where('student_event_id', $event->id)
        ->where('user_id', $setup['studentUser']->id)
        ->where('status', EventRegistrationStatus::REGISTERED->value)
        ->exists())->toBeTrue();

    actingSliceTwoStudent($setup['studentUser'])
        ->post(route('student.student-affairs.events.register', $event->id))
        ->assertRedirect();

    expect(StudentEventRegistration::where('student_event_id', $event->id)
        ->where('user_id', $setup['studentUser']->id)
        ->count())->toBe(1);

    actingSliceTwoStudent($setup['studentUser'])
        ->post(route('student.student-affairs.events.cancel-registration', $event->id))
        ->assertRedirect();

    expect(StudentEventRegistration::where('student_event_id', $event->id)
        ->where('user_id', $setup['studentUser']->id)
        ->first()->status)->toBe(EventRegistrationStatus::CANCELLED);
});

it('menolak pendaftaran event yang sudah lewat batas', function () {
    $setup = studentAffairsSliceTwoSetup();
    $event = makeSliceTwoEvent($setup, [
        'registration_deadline' => now()->subDay(),
    ]);

    actingSliceTwoStudent($setup['studentUser'])
        ->post(route('student.student-affairs.events.register', $event->id))
        ->assertRedirect()
        ->assertSessionHas('error');

    expect(StudentEventRegistration::where('student_event_id', $event->id)->count())->toBe(0);
});

it('menampilkan poin dan peringkat mahasiswa di portal', function () {
    $setup = studentAffairsSliceTwoSetup();
    makeSliceTwoPointEntry($setup, $setup['studentUser'], 30, 'Kontribusi A');
    makeSliceTwoPointEntry($setup, $setup['otherStudent'], 10, 'Kontribusi B');

    actingSliceTwoStudent($setup['studentUser'])
        ->get(route('student.student-affairs.leaderboard'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Student/StudentAffairs/Leaderboard')
            ->where('stats.myPoints', 30)
            ->where('stats.myRank', 1)
            ->has('leaderboard', 2)
            ->where('leaderboard.0.isMe', true)
            ->has('entries', 1));
});

it('mengajukan booking fasilitas sebagai anggota organisasi', function () {
    $setup = studentAffairsSliceTwoSetup();

    actingSliceTwoStudent($setup['studentUser'])
        ->post(route('student.student-affairs.bookings.store'), [
            'student_organization_id' => $setup['organization']->id,
            'room_id' => $setup['room']->id,
            'title' => 'Rapat Pengurus',
            'purpose' => 'Koordinasi program kerja.',
            'reservation_date' => now()->addDays(3)->toDateString(),
            'start_time' => '09:00',
            'end_time' => '11:00',
            'attendee_count' => 20,
        ])
        ->assertRedirect();

    $booking = RoomReservation::where('title', 'Rapat Pengurus')->firstOrFail();

    expect($booking)
        ->student_organization_id->toBe($setup['organization']->id)
        ->user_id->toBe($setup['studentUser']->id)
        ->status->toBe(FacilityBookingStatus::PENDING->value);
});

it('menolak booking dari non-anggota, melebihi kapasitas, atau bentrok jadwal', function () {
    $setup = studentAffairsSliceTwoSetup();

    actingSliceTwoStudent($setup['otherStudent'])
        ->post(route('student.student-affairs.bookings.store'), [
            'student_organization_id' => $setup['organization']->id,
            'room_id' => $setup['room']->id,
            'title' => 'Booking Non Anggota',
            'reservation_date' => now()->addDays(3)->toDateString(),
            'start_time' => '09:00',
            'end_time' => '11:00',
            'attendee_count' => 20,
        ])
        ->assertSessionHasErrors('student_organization_id');

    actingSliceTwoStudent($setup['studentUser'])
        ->post(route('student.student-affairs.bookings.store'), [
            'student_organization_id' => $setup['organization']->id,
            'room_id' => $setup['smallRoom']->id,
            'title' => 'Booking Kebesaran',
            'reservation_date' => now()->addDays(3)->toDateString(),
            'start_time' => '09:00',
            'end_time' => '11:00',
            'attendee_count' => 30,
        ])
        ->assertSessionHasErrors('attendee_count');

    RoomReservation::create([
        'reservation_number' => 'RES-BENTROK-001',
        'room_id' => $setup['room']->id,
        'user_id' => $setup['admin']->id,
        'title' => 'Kegiatan Disetujui',
        'reservation_date' => now()->addDays(3)->toDateString(),
        'start_time' => '10:00',
        'end_time' => '12:00',
        'attendee_count' => 10,
        'status' => 'approved',
    ]);

    actingSliceTwoStudent($setup['studentUser'])
        ->post(route('student.student-affairs.bookings.store'), [
            'student_organization_id' => $setup['organization']->id,
            'room_id' => $setup['room']->id,
            'title' => 'Booking Bentrok',
            'reservation_date' => now()->addDays(3)->toDateString(),
            'start_time' => '09:00',
            'end_time' => '11:00',
            'attendee_count' => 20,
        ])
        ->assertSessionHasErrors('start_time');

    expect(RoomReservation::whereIn('title', ['Booking Non Anggota', 'Booking Kebesaran', 'Booking Bentrok'])->count())->toBe(0);
});

it('membatalkan booking fasilitas milik sendiri dari portal mahasiswa', function () {
    $setup = studentAffairsSliceTwoSetup();
    $booking = makeSliceTwoBooking($setup);

    actingSliceTwoStudent($setup['studentUser'])
        ->post(route('student.student-affairs.bookings.cancel', $booking->id))
        ->assertRedirect();

    expect($booking->refresh()->status)->toBe(FacilityBookingStatus::CANCELLED->value);
});

it('menolak akses admin kemahasiswaan tanpa permission', function () {
    $setup = studentAffairsSliceTwoSetup();

    actingSliceTwoAdmin($setup['viewer'])
        ->get(route('admin.student-affairs.events.index'))
        ->assertForbidden();

    actingSliceTwoAdmin($setup['viewer'])
        ->get(route('admin.student-affairs.points.index'))
        ->assertForbidden();

    actingSliceTwoAdmin($setup['viewer'])
        ->get(route('admin.student-affairs.facility-bookings.index'))
        ->assertForbidden();
});
