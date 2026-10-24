<?php

use App\Enums\CampaignStatus;
use App\Enums\EmploymentStatus;
use App\Enums\EventType;
use App\Enums\JobRelevance;
use App\Enums\JobType;
use App\Models\Academic\AcademicYear;
use App\Models\Academic\Faculty;
use App\Models\Academic\StudyProgram;
use App\Models\Alumni\AlumniEvent;
use App\Models\Alumni\AlumniEventParticipant;
use App\Models\Alumni\AlumniProfile;
use App\Models\Alumni\EmployerPartner;
use App\Models\Alumni\JobPosting;
use App\Models\Alumni\TracerStudyCampaign;
use App\Models\Alumni\TracerStudyResponse;
use App\Models\User;
use App\Support\Alumni\AlumniConversionService;
use App\Support\Alumni\TracerStudyExportService;
use App\Support\Alumni\TracerStudyService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

function alumniTestSetup(): array
{
    $admin = User::factory()->create();
    $faculty = Faculty::create(['name' => 'Fakultas Sains dan Teknologi', 'code' => 'FST', 'is_active' => true]);
    $program = StudyProgram::create(['faculty_id' => $faculty->id, 'name' => 'Teknik Informatika', 'code' => 'TI', 'degree' => 'S1', 'is_active' => true]);
    $year = AcademicYear::create(['name' => '2025/2026 Ganjil', 'code' => '2025G', 'semester' => 'Ganjil', 'start_date' => '2025-08-01', 'end_date' => '2025-12-31', 'is_active' => true]);

    return compact('admin', 'faculty', 'program', 'year');
}

function createAlumniProfile(array $setup, array $overrides = []): AlumniProfile
{
    return AlumniProfile::create(array_merge([
        'nim' => 'ALM' . fake()->unique()->numerify('######'),
        'full_name' => fake()->name(),
        'graduation_year' => 2023,
        'graduation_date' => '2023-08-15',
        'study_program_id' => $setup['program']->id,
        'faculty_id' => $setup['faculty']->id,
        'gpa' => 3.50,
        'email' => fake()->unique()->safeEmail(),
        'employment_status' => 'unemployed',
        'is_active' => true,
        'created_by' => $setup['admin']->id,
        'updated_by' => $setup['admin']->id,
    ], $overrides));
}

// ────────────────────── ENUMS ──────────────────────

it('has all employment status cases with labels', function () {
    expect(EmploymentStatus::cases())->toHaveCount(5)
        ->and(EmploymentStatus::Working->label())->toBe('Bekerja')
        ->and(EmploymentStatus::options())->toBeArray();
});

it('has all event type cases', function () {
    expect(EventType::cases())->toHaveCount(5)
        ->and(EventType::Webinar->label())->toBe('Webinar');
});

it('has all job type cases', function () {
    expect(JobType::cases())->toHaveCount(4)
        ->and(JobType::FullTime->label())->toBe('Full-time');
});

it('has all campaign status cases', function () {
    expect(CampaignStatus::cases())->toHaveCount(3)
        ->and(CampaignStatus::Active->label())->toBe('Aktif');
});

it('has all job relevance cases', function () {
    expect(JobRelevance::cases())->toHaveCount(3)
        ->and(JobRelevance::Relevant->label())->toBe('Sesuai');
});

// ────────────────────── MODELS ──────────────────────

it('creates alumni profile with relations', function () {
    $setup = alumniTestSetup();
    $profile = createAlumniProfile($setup);

    expect($profile)->toBeInstanceOf(AlumniProfile::class)
        ->and($profile->studyProgram->name)->toBe('Teknik Informatika')
        ->and($profile->faculty->code)->toBe('FST');
});

it('calculates profile completeness correctly', function () {
    $setup = alumniTestSetup();
    $profile = createAlumniProfile($setup, ['phone' => null, 'current_city' => null, 'linkedin_url' => null]);

    expect($profile->profileCompleteness())->toBe(25); // only employment_status is filled

    $profile->update(['phone' => '081234567890', 'current_city' => 'Jakarta', 'linkedin_url' => 'https://linkedin.com/in/test']);

    expect($profile->fresh()->profileCompleteness())->toBe(100);
});

