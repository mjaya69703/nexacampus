<?php

use App\Models\Academic\Faculty;
use App\Models\Academic\StudyProgram;
use App\Models\Alumni\AlumniEvent;
use App\Models\Alumni\AlumniEventParticipant;
use App\Models\Alumni\AlumniProfile;
use App\Models\Alumni\TracerStudyCampaign;
use App\Models\Alumni\TracerStudyResponse;
use App\Models\Settings\System;
use App\Models\User;
use App\Support\Alumni\TracerStudyService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Spatie\Permission\Models\Permission as SpatiePermission;
use Spatie\Permission\Models\Role as SpatieRole;

uses(RefreshDatabase::class);

function alumniBatchTwoSetup(): array
{
    SpatieRole::firstOrCreate(['name' => 'admin', 'guard_name' => 'web']);

    System::create([
        'app_name' => 'NexaCampus Test',
        'app_version' => '1.0.0',
        'app_description' => 'Test',
        'app_url' => 'http://localhost',
        'app_email' => 'test@localhost',
    ])->forceFill(['is_installed' => true])->save();

    $admin = User::factory()->create();
    $admin->assignRole('admin');

    $faculty = Faculty::create([
        'name' => 'Fakultas Teknik',
        'code' => 'FT',
        'is_active' => true,
    ]);

    $program = StudyProgram::create([
        'faculty_id' => $faculty->id,
        'name' => 'Informatika',
        'code' => 'IF',
        'degree' => 'S1',
        'is_active' => true,
    ]);

    $otherProgram = StudyProgram::create([
        'faculty_id' => $faculty->id,
        'name' => 'Sistem Informasi',
        'code' => 'SI',
        'degree' => 'S1',
        'is_active' => true,
    ]);

    return compact('admin', 'faculty', 'program', 'otherProgram');
}

function giveAlumniBatchTwoPermissions(array $names): void
{
    $role = SpatieRole::where('name', 'admin')->firstOrFail();

    foreach ($names as $name) {
        $role->givePermissionTo(SpatiePermission::firstOrCreate([
            'name' => $name,
            'guard_name' => 'web',
        ]));
    }
}

function actingAlumniBatchTwoAdmin(User $admin)
{
    return test()->actingAs($admin)->withSession(['active_role' => 'admin']);
}

function alumniBatchTwoProfile(array $setup, array $overrides = []): AlumniProfile
{
    static $counter = 0;
    $counter++;

    return AlumniProfile::create([
        'nim' => $overrides['nim'] ?? '2024'.str_pad((string) $counter, 6, '0', STR_PAD_LEFT),
        'full_name' => $overrides['full_name'] ?? 'Alumni '.$counter,
        'graduation_date' => $overrides['graduation_date'] ?? '2024-08-31',
        'graduation_year' => $overrides['graduation_year'] ?? 2024,
        'faculty_id' => $setup['faculty']->id,
        'study_program_id' => $overrides['study_program_id'] ?? $setup['program']->id,
        'email' => $overrides['email'] ?? 'alumni'.$counter.'@example.test',
        'employment_status' => $overrides['employment_status'] ?? 'unemployed',
        'is_active' => $overrides['is_active'] ?? true,
    ]);
}

function alumniBatchTwoEventPayload(array $overrides = []): array
{
    $start = now()->addDays(10)->setTime(9, 0);

    return array_merge([
        'title' => 'Webinar Karier Alumni',
        'description' => 'Berbagi pengalaman karier untuk alumni baru.',
        'event_type' => 'webinar',
        'event_date' => $start->format('Y-m-d H:i:s'),
        'end_date' => $start->copy()->addHours(2)->format('Y-m-d H:i:s'),
        'location' => 'Auditorium Kampus',
        'is_online' => false,
        'meeting_url' => '',
        'max_participants' => 100,
        'registration_deadline' => now()->addDays(5)->setTime(23, 59)->format('Y-m-d H:i:s'),
        'is_published' => true,
    ], $overrides);
}

