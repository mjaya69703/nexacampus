<?php

use App\Enums\EventRegistrationStatus;
use App\Enums\MemberPosition;
use App\Enums\OrganizationType;
use App\Enums\StudentEventCategory;
use App\Enums\StudentEventStatus;
use App\Models\Academic\Faculty;
use App\Models\Academic\StudentProfile;
use App\Models\Academic\StudyProgram;
use App\Models\Settings\System;
use App\Models\StudentAffairs\StudentEvent;
use App\Models\StudentAffairs\StudentEventRegistration;
use App\Models\StudentAffairs\StudentOrganization;
use App\Models\StudentAffairs\StudentOrganizationMember;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Spatie\Permission\Models\Role as SpatieRole;

uses(RefreshDatabase::class);

/**
 * @return array<string, mixed>
 */
function publicAgendaSetup(): array
{
    System::create([
        'app_name' => 'NexaCampus Test',
        'app_version' => '1.0.0',
        'app_description' => 'Test',
        'app_url' => 'http://localhost',
        'app_email' => 'test@localhost',
    ])->forceFill(['is_installed' => true])->save();

    foreach (['student'] as $role) {
        SpatieRole::firstOrCreate(['name' => $role, 'guard_name' => 'web']);
    }

    $student = User::factory()->create([
        'first_name' => 'Rania',
        'last_name' => 'Putri',
        'username' => 'rania.agenda.public',
    ]);
    $student->assignRole('student');

    $otherStudent = User::factory()->create([
        'first_name' => 'Nadia',
        'last_name' => 'Senior',
        'username' => 'nadia.agenda.public',
    ]);
    $otherStudent->assignRole('student');

    $faculty = Faculty::create([
        'name' => 'Fakultas Sains dan Teknologi',
        'short_name' => 'FST',
        'code' => 'FST',
        'is_active' => true,
    ]);

    $program = StudyProgram::create([
        'faculty_id' => $faculty->id,
        'name' => 'Teknik Informatika',
        'short_name' => 'Informatika',
        'code' => 'TI',
        'degree' => 'S1',
        'is_active' => true,
    ]);

    foreach ([[$student, '2020010101'], [$otherStudent, '2020010102']] as [$user, $nim]) {
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
        'user_id' => $student->id,
        'position' => MemberPosition::CHAIRMAN->value,
        'period' => '2026/2027',
        'joined_at' => '2026-08-01',
        'is_active' => true,
    ]);

    return compact('student', 'otherStudent', 'faculty', 'program', 'organization');
}

/**
 * @param  array<string, mixed>  $setup
 * @param  array<string, mixed>  $overrides
 */
function makePublicAgendaEvent(array $setup, array $overrides = []): StudentEvent
{
    return StudentEvent::create(array_merge([
        'title' => 'Seminar Agenda '.fake()->unique()->numerify('###'),
        'slug' => 'seminar-agenda-'.fake()->unique()->numerify('###'),
        'category' => StudentEventCategory::SEMINAR->value,
        'description' => 'Deskripsi kegiatan agenda publik.',
        'student_organization_id' => $setup['organization']->id,
        'organizer' => 'BEM Uji',
        'location' => 'Aula Utama',
        'starts_at' => now()->addDays(7),
        'ends_at' => now()->addDays(7)->addHours(3),
        'registration_deadline' => now()->addDays(5),
        'quota' => 50,
        'points' => 15,
        'status' => StudentEventStatus::PUBLISHED->value,
        'is_featured' => false,
    ], $overrides));
}