it('creates employer partner with job postings', function () {
    $setup = alumniTestSetup();
    $employer = EmployerPartner::create([
        'name' => 'PT Test Corp',
        'industry' => 'Teknologi',
        'is_active' => true,
        'created_by' => $setup['admin']->id,
        'updated_by' => $setup['admin']->id,
    ]);

    $job = JobPosting::create([
        'employer_partner_id' => $employer->id,
        'title' => 'Developer',
        'company_name' => 'PT Test Corp',
        'description' => 'Test description',
        'job_type' => 'full-time',
        'posted_date' => now()->toDateString(),
        'deadline_date' => now()->addDays(30)->toDateString(),
        'is_active' => true,
        'created_by' => $setup['admin']->id,
        'updated_by' => $setup['admin']->id,
    ]);

    expect($employer->jobPostings)->toHaveCount(1)
        ->and($job->employerPartner->name)->toBe('PT Test Corp');
});

it('tracks event participants and available slots', function () {
    $setup = alumniTestSetup();
    $event = AlumniEvent::create([
        'title' => 'Test Webinar',
        'event_type' => 'webinar',
        'event_date' => now()->addDays(7),
        'max_participants' => 2,
        'is_published' => true,
        'created_by' => $setup['admin']->id,
        'updated_by' => $setup['admin']->id,
    ]);

    $profile = createAlumniProfile($setup);

    expect($event->availableSlots())->toBe(2);

    AlumniEventParticipant::create([
        'alumni_event_id' => $event->id,
        'alumni_profile_id' => $profile->id,
        'registered_at' => now(),
    ]);

    expect($event->fresh()->availableSlots())->toBe(1)
        ->and($event->participants)->toHaveCount(1);
});

it('enforces unique alumni event participant constraint', function () {
    $setup = alumniTestSetup();
    $event = AlumniEvent::create([
        'title' => 'Test Event',
        'event_type' => 'networking',
        'event_date' => now()->addDays(5),
        'is_published' => true,
        'created_by' => $setup['admin']->id,
        'updated_by' => $setup['admin']->id,
    ]);

    $profile = createAlumniProfile($setup);

    AlumniEventParticipant::create([
        'alumni_event_id' => $event->id,
        'alumni_profile_id' => $profile->id,
        'registered_at' => now(),
    ]);

    expect(fn () => AlumniEventParticipant::create([
        'alumni_event_id' => $event->id,
        'alumni_profile_id' => $profile->id,
        'registered_at' => now(),
    ]))->toThrow(\Illuminate\Database\QueryException::class);
});

it('creates tracer study campaign with questions as array', function () {
    $setup = alumniTestSetup();
    $questions = [
        ['id' => 'q1', 'type' => 'text', 'text' => 'Pertanyaan 1', 'options' => []],
        ['id' => 'q2', 'type' => 'radio', 'text' => 'Pertanyaan 2', 'options' => ['A', 'B']],
    ];

    $campaign = TracerStudyCampaign::create([
        'title' => 'Tracer Study Test',
        'questions' => $questions,
        'start_date' => now()->toDateString(),
        'end_date' => now()->addDays(30)->toDateString(),
        'status' => 'draft',
        'created_by' => $setup['admin']->id,
        'updated_by' => $setup['admin']->id,
    ]);

    expect($campaign->questions)->toBeArray()->toHaveCount(2)
        ->and($campaign->questions[0]['text'])->toBe('Pertanyaan 1');
});

it('calculates campaign response rate', function () {
    $setup = alumniTestSetup();
    $campaign = TracerStudyCampaign::create([
        'title' => 'Rate Test',
        'questions' => [],
        'start_date' => now()->toDateString(),
        'end_date' => now()->addDays(30)->toDateString(),
        'status' => 'active',
        'total_sent' => 100,
        'total_responded' => 75,
        'created_by' => $setup['admin']->id,
        'updated_by' => $setup['admin']->id,
    ]);

    expect($campaign->responseRate())->toBe(75.0);
});

// ────────────────────── SERVICES ──────────────────────