function alumniBatchTwoCampaignPayload(array $setup, array $overrides = []): array
{
    return array_merge([
        'title' => 'Tracer Study Lulusan 2024',
        'description' => 'Kuesioner status pekerjaan alumni.',
        'academic_year_id' => '',
        'target_graduation_years' => [2024],
        'target_study_program_ids' => [$setup['program']->id],
        'start_date' => now()->toDateString(),
        'end_date' => now()->addDays(30)->toDateString(),
        'status' => 'draft',
        'questions' => [
            ['id' => 'q_status', 'type' => 'radio', 'text' => 'Status Anda saat ini?', 'options' => "Bekerja\nWirausaha\nBelum bekerja"],
            ['id' => 'q_note', 'type' => 'text', 'text' => 'Catatan tambahan', 'options' => ''],
        ],
    ], $overrides);
}

function alumniBatchTwoCampaign(array $setup, array $overrides = []): TracerStudyCampaign
{
    $payload = alumniBatchTwoCampaignPayload($setup, $overrides);

    return TracerStudyCampaign::create([
        'title' => $payload['title'],
        'description' => $payload['description'],
        'academic_year_id' => null,
        'target_graduation_years' => $payload['target_graduation_years'],
        'target_study_program_ids' => $payload['target_study_program_ids'],
        'start_date' => $payload['start_date'],
        'end_date' => $payload['end_date'],
        'status' => $payload['status'],
        'questions' => $payload['questions'],
        'created_by' => $setup['admin']->id,
    ]);
}