it('menampilkan agenda publik hanya untuk event published atau ongoing yang akan datang', function () {
    $setup = publicAgendaSetup();

    $upcoming = makePublicAgendaEvent($setup, [
        'title' => 'Seminar Nasional Inovasi',
        'slug' => 'seminar-nasional-inovasi',
        'is_featured' => true,
    ]);
    makePublicAgendaEvent($setup, [
        'title' => 'Workshop Desain Produk',
        'slug' => 'workshop-desain-produk',
        'category' => StudentEventCategory::WORKSHOP->value,
    ]);
    makePublicAgendaEvent($setup, [
        'title' => 'Futsal Antar Prodi',
        'slug' => 'futsal-antar-prodi',
        'category' => StudentEventCategory::SPORTS->value,
        'status' => StudentEventStatus::ONGOING->value,
        'starts_at' => now()->subHours(2),
        'ends_at' => now()->addHours(2),
        'registration_deadline' => now()->subDay(),
    ]);
    makePublicAgendaEvent($setup, [
        'title' => 'Event Lampau',
        'slug' => 'event-lampau',
        'starts_at' => now()->subDays(10),
        'ends_at' => now()->subDays(10)->addHours(3),
    ]);
    makePublicAgendaEvent($setup, [
        'title' => 'Event Draf',
        'slug' => 'event-draf',
        'status' => StudentEventStatus::DRAFT->value,
    ]);
    makePublicAgendaEvent($setup, [
        'title' => 'Event Dibatalkan',
        'slug' => 'event-dibatalkan',
        'status' => StudentEventStatus::CANCELLED->value,
    ]);
    makePublicAgendaEvent($setup, [
        'title' => 'Event Selesai',
        'slug' => 'event-selesai',
        'status' => StudentEventStatus::COMPLETED->value,
    ]);

    test()->get(route('root.kemahasiswaan.agenda'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Home/Kemahasiswaan/Agenda')
            ->has('events', 3)
            ->where('events.0.title', 'Futsal Antar Prodi')
            ->where('events.1.title', 'Seminar Nasional Inovasi')
            ->where('events.1.slug', 'seminar-nasional-inovasi')
            ->where('events.1.isFeatured', true)
            ->where('events.1.registrationOpen', true)
            ->where('events.1.detailUrl', route('root.kemahasiswaan.agenda.show', 'seminar-nasional-inovasi'))
            ->where('stats.events', 3)
            ->where('stats.upcoming', 2)
            ->has('categories'));
});

it('menghitung statistik peserta dan penyelenggara agenda dari pendaftaran aktif', function () {
    $setup = publicAgendaSetup();

    $seminar = makePublicAgendaEvent($setup, [
        'title' => 'Seminar Statistik',
        'slug' => 'seminar-statistik',
        'quota' => 10,
    ]);

    makePublicAgendaEvent($setup, [
        'title' => 'Workshop Statistik',
        'slug' => 'workshop-statistik',
        'category' => StudentEventCategory::WORKSHOP->value,
        'organizer' => 'BEM Uji',
        'student_organization_id' => null,
    ]);

    foreach ([
        [$setup['student'], EventRegistrationStatus::REGISTERED->value],
        [$setup['otherStudent'], EventRegistrationStatus::ATTENDED->value],
        [User::factory()->create(['username' => 'batal.agenda.public']), EventRegistrationStatus::CANCELLED->value],
    ] as [$user, $status]) {
        StudentEventRegistration::create([
            'student_event_id' => $seminar->id,
            'user_id' => $user->id,
            'status' => $status,
            'registered_at' => now(),
        ]);
    }

    test()->get(route('root.kemahasiswaan.agenda'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Home/Kemahasiswaan/Agenda')
            ->has('events', 2)
            ->where('events.0.registeredCount', 2)
            ->where('events.0.seatsLeft', 8)
            ->where('stats.participants', 2)
            ->where('stats.organizations', 2));
});

it('menampilkan detail agenda untuk event published dan completed', function () {
    $setup = publicAgendaSetup();

    makePublicAgendaEvent($setup, [
        'title' => 'Seminar Detail Publik',
        'slug' => 'seminar-detail-publik',
    ]);
    makePublicAgendaEvent($setup, [
        'title' => 'Event Selesai Detail',
        'slug' => 'event-selesai-detail',
        'status' => StudentEventStatus::COMPLETED->value,
        'starts_at' => now()->subDays(3),
        'ends_at' => now()->subDays(3)->addHours(3),
    ]);

    test()->get(route('root.kemahasiswaan.agenda.show', 'seminar-detail-publik'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Home/Kemahasiswaan/AgendaDetail')
            ->where('event.title', 'Seminar Detail Publik')
            ->where('event.registrationOpen', true)
            ->where('event.organization', 'Himpunan Mahasiswa Teknik Informatika')
            ->where('ctaLabel', 'Masuk untuk Mendaftar')
            ->where('ctaUrl', route('auth.signin-index'))
            ->has('related'));

    test()->get(route('root.kemahasiswaan.agenda.show', 'event-selesai-detail'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Home/Kemahasiswaan/AgendaDetail')
            ->where('event.title', 'Event Selesai Detail')
            ->where('event.status', StudentEventStatus::COMPLETED->value)
            ->where('event.registrationOpen', false));
});

it('mengembalikan 404 untuk detail event draf, dibatalkan, atau tidak dikenal', function () {
    $setup = publicAgendaSetup();

    makePublicAgendaEvent($setup, [
        'slug' => 'event-draf-detail',
        'status' => StudentEventStatus::DRAFT->value,
    ]);
    makePublicAgendaEvent($setup, [
        'slug' => 'event-batal-detail',
        'status' => StudentEventStatus::CANCELLED->value,
    ]);

    test()->get(route('root.kemahasiswaan.agenda.show', 'event-draf-detail'))->assertNotFound();
    test()->get(route('root.kemahasiswaan.agenda.show', 'event-batal-detail'))->assertNotFound();
    test()->get(route('root.kemahasiswaan.agenda.show', 'slug-tidak-ada'))->assertNotFound();
});

it('mengarahkan mahasiswa terautentikasi ke portal event pada detail agenda', function () {
    $setup = publicAgendaSetup();

    makePublicAgendaEvent($setup, [
        'title' => 'Seminar Portal Mahasiswa',
        'slug' => 'seminar-portal-mahasiswa',
    ]);

    test()->actingAs($setup['student'])
        ->withSession(['active_role' => 'student'])
        ->get(route('root.kemahasiswaan.agenda.show', 'seminar-portal-mahasiswa'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Home/Kemahasiswaan/AgendaDetail')
            ->where('ctaLabel', 'Buka Portal Event')
            ->where('ctaUrl', route('student.student-affairs.events')));
});

it('menampilkan kegiatan serupa pada detail agenda berdasarkan kategori', function () {
    $setup = publicAgendaSetup();

    makePublicAgendaEvent($setup, [
        'title' => 'Seminar Utama',
        'slug' => 'seminar-utama',
    ]);
    makePublicAgendaEvent($setup, [
        'title' => 'Seminar Serupa Satu',
        'slug' => 'seminar-serupa-satu',
    ]);
    makePublicAgendaEvent($setup, [
        'title' => 'Seminar Serupa Dua',
        'slug' => 'seminar-serupa-dua',
    ]);
    makePublicAgendaEvent($setup, [
        'title' => 'Workshop Beda Kategori',
        'slug' => 'workshop-beda-kategori',
        'category' => StudentEventCategory::WORKSHOP->value,
    ]);

    test()->get(route('root.kemahasiswaan.agenda.show', 'seminar-utama'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Home/Kemahasiswaan/AgendaDetail')
            ->has('related', 2)
            ->where('related.0.slug', 'seminar-serupa-satu')
            ->where('related.1.slug', 'seminar-serupa-dua'));
});