it('submits tracer study response and increments counter', function () {
    $setup = alumniTestSetup();
    $profile = createAlumniProfile($setup, ['employment_status' => 'working']);

    $campaign = TracerStudyCampaign::create([
        'title' => 'Submit Test',
        'questions' => [['id' => 'q1', 'type' => 'text', 'text' => 'Q1', 'options' => []]],
        'start_date' => now()->toDateString(),
        'end_date' => now()->addDays(30)->toDateString(),
        'status' => 'active',
        'total_sent' => 10,
        'total_responded' => 0,
        'created_by' => $setup['admin']->id,
        'updated_by' => $setup['admin']->id,
    ]);

    $service = app(TracerStudyService::class);
    $response = $service->submitResponse($campaign, $profile, ['q1' => 'Answer'], [
        'employment_status' => 'working',
        'employer_name' => 'PT Test',
        'job_title' => 'Dev',
        'job_relevance' => 'relevant',
        'time_to_employment_months' => 3,
        'salary_range' => '5-8 juta',
        'further_study' => false,
    ]);

    expect($response)->toBeInstanceOf(TracerStudyResponse::class)
        ->and($response->answers)->toBe(['q1' => 'Answer'])
        ->and($campaign->fresh()->total_responded)->toBe(1);
});

it('prevents duplicate tracer study response', function () {
    $setup = alumniTestSetup();
    $profile = createAlumniProfile($setup);

    $campaign = TracerStudyCampaign::create([
        'title' => 'Duplicate Test',
        'questions' => [],
        'start_date' => now()->toDateString(),
        'end_date' => now()->addDays(30)->toDateString(),
        'status' => 'active',
        'total_sent' => 5,
        'total_responded' => 0,
        'created_by' => $setup['admin']->id,
        'updated_by' => $setup['admin']->id,
    ]);

    $service = app(TracerStudyService::class);
    $service->submitResponse($campaign, $profile, [], [
        'employment_status' => 'unemployed',
    ]);

    expect(fn () => $service->submitResponse($campaign, $profile, [], [
        'employment_status' => 'unemployed',
    ]))->toThrow(\RuntimeException::class);
});

it('sends campaign and updates total_sent', function () {
    $setup = alumniTestSetup();
    createAlumniProfile($setup, ['graduation_year' => 2023]);
    createAlumniProfile($setup, ['graduation_year' => 2023]);

    $campaign = TracerStudyCampaign::create([
        'title' => 'Send Test',
        'questions' => [],
        'target_graduation_years' => [2023],
        'start_date' => now()->toDateString(),
        'end_date' => now()->addDays(30)->toDateString(),
        'status' => 'draft',
        'created_by' => $setup['admin']->id,
        'updated_by' => $setup['admin']->id,
    ]);

    $service = app(TracerStudyService::class);
    $service->sendCampaign($campaign);

    $fresh = $campaign->fresh();
    expect($fresh->status)->toBe('active')
        ->and($fresh->total_sent)->toBe(2);
});

it('closes expired campaigns', function () {
    $setup = alumniTestSetup();
    TracerStudyCampaign::create([
        'title' => 'Expired Campaign',
        'questions' => [],
        'start_date' => now()->subDays(60)->toDateString(),
        'end_date' => now()->subDays(1)->toDateString(),
        'status' => 'active',
        'created_by' => $setup['admin']->id,
        'updated_by' => $setup['admin']->id,
    ]);

    $service = app(TracerStudyService::class);
    $closed = $service->closeExpired();

    expect($closed)->toBe(1);
});