it('mengelola event alumni: validasi, edit, poster, kuota, status, sampah, dan ekspor', function () {
    $setup = alumniBatchTwoSetup();
    giveAlumniBatchTwoPermissions([
        'alumni-event.viewAny', 'alumni-event.create', 'alumni-event.update', 'alumni-event.delete', 'alumni-event.view',
    ]);

    Storage::fake('public');
    Storage::disk('public')->put('alumni-events/posters/lama.jpg', 'poster-lama');

    actingAlumniBatchTwoAdmin($setup['admin'])
        ->get('/admin/alumni/alumni-events')
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('Admin/Alumni/Event/Index')
            ->where('stats.total', 0)
            ->has('data.rows', 0));

    actingAlumniBatchTwoAdmin($setup['admin'])
        ->post('/admin/alumni/alumni-events', alumniBatchTwoEventPayload())
        ->assertRedirect('/admin/alumni/alumni-events')
        ->assertSessionHas('success');

    $event = AlumniEvent::where('title', 'Webinar Karier Alumni')->firstOrFail();
    $event->update(['poster_path' => 'alumni-events/posters/lama.jpg']);

    expect($event->is_published)->toBeTrue()
        ->and($event->created_by)->toBe($setup['admin']->id);

    // Event daring wajib punya tautan meeting.
    actingAlumniBatchTwoAdmin($setup['admin'])
        ->post('/admin/alumni/alumni-events', alumniBatchTwoEventPayload([
            'title' => 'Event Online Tanpa Link',
            'is_online' => true,
            'meeting_url' => '',
        ]))
        ->assertSessionHasErrors('meeting_url');

    // Batas pendaftaran tidak boleh melewati waktu mulai.
    actingAlumniBatchTwoAdmin($setup['admin'])
        ->post('/admin/alumni/alumni-events', alumniBatchTwoEventPayload([
            'title' => 'Batas Daftar Salah',
            'registration_deadline' => now()->addDays(20)->format('Y-m-d H:i:s'),
        ]))
        ->assertSessionHasErrors('registration_deadline');

    // Kolom opsional harus benar-benar bisa dikosongkan saat edit.
    actingAlumniBatchTwoAdmin($setup['admin'])
        ->put("/admin/alumni/alumni-events/{$event->id}", alumniBatchTwoEventPayload([
            'title' => 'Webinar Karier Alumni Baru',
            'description' => '',
            'end_date' => '',
            'location' => '',
            'meeting_url' => '',
            'max_participants' => '',
            'registration_deadline' => '',
            'remove_poster' => true,
        ]))
        ->assertRedirect('/admin/alumni/alumni-events')
        ->assertSessionHas('success');

    $event->refresh();

    expect($event->title)->toBe('Webinar Karier Alumni Baru')
        ->and($event->description)->toBeNull()
        ->and($event->end_date)->toBeNull()
        ->and($event->location)->toBeNull()
        ->and($event->registration_deadline)->toBeNull()
        ->and($event->max_participants)->toBeNull()
        ->and($event->poster_path)->toBeNull();

    Storage::disk('public')->assertMissing('alumni-events/posters/lama.jpg');

    // Kuota tidak boleh lebih kecil dari peserta yang sudah terdaftar.
    $first = alumniBatchTwoProfile($setup);
    $second = alumniBatchTwoProfile($setup);

    foreach ([$first, $second] as $profile) {
        AlumniEventParticipant::create([
            'alumni_event_id' => $event->id,
            'alumni_profile_id' => $profile->id,
            'registered_at' => now(),
        ]);
    }

    actingAlumniBatchTwoAdmin($setup['admin'])
        ->put("/admin/alumni/alumni-events/{$event->id}", alumniBatchTwoEventPayload([
            'title' => 'Webinar Karier Alumni Baru',
            'max_participants' => 1,
        ]))
        ->assertSessionHasErrors('max_participants');

    actingAlumniBatchTwoAdmin($setup['admin'])
        ->post("/admin/alumni/alumni-events/{$event->id}/toggle", ['is_published' => false])
        ->assertSessionHas('success');

    expect($event->refresh()->is_published)->toBeFalse();

    actingAlumniBatchTwoAdmin($setup['admin'])
        ->get('/admin/alumni/alumni-events')
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->where('stats.total', 1)
            ->where('stats.participants', 2)
            ->where('data.rows.0.participantsCount', 2)
            ->where('data.rows.0.isPublished', false));

    $csv = actingAlumniBatchTwoAdmin($setup['admin'])
        ->get('/admin/alumni/alumni-events/export?format=csv')
        ->assertOk();

    expect($csv->headers->get('Content-Type'))->toContain('text/csv')
        ->and($csv->streamedContent())->toContain('Judul', 'Webinar Karier Alumni Baru');

    $xlsx = actingAlumniBatchTwoAdmin($setup['admin'])
        ->get('/admin/alumni/alumni-events/export?format=xlsx')
        ->assertOk();

    expect($xlsx->headers->get('Content-Type'))->toContain('spreadsheetml')
        ->and(str_starts_with($xlsx->streamedContent(), 'PK'))->toBeTrue();

    actingAlumniBatchTwoAdmin($setup['admin'])
        ->delete("/admin/alumni/alumni-events/{$event->id}")
        ->assertRedirect('/admin/alumni/alumni-events');

    actingAlumniBatchTwoAdmin($setup['admin'])
        ->get('/admin/alumni/alumni-events?mode=trash')
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->where('filters.mode', 'trash')
            ->where('stats.trashed', 1)
            ->has('data.rows', 1));

    actingAlumniBatchTwoAdmin($setup['admin'])
        ->post("/admin/alumni/alumni-events/{$event->id}/restore")
        ->assertSessionHas('success');

    expect(AlumniEvent::find($event->id))->not->toBeNull();

    actingAlumniBatchTwoAdmin($setup['admin'])
        ->delete("/admin/alumni/alumni-events/{$event->id}")
        ->assertRedirect('/admin/alumni/alumni-events');

    actingAlumniBatchTwoAdmin($setup['admin'])
        ->delete("/admin/alumni/alumni-events/{$event->id}/force")
        ->assertSessionHas('success');

    expect(AlumniEvent::withTrashed()->find($event->id))->toBeNull();
});