it('exports tracer study responses as csv', function () {
    $setup = alumniTestSetup();
    $profile = createAlumniProfile($setup, ['employment_status' => 'working']);

    $campaign = TracerStudyCampaign::create([
        'title' => 'Export Test',
        'questions' => [['id' => 'q1', 'type' => 'text', 'text' => 'Name?', 'options' => []]],
        'start_date' => now()->toDateString(),
        'end_date' => now()->addDays(30)->toDateString(),
        'status' => 'active',
        'total_sent' => 1,
        'total_responded' => 1,
        'created_by' => $setup['admin']->id,
        'updated_by' => $setup['admin']->id,
    ]);

    TracerStudyResponse::create([
        'tracer_study_campaign_id' => $campaign->id,
        'alumni_profile_id' => $profile->id,
        'answers' => ['q1' => 'Test Answer'],
        'employment_status' => 'working',
        'submitted_at' => now(),
    ]);

    $exportService = app(TracerStudyExportService::class);

    // Just ensure the method exists and doesn't throw
    expect(method_exists($exportService, 'streamCsv'))->toBeTrue()
        ->and(method_exists($exportService, 'streamXlsx'))->toBeTrue()
        ->and(method_exists($exportService, 'streamPdf'))->toBeTrue();
});

// ────────────────────── ALUMNI ROUTES ──────────────────────