it('menampilkan detail event beserta peserta', function () {
    $setup = alumniBatchTwoSetup();
    giveAlumniBatchTwoPermissions(['alumni-event.viewAny', 'alumni-event.view']);

    $event = AlumniEvent::create([
        ...alumniBatchTwoEventPayload(['is_published' => true]),
        'event_date' => now()->addDays(3),
        'end_date' => null,
        'registration_deadline' => null,
    ]);

    $profile = alumniBatchTwoProfile($setup);

    AlumniEventParticipant::create([
        'alumni_event_id' => $event->id,
        'alumni_profile_id' => $profile->id,
        'registered_at' => now(),
        'attended_at' => now(),
        'notes' => 'Hadir tepat waktu',
    ]);

    actingAlumniBatchTwoAdmin($setup['admin'])
        ->get("/admin/alumni/alumni-events/{$event->id}")
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('Admin/Alumni/Event/Show')
            ->where('event.title', 'Webinar Karier Alumni')
            ->where('event.participantsCount', 1)
            ->where('event.registrationOpen', true)
            ->has('participants', 1)
            ->where('participants.0.name', $profile->full_name)
            ->where('participants.0.attended', true));
});

it('membuat kampanye tracer study dengan id pertanyaan stabil dan counter sasaran aktif', function () {
    $setup = alumniBatchTwoSetup();
    giveAlumniBatchTwoPermissions([
        'tracer-study-campaign.viewAny', 'tracer-study-campaign.create', 'tracer-study-campaign.update',
    ]);

    alumniBatchTwoProfile($setup, ['graduation_year' => 2024]);
    alumniBatchTwoProfile($setup, ['graduation_year' => 2024]);
    alumniBatchTwoProfile($setup, ['graduation_year' => 2023]);

    actingAlumniBatchTwoAdmin($setup['admin'])
        ->post('/admin/alumni/tracer-study-campaigns', alumniBatchTwoCampaignPayload($setup, [
            'status' => 'active',
        ]))
        ->assertRedirect('/admin/alumni/tracer-study-campaigns')
        ->assertSessionHas('success');

    $campaign = TracerStudyCampaign::where('title', 'Tracer Study Lulusan 2024')->firstOrFail();

    expect($campaign->status)->toBe('active')
        ->and($campaign->total_sent)->toBe(2)
        ->and($campaign->total_responded)->toBe(0)
        ->and($campaign->questions[0]['id'])->toBe('q_status')
        ->and($campaign->questions[0]['options'])->toBe(['Bekerja', 'Wirausaha', 'Belum bekerja'])
        ->and($campaign->questions[1]['id'])->toBe('q_note')
        ->and($campaign->questions[1]['options'])->toBe([]);

    actingAlumniBatchTwoAdmin($setup['admin'])
        ->get('/admin/alumni/tracer-study-campaigns')
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('Admin/Alumni/TracerStudy/Index')
            ->where('stats.total', 1)
            ->where('stats.active', 1)
            ->where('data.rows.0.questionsCount', 2)
            ->where('data.rows.0.responseRate', 0)
            ->where('data.rows.0.targetYears', [2024]));
});

it('mengaktifkan kampanye dan tidak mengizinkan status mundur', function () {
    $setup = alumniBatchTwoSetup();
    giveAlumniBatchTwoPermissions([
        'tracer-study-campaign.viewAny', 'tracer-study-campaign.create', 'tracer-study-campaign.update',
    ]);

    alumniBatchTwoProfile($setup, ['graduation_year' => 2024]);
    alumniBatchTwoProfile($setup, ['graduation_year' => 2023]);

    actingAlumniBatchTwoAdmin($setup['admin'])
        ->post('/admin/alumni/tracer-study-campaigns', alumniBatchTwoCampaignPayload($setup))
        ->assertRedirect('/admin/alumni/tracer-study-campaigns');

    $campaign = TracerStudyCampaign::where('title', 'Tracer Study Lulusan 2024')->firstOrFail();

    expect($campaign->status)->toBe('draft')
        ->and($campaign->total_sent)->toBe(0);

    actingAlumniBatchTwoAdmin($setup['admin'])
        ->post("/admin/alumni/tracer-study-campaigns/{$campaign->id}/activate")
        ->assertRedirect("/admin/alumni/tracer-study-campaigns/{$campaign->id}")
        ->assertSessionHas('success');

    $campaign->refresh();

    expect($campaign->status)->toBe('active')
        ->and($campaign->total_sent)->toBe(1);

    // Draft tidak boleh dikembalikan setelah aktif.
    actingAlumniBatchTwoAdmin($setup['admin'])
        ->put("/admin/alumni/tracer-study-campaigns/{$campaign->id}", alumniBatchTwoCampaignPayload($setup, [
            'status' => 'draft',
        ]))
        ->assertSessionHasErrors('status');

    // Tutup kampanye, lalu pastikan tidak bisa dibuka lagi.
    actingAlumniBatchTwoAdmin($setup['admin'])
        ->put("/admin/alumni/tracer-study-campaigns/{$campaign->id}", alumniBatchTwoCampaignPayload($setup, [
            'status' => 'closed',
        ]))
        ->assertRedirect('/admin/alumni/tracer-study-campaigns')
        ->assertSessionHas('success');

    expect($campaign->refresh()->status)->toBe('closed');

    actingAlumniBatchTwoAdmin($setup['admin'])
        ->put("/admin/alumni/tracer-study-campaigns/{$campaign->id}", alumniBatchTwoCampaignPayload($setup, [
            'status' => 'active',
        ]))
        ->assertSessionHasErrors('status');
});