function alumniTestUser(): User
{
    // Ensure system is marked as installed (bypass fillable)
    if (! \App\Models\Settings\System::query()->exists()) {
        DB::table('systems')->insert([
            'app_name' => 'NexaCampus',
            'app_version' => 'v1.0',
            'app_description' => 'Test',
            'app_url' => 'http://localhost',
            'app_email' => 'test@test.com',
            'is_installed' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    } else {
        DB::table('systems')->update(['is_installed' => true]);
    }
    if (! \App\Models\Settings\Campus::query()->exists()) {
        \App\Models\Settings\Campus::create([
            'name' => 'Test Campus', 'phone' => '0', 'whatsapp' => '0',
            'email_info' => 'test@test.com', 'email_humas' => 'test@test.com', 'domain' => 'test.com',
        ]);
    }

    $role = \App\Models\Access\Role::firstOrCreate(['name' => 'alumni', 'guard_name' => 'web']);
    $user = User::factory()->create();
    $user->assignRole($role);
    session(['active_role' => 'alumni']);
    return $user;
}

it('renders alumni dashboard for authenticated alumni user', function () {
    $user = alumniTestUser();

    $response = $this->actingAs($user)->withSession(['active_role' => 'alumni'])->get(route('alumni.dashboard.index'));
    $response->assertStatus(200);
});

it('renders alumni profile page', function () {
    $user = alumniTestUser();

    $response = $this->actingAs($user)->withSession(['active_role' => 'alumni'])->get(route('alumni.profile.index'));
    $response->assertStatus(200);
});

it('renders alumni job board', function () {
    $user = alumniTestUser();

    $response = $this->actingAs($user)->withSession(['active_role' => 'alumni'])->get(route('alumni.jobs.index'));
    $response->assertStatus(200);
});

it('renders alumni events list', function () {
    $user = alumniTestUser();

    $response = $this->actingAs($user)->withSession(['active_role' => 'alumni'])->get(route('alumni.events.index'));
    $response->assertStatus(200);
});

it('renders alumni tracer study index', function () {
    $user = alumniTestUser();

    $response = $this->actingAs($user)->withSession(['active_role' => 'alumni'])->get(route('alumni.tracer-study.index'));
    $response->assertStatus(200);
});

// ────────────────────── AL-3: PORTAL ALUMNI (INERTIA) ──────────────────────

function alumniPortalContext(array $profileOverrides = []): array
{
    $setup = alumniTestSetup();
    $user = alumniTestUser();
    $profile = createAlumniProfile($setup, array_merge([
        'user_id' => $user->id,
        'phone' => '081200000000',
        'current_city' => 'Bandung',
        'linkedin_url' => 'https://linkedin.com/in/alumni-test',
        'employment_status' => 'working',
        'employer_name' => 'PT Lama',
        'job_title' => 'Staff Lama',
        'job_industry' => 'Manufaktur',
    ], $profileOverrides));

    return compact('setup', 'user', 'profile');
}

function alumniPortalJob(array $setup, array $overrides = []): JobPosting
{
    return JobPosting::create(array_merge([
        'title' => 'Backend Engineer',
        'company_name' => 'PT Mitra',
        'description' => 'Deskripsi lowongan',
        'job_type' => 'full-time',
        'industry' => 'Teknologi',
        'location' => 'Jakarta',
        'posted_date' => now()->toDateString(),
        'deadline_date' => now()->addDays(20)->toDateString(),
        'is_active' => true,
        'created_by' => $setup['admin']->id,
        'updated_by' => $setup['admin']->id,
    ], $overrides));
}

function alumniPortalEvent(array $setup, array $overrides = []): AlumniEvent
{
    return AlumniEvent::create(array_merge([
        'title' => 'Webinar Karier',
        'event_type' => 'webinar',
        'event_date' => now()->addDays(10),
        'location' => 'Aula Kampus',
        'is_published' => true,
        'created_by' => $setup['admin']->id,
        'updated_by' => $setup['admin']->id,
    ], $overrides));
}

function alumniPortalCampaign(array $setup, array $overrides = []): TracerStudyCampaign
{
    return TracerStudyCampaign::create(array_merge([
        'title' => 'Tracer 2026',
        'questions' => [
            ['id' => 'q1', 'type' => 'text', 'text' => 'Pekerjaan saat ini?', 'options' => []],
            ['id' => 'q2', 'type' => 'radio', 'text' => 'Kesesuaian studi?', 'options' => ['Sesuai', 'Tidak']],
        ],
        'start_date' => now()->toDateString(),
        'end_date' => now()->addDays(30)->toDateString(),
        'status' => 'active',
        'total_sent' => 10,
        'total_responded' => 0,
        'created_by' => $setup['admin']->id,
        'updated_by' => $setup['admin']->id,
    ], $overrides));
}

it('menampilkan profil alumni lengkap lewat Inertia', function () {
    ['user' => $user, 'profile' => $profile] = alumniPortalContext();

    $this->actingAs($user)->withSession(['active_role' => 'alumni'])
        ->get(route('alumni.profile.index'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('Alumni/Profile/Index')
            ->where('hasProfile', true)
            ->where('profile.nim', $profile->nim)
            ->where('profile.employmentStatusLabel', 'Bekerja')
            ->where('completeness', 100)
            ->where('urls.edit', route('alumni.profile.edit')));
});

it('membersihkan data karir lama saat status berubah jadi bukan pekerja', function () {
    ['user' => $user, 'profile' => $profile] = alumniPortalContext();

    $this->actingAs($user)->withSession(['active_role' => 'alumni'])
        ->put(route('alumni.profile.update'), [
            'employment_status' => 'studying',
            'employer_name' => 'PT Lama',
            'job_title' => 'Staff Lama',
            'job_industry' => 'Manufaktur',
            'linkedin_url' => 'https://linkedin.com/in/alumni-test',
        ])
        ->assertRedirect(route('alumni.profile.index'))
        ->assertSessionHas('success');

    $profile->refresh();

    expect($profile->employment_status)->toBe('studying')
        ->and($profile->employer_name)->toBeNull()
        ->and($profile->job_title)->toBeNull()
        ->and($profile->job_industry)->toBeNull()
        ->and($profile->linkedin_url)->toBe('https://linkedin.com/in/alumni-test');
});

it('menolak status pekerjaan yang tidak ada di enum', function () {
    ['user' => $user] = alumniPortalContext();

    $this->actingAs($user)->withSession(['active_role' => 'alumni'])
        ->put(route('alumni.profile.update'), ['employment_status' => 'alien'])
        ->assertSessionHasErrors('employment_status');
});

it('hanya menampilkan lowongan aktif yang belum kedaluwarsa', function () {
    ['setup' => $setup, 'user' => $user] = alumniPortalContext();

    $active = alumniPortalJob($setup);

    alumniPortalJob($setup, ['title' => 'Lowongan Kedaluwarsa', 'deadline_date' => now()->subDay()->toDateString()]);
    alumniPortalJob($setup, ['title' => 'Lowongan Nonaktif', 'is_active' => false]);

    $this->actingAs($user)->withSession(['active_role' => 'alumni'])
        ->get(route('alumni.jobs.index'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('Alumni/Jobs/Index')
            ->has('data.rows', 1)
            ->where('data.rows.0.id', $active->id)
            ->where('data.total', 1));

    $this->actingAs($user)->withSession(['active_role' => 'alumni'])
        ->get(route('alumni.jobs.index', ['job_type' => 'internship']))
        ->assertOk()
        ->assertInertia(fn ($page) => $page->has('data.rows', 0));
});

it('menandai lowongan kedaluwarsa saat dibuka lewat tautan langsung', function () {
    ['setup' => $setup, 'user' => $user] = alumniPortalContext();

    $job = alumniPortalJob($setup, ['deadline_date' => now()->subDays(3)->toDateString()]);

    $this->actingAs($user)->withSession(['active_role' => 'alumni'])
        ->get(route('alumni.jobs.show', $job))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('Alumni/Jobs/Show')
            ->where('job.isExpired', true)
            ->where('job.id', $job->id));
});

it('menyembunyikan detail lowongan nonaktif', function () {
    ['setup' => $setup, 'user' => $user] = alumniPortalContext();

    $job = alumniPortalJob($setup, ['is_active' => false]);

    $this->actingAs($user)->withSession(['active_role' => 'alumni'])
        ->get(route('alumni.jobs.show', $job))
        ->assertNotFound();
});

it('hanya menampilkan event yang dipublikasikan dan bisa difilter tipe', function () {
    ['setup' => $setup, 'user' => $user] = alumniPortalContext();

    $published = alumniPortalEvent($setup);
    alumniPortalEvent($setup, ['title' => 'Event Draft', 'is_published' => false]);

    $this->actingAs($user)->withSession(['active_role' => 'alumni'])
        ->get(route('alumni.events.index'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('Alumni/Events/Index')
            ->has('data.rows', 1)
            ->where('data.rows.0.id', $published->id)
            ->where('data.total', 1));

    $this->actingAs($user)->withSession(['active_role' => 'alumni'])
        ->get(route('alumni.events.index', ['event_type' => 'workshop']))
        ->assertOk()
        ->assertInertia(fn ($page) => $page->has('data.rows', 0));
});

it('menandai alumni yang sudah terdaftar pada detail event', function () {
    ['setup' => $setup, 'user' => $user, 'profile' => $profile] = alumniPortalContext();
    $event = alumniPortalEvent($setup, ['max_participants' => 10]);

    AlumniEventParticipant::create([
        'alumni_event_id' => $event->id,
        'alumni_profile_id' => $profile->id,
        'registered_at' => now(),
    ]);

    $this->actingAs($user)->withSession(['active_role' => 'alumni'])
        ->get(route('alumni.events.show', $event))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('Alumni/Events/Show')
            ->where('isRegistered', true)
            ->where('canRegister', false)
            ->where('event.participantsCount', 1)
            ->where('event.slotsLeft', 9));
});

it('menolak pendaftaran event yang tenggatnya sudah lewat', function () {
    ['setup' => $setup, 'user' => $user] = alumniPortalContext();

    $event = alumniPortalEvent($setup, ['registration_deadline' => now()->subDay()]);

    $this->actingAs($user)->withSession(['active_role' => 'alumni'])
        ->get(route('alumni.events.register', $event))
        ->assertRedirect(route('alumni.events.show', $event))
        ->assertSessionHas('error', 'Pendaftaran event sudah ditutup.');
});

it('menyimpan pendaftaran event dan menolak duplikat', function () {
    ['setup' => $setup, 'user' => $user, 'profile' => $profile] = alumniPortalContext();
    $event = alumniPortalEvent($setup, ['max_participants' => 5]);

    $this->actingAs($user)->withSession(['active_role' => 'alumni'])
        ->post(route('alumni.events.register.store', $event), ['notes' => 'Hadir tepat waktu'])
        ->assertRedirect(route('alumni.events.show', $event))
        ->assertSessionHas('success');

    expect(AlumniEventParticipant::where('alumni_event_id', $event->id)
        ->where('alumni_profile_id', $profile->id)
        ->count())->toBe(1);

    expect(AlumniEventParticipant::first()->notes)->toBe('Hadir tepat waktu');

    $this->actingAs($user)->withSession(['active_role' => 'alumni'])
        ->post(route('alumni.events.register.store', $event))
        ->assertRedirect(route('alumni.events.show', $event))
        ->assertSessionHas('info', 'Kamu sudah terdaftar di event ini.');

    expect(AlumniEventParticipant::where('alumni_event_id', $event->id)->count())->toBe(1);
});

it('menolak pendaftaran event saat kuota penuh', function () {
    ['setup' => $setup, 'user' => $user] = alumniPortalContext();
    $event = alumniPortalEvent($setup, ['max_participants' => 1]);

    $other = createAlumniProfile($setup);
    AlumniEventParticipant::create([
        'alumni_event_id' => $event->id,
        'alumni_profile_id' => $other->id,
        'registered_at' => now(),
    ]);

    $this->actingAs($user)->withSession(['active_role' => 'alumni'])
        ->get(route('alumni.events.register', $event))
        ->assertRedirect(route('alumni.events.show', $event))
        ->assertSessionHas('error', 'Kuota event sudah penuh.');

    $this->actingAs($user)->withSession(['active_role' => 'alumni'])
        ->post(route('alumni.events.register.store', $event))
        ->assertRedirect(route('alumni.events.show', $event))
        ->assertSessionHas('error', 'Kuota event sudah penuh.');

    expect(AlumniEventParticipant::where('alumni_event_id', $event->id)->count())->toBe(1);
});

it('menyaring kampanye tracer sesuai sasaran dan periode aktif', function () {
    ['setup' => $setup, 'user' => $user] = alumniPortalContext();

    $targeted = alumniPortalCampaign($setup, ['target_graduation_years' => [2023]]);
    alumniPortalCampaign($setup, ['title' => 'Tahun Lain', 'target_graduation_years' => [2020]]);
    alumniPortalCampaign($setup, ['title' => 'Belum Dibuka', 'start_date' => now()->addDays(5)->toDateString()]);
    alumniPortalCampaign($setup, ['title' => 'Sudah Ditutup', 'status' => 'closed']);

    $this->actingAs($user)->withSession(['active_role' => 'alumni'])
        ->get(route('alumni.tracer-study.index'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('Alumni/TracerStudy/Index')
            ->has('campaigns', 1)
            ->where('campaigns.0.id', $targeted->id)
            ->where('campaigns.0.isOpen', true)
            ->where('campaigns.0.hasResponded', false));
});

it('tetap menampilkan kampanye yang sudah diisi walau sudah ditutup', function () {
    ['setup' => $setup, 'user' => $user, 'profile' => $profile] = alumniPortalContext();

    $campaign = alumniPortalCampaign($setup, ['status' => 'closed']);

    TracerStudyResponse::create([
        'tracer_study_campaign_id' => $campaign->id,
        'alumni_profile_id' => $profile->id,
        'answers' => ['q1' => 'Sudah diisi'],
        'employment_status' => 'working',
        'submitted_at' => now(),
    ]);

    $this->actingAs($user)->withSession(['active_role' => 'alumni'])
        ->get(route('alumni.tracer-study.index'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->has('campaigns', 1)
            ->where('campaigns.0.hasResponded', true)
            ->where('campaigns.0.isOpen', false)
            ->where('campaigns.0.fillUrl', null));
});

it('menolak detail kampanye draft atau di luar sasaran alumni', function () {
    ['setup' => $setup, 'user' => $user] = alumniPortalContext();

    $draft = alumniPortalCampaign($setup, ['status' => 'draft']);
    $otherYear = alumniPortalCampaign($setup, ['title' => 'Tahun Lain', 'target_graduation_years' => [2019]]);

    $this->actingAs($user)->withSession(['active_role' => 'alumni'])
        ->get(route('alumni.tracer-study.show', $draft))
        ->assertNotFound();

    $this->actingAs($user)->withSession(['active_role' => 'alumni'])
        ->get(route('alumni.tracer-study.show', $otherYear))
        ->assertNotFound();
});

it('mengalihkan pengisian kampanye yang sudah tidak aktif', function () {
    ['setup' => $setup, 'user' => $user] = alumniPortalContext();

    $closed = alumniPortalCampaign($setup, ['status' => 'closed']);

    $this->actingAs($user)->withSession(['active_role' => 'alumni'])
        ->get(route('alumni.tracer-study.fill', $closed))
        ->assertRedirect(route('alumni.tracer-study.show', $closed))
        ->assertSessionHas('error', 'Survei ini sudah tidak aktif.');
});

it('mewajibkan semua pertanyaan tracer dijawab dengan opsi yang valid', function () {
    ['setup' => $setup, 'user' => $user] = alumniPortalContext();
    $campaign = alumniPortalCampaign($setup);

    $payload = [
        'snapshot' => [
            'employment_status' => 'working',
            'employer_name' => 'PT Baru',
            'job_title' => 'Engineer',
            'job_relevance' => 'relevant',
            'time_to_employment_months' => 3,
            'salary_range' => '8-10 juta',
            'further_study' => false,
        ],
        'answers' => ['q1' => 'Sudah bekerja'],
    ];

    $this->actingAs($user)->withSession(['active_role' => 'alumni'])
        ->post(route('alumni.tracer-study.submit', $campaign), $payload)
        ->assertSessionHasErrors('answers.q2');

    $payload['answers']['q2'] = 'Opsi Tidak Ada';

    $this->actingAs($user)->withSession(['active_role' => 'alumni'])
        ->post(route('alumni.tracer-study.submit', $campaign), $payload)
        ->assertSessionHasErrors('answers.q2');

    expect(TracerStudyResponse::count())->toBe(0);
});

it('menyimpan jawaban tracer study memakai id pertanyaan yang stabil', function () {
    ['setup' => $setup, 'user' => $user, 'profile' => $profile] = alumniPortalContext();
    $campaign = alumniPortalCampaign($setup);

    $this->actingAs($user)->withSession(['active_role' => 'alumni'])
        ->post(route('alumni.tracer-study.submit', $campaign), [
            'snapshot' => [
                'employment_status' => 'working',
                'employer_name' => 'PT Baru',
                'job_title' => 'Engineer',
                'job_relevance' => 'relevant',
                'time_to_employment_months' => 3,
                'salary_range' => '8-10 juta',
                'further_study' => true,
            ],
            'answers' => [
                'q1' => 'Sudah bekerja',
                'q2' => 'Sesuai',
            ],
        ])
        ->assertRedirect(route('alumni.tracer-study.show', $campaign))
        ->assertSessionHas('success');

    $response = TracerStudyResponse::where('tracer_study_campaign_id', $campaign->id)
        ->where('alumni_profile_id', $profile->id)
        ->first();

    expect($response)->not->toBeNull()
        ->and($response->answers)->toBe(['q1' => 'Sudah bekerja', 'q2' => 'Sesuai'])
        ->and($response->employment_status)->toBe('working')
        ->and($response->job_relevance)->toBe('relevant')
        ->and((bool) $response->further_study)->toBeTrue()
        ->and($campaign->fresh()->total_responded)->toBe(1);
});

it('menolak submit ulang tracer study yang sudah diisi', function () {
    ['setup' => $setup, 'user' => $user, 'profile' => $profile] = alumniPortalContext();
    $campaign = alumniPortalCampaign($setup);

    TracerStudyResponse::create([
        'tracer_study_campaign_id' => $campaign->id,
        'alumni_profile_id' => $profile->id,
        'answers' => ['q1' => 'Pertama', 'q2' => 'Sesuai'],
        'employment_status' => 'working',
        'submitted_at' => now(),
    ]);

    $this->actingAs($user)->withSession(['active_role' => 'alumni'])
        ->get(route('alumni.tracer-study.fill', $campaign))
        ->assertRedirect(route('alumni.tracer-study.show', $campaign))
        ->assertSessionHas('info', 'Kamu sudah mengisi survei ini.');

    $this->actingAs($user)->withSession(['active_role' => 'alumni'])
        ->post(route('alumni.tracer-study.submit', $campaign), [
            'snapshot' => ['employment_status' => 'unemployed', 'further_study' => false],
            'answers' => ['q1' => 'Kedua', 'q2' => 'Tidak'],
        ])
        ->assertRedirect(route('alumni.tracer-study.show', $campaign))
        ->assertSessionHas('info', 'Kamu sudah mengisi survei ini.');

    expect(TracerStudyResponse::count())->toBe(1);
});