it('menyinkronkan total_responded dengan respon nyata saat kampanye diperbarui', function () {
    $setup = alumniBatchTwoSetup();
    giveAlumniBatchTwoPermissions([
        'tracer-study-campaign.viewAny', 'tracer-study-campaign.update',
    ]);

    $profile = alumniBatchTwoProfile($setup);
    $campaign = alumniBatchTwoCampaign($setup, ['status' => 'draft']);

    TracerStudyResponse::create([
        'tracer_study_campaign_id' => $campaign->id,
        'alumni_profile_id' => $profile->id,
        'answers' => ['q_status' => 'Bekerja'],
        'employment_status' => 'working',
        'submitted_at' => now(),
    ]);

    expect($campaign->refresh()->total_responded)->toBe(0);

    actingAlumniBatchTwoAdmin($setup['admin'])
        ->put("/admin/alumni/tracer-study-campaigns/{$campaign->id}", alumniBatchTwoCampaignPayload($setup, [
            'status' => 'active',
        ]))
        ->assertRedirect('/admin/alumni/tracer-study-campaigns');

    $campaign->refresh();

    // total_sent minimal sama dengan jumlah respon yang sudah masuk.
    expect($campaign->status)->toBe('active')
        ->and($campaign->total_responded)->toBe(1)
        ->and($campaign->total_sent)->toBeGreaterThanOrEqual(1);
});

it('mengekspor dan menghapus kampanye tracer study', function () {
    $setup = alumniBatchTwoSetup();
    giveAlumniBatchTwoPermissions([
        'tracer-study-campaign.viewAny', 'tracer-study-campaign.update', 'tracer-study-campaign.delete',
    ]);

    $campaign = alumniBatchTwoCampaign($setup);

    $csv = actingAlumniBatchTwoAdmin($setup['admin'])
        ->get('/admin/alumni/tracer-study-campaigns/export?format=csv')
        ->assertOk();

    expect($csv->headers->get('Content-Type'))->toContain('text/csv')
        ->and($csv->streamedContent())->toContain('Judul', 'Tracer Study Lulusan 2024');

    $xlsx = actingAlumniBatchTwoAdmin($setup['admin'])
        ->get('/admin/alumni/tracer-study-campaigns/export?format=xlsx')
        ->assertOk();

    expect($xlsx->headers->get('Content-Type'))->toContain('spreadsheetml')
        ->and(str_starts_with($xlsx->streamedContent(), 'PK'))->toBeTrue();

    actingAlumniBatchTwoAdmin($setup['admin'])
        ->post('/admin/alumni/tracer-study-campaigns/bulk-destroy', ['ids' => [$campaign->id]])
        ->assertSessionHas('success');

    expect(TracerStudyCampaign::count())->toBe(0)
        ->and(TracerStudyCampaign::onlyTrashed()->count())->toBe(1);

    actingAlumniBatchTwoAdmin($setup['admin'])
        ->post('/admin/alumni/tracer-study-campaigns/bulk-restore', ['ids' => [$campaign->id]])
        ->assertSessionHas('success');

    expect(TracerStudyCampaign::count())->toBe(1);

    actingAlumniBatchTwoAdmin($setup['admin'])
        ->post('/admin/alumni/tracer-study-campaigns/bulk-force-destroy', ['ids' => [$campaign->id]])
        ->assertSessionHas('success');

    expect(TracerStudyCampaign::withTrashed()->count())->toBe(0);
});

it('menghitung statistik respon dengan nilai enum yang benar dan memfilter data', function () {
    $setup = alumniBatchTwoSetup();
    giveAlumniBatchTwoPermissions(['tracer-study-response.viewAny', 'tracer-study-response.view']);

    $campaign = alumniBatchTwoCampaign($setup, ['status' => 'active']);

    $working = alumniBatchTwoProfile($setup, ['full_name' => 'Alumni Bekerja', 'graduation_year' => 2024]);
    $entrepreneur = alumniBatchTwoProfile($setup, ['full_name' => 'Alumni Wirausaha', 'graduation_year' => 2024, 'study_program_id' => $setup['otherProgram']->id]);
    $unemployed = alumniBatchTwoProfile($setup, ['full_name' => 'Alumni Belum Kerja', 'graduation_year' => 2023]);

    TracerStudyResponse::create([
        'tracer_study_campaign_id' => $campaign->id,
        'alumni_profile_id' => $working->id,
        'answers' => ['q_status' => 'Bekerja', 'q_note' => 'Remote'],
        'employment_status' => 'working',
        'employer_name' => 'PT Maju',
        'job_title' => 'Engineer',
        'job_relevance' => 'relevant',
        'time_to_employment_months' => 2,
        'further_study' => false,
        'submitted_at' => now(),
    ]);

    TracerStudyResponse::create([
        'tracer_study_campaign_id' => $campaign->id,
        'alumni_profile_id' => $entrepreneur->id,
        'answers' => ['q_status' => 'Wirausaha'],
        'employment_status' => 'entrepreneur',
        'employer_name' => 'CV Sendiri',
        'job_title' => 'Founder',
        'job_relevance' => 'partially_relevant',
        'time_to_employment_months' => 4,
        'further_study' => true,
        'submitted_at' => now(),
    ]);

    TracerStudyResponse::create([
        'tracer_study_campaign_id' => $campaign->id,
        'alumni_profile_id' => $unemployed->id,
        'answers' => ['q_status' => 'Belum bekerja'],
        'employment_status' => 'unemployed',
        'job_relevance' => 'not_relevant',
        'further_study' => false,
        'submitted_at' => now(),
    ]);

    actingAlumniBatchTwoAdmin($setup['admin'])
        ->get('/admin/alumni/tracer-study-responses')
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('Admin/Alumni/TracerResponse/Index')
            ->where('stats.total', 3)
            ->where('stats.employed', 2)
            ->where('stats.relevant', 1)
            ->where('stats.furtherStudy', 1)
            ->where('stats.avgTimeToEmployment', 3)
            ->has('data.rows', 3));

    actingAlumniBatchTwoAdmin($setup['admin'])
        ->get('/admin/alumni/tracer-study-responses?employment_status=working')
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->where('stats.total', 1)
            ->where('data.rows.0.alumniName', 'Alumni Bekerja'));

    actingAlumniBatchTwoAdmin($setup['admin'])
        ->get('/admin/alumni/tracer-study-responses?study_program_id='.$setup['otherProgram']->id)
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->where('stats.total', 1)
            ->where('data.rows.0.alumniName', 'Alumni Wirausaha'));
});

it('menampilkan detail respon dengan jawaban kuesioner yang terpetakan', function () {
    $setup = alumniBatchTwoSetup();
    giveAlumniBatchTwoPermissions(['tracer-study-response.viewAny', 'tracer-study-response.view']);

    $campaign = alumniBatchTwoCampaign($setup);
    $profile = alumniBatchTwoProfile($setup);

    $response = TracerStudyResponse::create([
        'tracer_study_campaign_id' => $campaign->id,
        'alumni_profile_id' => $profile->id,
        'answers' => ['q_status' => 'Bekerja', 'q_note' => ['Remote', 'Fleksibel']],
        'employment_status' => 'working',
        'job_relevance' => 'relevant',
        'submitted_at' => now(),
    ]);

    actingAlumniBatchTwoAdmin($setup['admin'])
        ->get("/admin/alumni/tracer-study-responses/{$response->id}")
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('Admin/Alumni/TracerResponse/Show')
            ->where('response.alumniName', $profile->full_name)
            ->where('response.employmentStatusLabel', 'Bekerja')
            ->where('response.jobRelevanceLabel', 'Sesuai')
            ->has('answers', 2)
            ->where('answers.0.text', 'Status Anda saat ini?')
            ->where('answers.0.answer', 'Bekerja')
            ->where('answers.1.answer', 'Remote, Fleksibel'));
});

it('mengekspor respon tracer study ke csv, xlsx, dan pdf asli', function () {
    $setup = alumniBatchTwoSetup();
    giveAlumniBatchTwoPermissions(['tracer-study-response.viewAny']);

    $campaign = alumniBatchTwoCampaign($setup);
    $profile = alumniBatchTwoProfile($setup);
    $other = alumniBatchTwoProfile($setup);

    foreach ([$profile, $other] as $index => $alumni) {
        TracerStudyResponse::create([
            'tracer_study_campaign_id' => $campaign->id,
            'alumni_profile_id' => $alumni->id,
            'answers' => ['q_status' => 'Bekerja', 'q_note' => 'Jawaban '.$index],
            'employment_status' => 'working',
            'job_relevance' => 'relevant',
            'submitted_at' => now(),
        ]);
    }

    $csv = actingAlumniBatchTwoAdmin($setup['admin'])
        ->get('/admin/alumni/tracer-study-responses/export?format=csv')
        ->assertOk();

    $csvContent = $csv->streamedContent();

    expect($csvContent)->toContain('No', 'NIM', 'Status Kerja', 'Jawaban 0')
        ->and($csvContent)->toContain($profile->nim);

    $xlsx = actingAlumniBatchTwoAdmin($setup['admin'])
        ->get('/admin/alumni/tracer-study-responses/export?format=xlsx')
        ->assertOk();

    expect($xlsx->headers->get('Content-Type'))->toContain('spreadsheetml')
        ->and(str_starts_with($xlsx->streamedContent(), 'PK'))->toBeTrue();

    $pdf = actingAlumniBatchTwoAdmin($setup['admin'])
        ->get('/admin/alumni/tracer-study-responses/export?format=pdf&campaign='.$campaign->id)
        ->assertOk();

    expect($pdf->headers->get('Content-Type'))->toContain('application/pdf')
        ->and(str_starts_with($pdf->getContent(), '%PDF'))->toBeTrue();
});

it('menyajikan analytics kampanye dengan struktur dan perhitungan yang benar', function () {
    $setup = alumniBatchTwoSetup();
    giveAlumniBatchTwoPermissions([
        'tracer-study-campaign.view', 'tracer-study-response.viewAny', 'tracer-study-campaign.viewAny',
    ]);

    $campaign = alumniBatchTwoCampaign($setup, ['status' => 'active']);
    $campaign->update(['total_sent' => 4, 'total_responded' => 3]);

    $working = alumniBatchTwoProfile($setup, ['graduation_year' => 2024]);
    $entrepreneur = alumniBatchTwoProfile($setup, ['graduation_year' => 2024, 'study_program_id' => $setup['otherProgram']->id]);
    $studying = alumniBatchTwoProfile($setup, ['graduation_year' => 2023]);

    TracerStudyResponse::create([
        'tracer_study_campaign_id' => $campaign->id,
        'alumni_profile_id' => $working->id,
        'answers' => ['q_status' => 'Bekerja'],
        'employment_status' => 'working',
        'job_relevance' => 'relevant',
        'time_to_employment_months' => 2,
        'salary_range' => '5 - 8 juta',
        'further_study' => false,
        'submitted_at' => now(),
    ]);

    TracerStudyResponse::create([
        'tracer_study_campaign_id' => $campaign->id,
        'alumni_profile_id' => $entrepreneur->id,
        'answers' => ['q_status' => 'Wirausaha'],
        'employment_status' => 'entrepreneur',
        'job_relevance' => 'partially_relevant',
        'time_to_employment_months' => 4,
        'further_study' => true,
        'submitted_at' => now(),
    ]);

    TracerStudyResponse::create([
        'tracer_study_campaign_id' => $campaign->id,
        'alumni_profile_id' => $studying->id,
        'answers' => ['q_status' => 'Belum bekerja'],
        'employment_status' => 'studying',
        'job_relevance' => 'not_relevant',
        'time_to_employment_months' => 6,
        'further_study' => true,
        'submitted_at' => now(),
    ]);

    $analytics = app(TracerStudyService::class)->analytics($campaign->fresh());

    expect($analytics['total_responses'])->toBe(3)
        ->and($analytics['response_rate'])->toBe(75.0)
        ->and($analytics['employment_distribution']['working']['label'])->toBe('Bekerja')
        ->and($analytics['employment_distribution']['working']['count'])->toBe(1)
        ->and($analytics['employment_distribution']['working']['percentage'])->toBe(33.3)
        ->and($analytics['job_relevance_breakdown']['relevant']['count'])->toBe(1)
        ->and($analytics['avg_time_to_employment'])->toBe(4.0)
        ->and($analytics['further_study_count'])->toBe(2)
        ->and($analytics['by_study_program'][$setup['program']->name]['responses'])->toBe(2)
        ->and($analytics['by_study_program'][$setup['program']->name]['working'])->toBe(1)
        ->and($analytics['by_study_program'][$setup['otherProgram']->name]['entrepreneur'])->toBe(1)
        ->and($analytics['by_graduation_year'][2024]['responses'])->toBe(2)
        ->and($analytics['salary_distribution']['5 - 8 juta']['count'])->toBe(1);

    actingAlumniBatchTwoAdmin($setup['admin'])
        ->get("/admin/alumni/tracer-study-campaigns/{$campaign->id}/analytics")
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('Admin/Alumni/TracerStudy/Analytics')
            ->where('campaign.id', $campaign->id)
            ->where('campaign.responseRate', 75)
            ->where('analytics.total_responses', 3)
            ->where('analytics.employment_distribution.working.count', 1)
            ->where('analytics.job_relevance_breakdown.relevant.count', 1)
            ->where('analytics.by_study_program.'.$setup['program']->name.'.responses', 2));
});

it('mengekspor analytics kampanye ke xlsx dan pdf', function () {
    $setup = alumniBatchTwoSetup();
    giveAlumniBatchTwoPermissions(['tracer-study-campaign.view']);

    $campaign = alumniBatchTwoCampaign($setup);
    $profile = alumniBatchTwoProfile($setup);

    TracerStudyResponse::create([
        'tracer_study_campaign_id' => $campaign->id,
        'alumni_profile_id' => $profile->id,
        'answers' => ['q_status' => 'Bekerja'],
        'employment_status' => 'working',
        'job_relevance' => 'relevant',
        'submitted_at' => now(),
    ]);

    $xlsx = actingAlumniBatchTwoAdmin($setup['admin'])
        ->get("/admin/alumni/tracer-study-campaigns/{$campaign->id}/export/xlsx")
        ->assertOk();

    expect($xlsx->headers->get('Content-Type'))->toContain('spreadsheetml')
        ->and(str_starts_with($xlsx->streamedContent(), 'PK'))->toBeTrue();

    $pdf = actingAlumniBatchTwoAdmin($setup['admin'])
        ->get("/admin/alumni/tracer-study-campaigns/{$campaign->id}/export/pdf")
        ->assertOk();

    expect($pdf->headers->get('Content-Type'))->toContain('application/pdf')
        ->and(str_starts_with($pdf->getContent(), '%PDF'))->toBeTrue();

    $csv = actingAlumniBatchTwoAdmin($setup['admin'])
        ->get("/admin/alumni/tracer-study-campaigns/{$campaign->id}/export/csv")
        ->assertOk();

    expect($csv->headers->get('Content-Type'))->toContain('text/csv')
        ->and($csv->streamedContent())->toContain('NIM', $profile->nim);
});
