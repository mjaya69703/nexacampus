<?php

use App\Models\Academic\AcademicPeriod;
use App\Models\Academic\AcademicYear;
use App\Models\Academic\Faculty;
use App\Models\Academic\StudentProfile;
use App\Models\Academic\StudyProgram;
use App\Models\Academic\StudyResult;
use App\Models\Alumni\AlumniProfile;
use App\Models\Alumni\EmployerPartner;
use App\Models\Alumni\JobPosting;
use App\Models\Settings\System;
use App\Models\StudentService\GraduationApplication;
use App\Models\StudentService\GraduationBatch;
use App\Models\User;
use App\Support\Alumni\AlumniConversionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Spatie\Permission\Models\Permission as SpatiePermission;
use Spatie\Permission\Models\Role as SpatieRole;

uses(RefreshDatabase::class);

function alumniBatchOneSetup(): array
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

    return compact('admin', 'faculty', 'program');
}

function giveAlumniBatchOnePermissions(array $names): void
{
    $role = SpatieRole::where('name', 'admin')->firstOrFail();

    foreach ($names as $name) {
        $role->givePermissionTo(SpatiePermission::firstOrCreate([
            'name' => $name,
            'guard_name' => 'web',
        ]));
    }
}

function actingAlumniBatchOneAdmin(User $admin)
{
    return test()->actingAs($admin)->withSession(['active_role' => 'admin']);
}

function alumniBatchOneProfilePayload(array $setup, array $overrides = []): array
{
    return array_merge([
        'nim' => '2020010001',
        'full_name' => 'Budi Alumni',
        'graduation_date' => '2024-08-31',
        'graduation_year' => 2024,
        'faculty_id' => $setup['faculty']->id,
        'study_program_id' => $setup['program']->id,
        'gpa' => 3.45,
        'birth_date' => '2000-01-01',
        'gender' => 'L',
        'email' => 'budi.alumni@example.test',
        'phone' => '081234567890',
        'address' => 'Jl. Alumni No. 1',
        'current_city' => 'Bandung',
        'current_province' => 'Jawa Barat',
        'employment_status' => 'working',
        'employer_name' => 'PT Contoh Digital',
        'job_title' => 'Software Engineer',
        'job_industry' => 'Teknologi Informasi',
        'linkedin_url' => 'https://linkedin.com/in/budi-alumni',
        'is_active' => true,
    ], $overrides);
}

function alumniBatchOneProfile(array $setup, array $overrides = []): AlumniProfile
{
    $payload = alumniBatchOneProfilePayload($setup, $overrides);

    return AlumniProfile::create([
        ...$payload,
        'employment_status' => $payload['employment_status'] ?? 'unemployed',
    ]);
}

function alumniBatchOneJobPayload(EmployerPartner $partner, array $overrides = []): array
{
    return array_merge([
        'employer_partner_id' => $partner->id,
        'title' => 'Backend Engineer',
        'company_name' => '',
        'industry' => '',
        'description' => 'Membangun layanan backend untuk produk alumni.',
        'requirements' => 'Menguasai PHP dan SQL.',
        'location' => 'Jakarta',
        'job_type' => 'full-time',
        'salary_range' => '8 - 12 juta',
        'apply_url' => 'https://example.test/careers/backend',
        'contact_email' => 'hr@example.test',
        'posted_date' => now()->toDateString(),
        'deadline_date' => now()->addDays(14)->toDateString(),
        'is_active' => true,
    ], $overrides);
}

it('menampilkan statistik profil alumni dengan status kerja yang benar', function () {
    $setup = alumniBatchOneSetup();
    giveAlumniBatchOnePermissions(['alumni-profile.viewAny']);

    alumniBatchOneProfile($setup, [
        'nim' => '2020010001',
        'employment_status' => 'working',
        'email' => 'budi@example.test',
    ]);
    alumniBatchOneProfile($setup, [
        'nim' => '2020010002',
        'full_name' => 'Siti Alumni',
        'email' => 'siti@example.test',
        'employment_status' => 'unemployed',
        'is_active' => false,
    ]);

    actingAlumniBatchOneAdmin($setup['admin'])
        ->get('/admin/alumni/alumni-profiles')
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('Admin/Alumni/Profile/Index')
            ->where('stats.total', 2)
            ->where('stats.active', 1)
            ->where('stats.working', 1)
            ->where('stats.entrepreneur', 0)
            ->where('stats.trashed', 0)
            ->where('employmentStatusOptions.working', 'Bekerja')
            ->has('data.rows', 2));
});

it('menyimpan profil alumni dan menolak program studi yang tidak sesuai fakultas', function () {
    $setup = alumniBatchOneSetup();
    giveAlumniBatchOnePermissions(['alumni-profile.viewAny', 'alumni-profile.create']);

    actingAlumniBatchOneAdmin($setup['admin'])
        ->post('/admin/alumni/alumni-profiles', alumniBatchOneProfilePayload($setup))
        ->assertRedirect('/admin/alumni/alumni-profiles')
        ->assertSessionHas('success');

    $profile = AlumniProfile::where('nim', '2020010001')->firstOrFail();

    expect($profile->phone)->toBe('081234567890')
        ->and($profile->faculty_id)->toBe($setup['faculty']->id)
        ->and($profile->study_program_id)->toBe($setup['program']->id)
        ->and($profile->employment_status)->toBe('working')
        ->and($profile->created_by)->toBe($setup['admin']->id);

    actingAlumniBatchOneAdmin($setup['admin'])
        ->post('/admin/alumni/alumni-profiles', alumniBatchOneProfilePayload($setup, [
            'email' => 'duplikat@example.test',
        ]))
        ->assertSessionHasErrors('nim');

    $otherFaculty = Faculty::create([
        'name' => 'Fakultas Ekonomi',
        'code' => 'FE',
        'is_active' => true,
    ]);
    $otherProgram = StudyProgram::create([
        'faculty_id' => $otherFaculty->id,
        'name' => 'Manajemen',
        'code' => 'MJ',
        'degree' => 'S1',
        'is_active' => true,
    ]);

    actingAlumniBatchOneAdmin($setup['admin'])
        ->post('/admin/alumni/alumni-profiles', alumniBatchOneProfilePayload($setup, [
            'nim' => '2020010003',
            'email' => 'salah@example.test',
            'faculty_id' => $setup['faculty']->id,
            'study_program_id' => $otherProgram->id,
        ]))
        ->assertSessionHasErrors('study_program_id');

    actingAlumniBatchOneAdmin($setup['admin'])
        ->post('/admin/alumni/alumni-profiles', alumniBatchOneProfilePayload($setup, [
            'nim' => '2020010004',
            'email' => 'tanpa-fakultas@example.test',
            'faculty_id' => '',
        ]))
        ->assertSessionHasErrors('faculty_id');
});

it('memperbarui profil, mengosongkan kolom opsional, dan menghapus foto', function () {
    $setup = alumniBatchOneSetup();
    giveAlumniBatchOnePermissions(['alumni-profile.viewAny', 'alumni-profile.update']);

    Storage::fake('public');
    Storage::disk('public')->put('alumni/photos/lama.jpg', 'foto-lama');

    $profile = alumniBatchOneProfile($setup, [
        'photo_path' => 'alumni/photos/lama.jpg',
    ]);

    actingAlumniBatchOneAdmin($setup['admin'])
        ->put("/admin/alumni/alumni-profiles/{$profile->id}", alumniBatchOneProfilePayload($setup, [
            'full_name' => 'Budi Alumni Diperbarui',
            'phone' => '',
            'address' => '',
            'current_city' => '',
            'current_province' => '',
            'employer_name' => '',
            'job_title' => '',
            'job_industry' => '',
            'linkedin_url' => '',
            'gender' => '',
            'birth_date' => '',
            'gpa' => '',
            'remove_photo' => true,
        ]))
        ->assertRedirect('/admin/alumni/alumni-profiles')
        ->assertSessionHas('success');

    $profile->refresh();

    expect($profile->full_name)->toBe('Budi Alumni Diperbarui')
        ->and($profile->phone)->toBeNull()
        ->and($profile->address)->toBeNull()
        ->and($profile->current_city)->toBeNull()
        ->and($profile->employer_name)->toBeNull()
        ->and($profile->linkedin_url)->toBeNull()
        ->and($profile->gender)->toBeNull()
        ->and($profile->birth_date)->toBeNull()
        ->and($profile->gpa)->toBeNull()
        ->and($profile->photo_path)->toBeNull();

    Storage::disk('public')->assertMissing('alumni/photos/lama.jpg');
});

it('mengubah status, menghapus, memulihkan, dan menghapus permanen profil alumni', function () {
    $setup = alumniBatchOneSetup();
    giveAlumniBatchOnePermissions([
        'alumni-profile.viewAny', 'alumni-profile.update', 'alumni-profile.delete',
    ]);

    $profile = alumniBatchOneProfile($setup);

    actingAlumniBatchOneAdmin($setup['admin'])
        ->post("/admin/alumni/alumni-profiles/{$profile->id}/toggle", ['is_active' => false])
        ->assertSessionHas('success');

    expect($profile->refresh()->is_active)->toBeFalse();

    actingAlumniBatchOneAdmin($setup['admin'])
        ->delete("/admin/alumni/alumni-profiles/{$profile->id}")
        ->assertRedirect('/admin/alumni/alumni-profiles')
        ->assertSessionHas('success');

    expect(AlumniProfile::find($profile->id))->toBeNull()
        ->and(AlumniProfile::withTrashed()->find($profile->id))->not->toBeNull();

    actingAlumniBatchOneAdmin($setup['admin'])
        ->get('/admin/alumni/alumni-profiles?mode=trash')
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->where('stats.trashed', 1)
            ->has('data.rows', 1)
            ->where('filters.mode', 'trash'));

    actingAlumniBatchOneAdmin($setup['admin'])
        ->post("/admin/alumni/alumni-profiles/{$profile->id}/restore")
        ->assertSessionHas('success');

    expect(AlumniProfile::find($profile->id))->not->toBeNull();

    actingAlumniBatchOneAdmin($setup['admin'])
        ->delete("/admin/alumni/alumni-profiles/{$profile->id}")
        ->assertRedirect('/admin/alumni/alumni-profiles');

    actingAlumniBatchOneAdmin($setup['admin'])
        ->delete("/admin/alumni/alumni-profiles/{$profile->id}/force")
        ->assertSessionHas('success');

    expect(AlumniProfile::withTrashed()->find($profile->id))->toBeNull();
});

it('menghapus massal, memulihkan massal, dan mengekspor profil alumni', function () {
    $setup = alumniBatchOneSetup();
    giveAlumniBatchOnePermissions([
        'alumni-profile.viewAny', 'alumni-profile.update', 'alumni-profile.delete',
    ]);

    $first = alumniBatchOneProfile($setup, ['nim' => '2020010011', 'email' => 'satu@example.test']);
    $second = alumniBatchOneProfile($setup, ['nim' => '2020010012', 'email' => 'dua@example.test']);

    actingAlumniBatchOneAdmin($setup['admin'])
        ->post('/admin/alumni/alumni-profiles/bulk-destroy', ['ids' => [$first->id, $second->id]])
        ->assertSessionHas('success');

    expect(AlumniProfile::count())->toBe(0)
        ->and(AlumniProfile::onlyTrashed()->count())->toBe(2);

    actingAlumniBatchOneAdmin($setup['admin'])
        ->post('/admin/alumni/alumni-profiles/bulk-restore', ['ids' => [$first->id, $second->id]])
        ->assertSessionHas('success');

    expect(AlumniProfile::count())->toBe(2);

    $response = actingAlumniBatchOneAdmin($setup['admin'])
        ->get('/admin/alumni/alumni-profiles/export?format=csv')
        ->assertOk();

    expect($response->headers->get('Content-Type'))->toContain('text/csv')
        ->and($response->streamedContent())->toContain('NIM', 'Budi Alumni');

    actingAlumniBatchOneAdmin($setup['admin'])
        ->post('/admin/alumni/alumni-profiles/bulk-force-destroy', ['ids' => [$first->id, $second->id]])
        ->assertSessionHas('success');

    expect(AlumniProfile::withTrashed()->count())->toBe(0);
});

it('mengelola mitra perusahaan termasuk logo, status, sampah, dan ekspor', function () {
    $setup = alumniBatchOneSetup();
    giveAlumniBatchOnePermissions([
        'employer-partner.viewAny', 'employer-partner.create',
        'employer-partner.update', 'employer-partner.delete',
    ]);

    Storage::fake('public');

    actingAlumniBatchOneAdmin($setup['admin'])
        ->get('/admin/alumni/employer-partners')
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('Admin/Alumni/Employer/Index')
            ->where('stats.total', 0)
            ->has('data.rows', 0));

    actingAlumniBatchOneAdmin($setup['admin'])
        ->post('/admin/alumni/employer-partners', [
            'name' => 'PT Mitra Digital',
            'industry' => 'Teknologi Informasi',
            'website' => 'https://mitra.example.test',
            'contact_person' => 'Rina HR',
            'contact_email' => 'hr@mitra.example.test',
            'contact_phone' => '0211234567',
            'address' => 'Jl. Mitra No. 2',
            'city' => 'Jakarta',
            'province' => 'DKI Jakarta',
            'description' => 'Mitra rekrutmen alumni.',
            'is_active' => true,
        ])
        ->assertRedirect('/admin/alumni/employer-partners')
        ->assertSessionHas('success');

    $partner = EmployerPartner::where('name', 'PT Mitra Digital')->firstOrFail();

    Storage::disk('public')->put('employer-partners/logos/lama.png', 'logo-lama');
    $partner->update(['logo_path' => 'employer-partners/logos/lama.png']);

    actingAlumniBatchOneAdmin($setup['admin'])
        ->put("/admin/alumni/employer-partners/{$partner->id}", [
            'name' => 'PT Mitra Digital Baru',
            'industry' => '',
            'website' => '',
            'contact_person' => '',
            'contact_email' => '',
            'contact_phone' => '',
            'address' => '',
            'city' => '',
            'province' => '',
            'description' => '',
            'is_active' => false,
            'remove_logo' => true,
        ])
        ->assertRedirect('/admin/alumni/employer-partners')
        ->assertSessionHas('success');

    $partner->refresh();

    expect($partner->name)->toBe('PT Mitra Digital Baru')
        ->and($partner->industry)->toBeNull()
        ->and($partner->website)->toBeNull()
        ->and($partner->contact_person)->toBeNull()
        ->and($partner->is_active)->toBeFalse()
        ->and($partner->logo_path)->toBeNull();

    Storage::disk('public')->assertMissing('employer-partners/logos/lama.png');

    actingAlumniBatchOneAdmin($setup['admin'])
        ->post("/admin/alumni/employer-partners/{$partner->id}/toggle", ['is_active' => true])
        ->assertSessionHas('success');

    expect($partner->refresh()->is_active)->toBeTrue();

    $response = actingAlumniBatchOneAdmin($setup['admin'])
        ->get('/admin/alumni/employer-partners/export?format=csv')
        ->assertOk();

    expect($response->headers->get('Content-Type'))->toContain('text/csv')
        ->and($response->streamedContent())->toContain('Nama Mitra', 'PT Mitra Digital Baru');

    actingAlumniBatchOneAdmin($setup['admin'])
        ->delete("/admin/alumni/employer-partners/{$partner->id}")
        ->assertRedirect('/admin/alumni/employer-partners');

    actingAlumniBatchOneAdmin($setup['admin'])
        ->get('/admin/alumni/employer-partners?mode=trash')
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->where('stats.trashed', 1)
            ->has('data.rows', 1));

    actingAlumniBatchOneAdmin($setup['admin'])
        ->post("/admin/alumni/employer-partners/{$partner->id}/restore")
        ->assertSessionHas('success');

    actingAlumniBatchOneAdmin($setup['admin'])
        ->delete("/admin/alumni/employer-partners/{$partner->id}")
        ->assertRedirect('/admin/alumni/employer-partners');

    actingAlumniBatchOneAdmin($setup['admin'])
        ->delete("/admin/alumni/employer-partners/{$partner->id}/force")
        ->assertSessionHas('success');

    expect(EmployerPartner::withTrashed()->find($partner->id))->toBeNull();
});

it('membuat lowongan dengan perusahaan dari mitra dan deadline yang benar', function () {
    $setup = alumniBatchOneSetup();
    giveAlumniBatchOnePermissions([
        'job-posting.viewAny', 'job-posting.create', 'job-posting.update', 'job-posting.delete',
    ]);

    $partner = EmployerPartner::create([
        'name' => 'PT Mitra Karir',
        'industry' => 'Teknologi Informasi',
        'is_active' => true,
    ]);

    $deadline = now()->addDays(14);

    actingAlumniBatchOneAdmin($setup['admin'])
        ->post('/admin/alumni/job-postings', alumniBatchOneJobPayload($partner, [
            'deadline_date' => $deadline->toDateString(),
        ]))
        ->assertRedirect('/admin/alumni/job-postings')
        ->assertSessionHas('success');

    $job = JobPosting::where('title', 'Backend Engineer')->firstOrFail();

    expect($job->company_name)->toBe('PT Mitra Karir')
        ->and($job->industry)->toBe('Teknologi Informasi')
        ->and($job->deadline_date->toDateString())->toBe($deadline->toDateString())
        ->and($job->employer_partner_id)->toBe($partner->id);

    actingAlumniBatchOneAdmin($setup['admin'])
        ->get('/admin/alumni/job-postings')
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('Admin/Alumni/Job/Index')
            ->where('stats.total', 1)
            ->where('stats.fullTime', 1)
            ->where('stats.expired', 0)
            ->where('data.rows.0.companyName', 'PT Mitra Karir')
            ->where('data.rows.0.deadlineDate', $deadline->format('d M Y')));

    actingAlumniBatchOneAdmin($setup['admin'])
        ->post('/admin/alumni/job-postings', [
            ...alumniBatchOneJobPayload($partner),
            'title' => 'Tanpa Perusahaan',
            'employer_partner_id' => '',
            'company_name' => '',
        ])
        ->assertSessionHasErrors('company_name');

    actingAlumniBatchOneAdmin($setup['admin'])
        ->post('/admin/alumni/job-postings', alumniBatchOneJobPayload($partner, [
            'title' => 'Deadline Terbalik',
            'posted_date' => '2026-08-10',
            'deadline_date' => '2026-08-01',
        ]))
        ->assertSessionHasErrors('deadline_date');
});

it('memfilter lowongan berdasarkan masa berlaku dan tipe pekerjaan', function () {
    $setup = alumniBatchOneSetup();
    giveAlumniBatchOnePermissions(['job-posting.viewAny']);

    $partner = EmployerPartner::create([
        'name' => 'PT Mitra Karir',
        'industry' => 'Teknologi Informasi',
        'is_active' => true,
    ]);

    JobPosting::create(alumniBatchOneJobPayload($partner, [
        'title' => 'Lowongan Terbuka',
        'posted_date' => now()->subDays(5)->toDateString(),
        'deadline_date' => now()->addDays(10)->toDateString(),
    ]));

    JobPosting::create(alumniBatchOneJobPayload($partner, [
        'title' => 'Lowongan Kedaluwarsa',
        'job_type' => 'internship',
        'posted_date' => now()->subDays(30)->toDateString(),
        'deadline_date' => now()->subDays(10)->toDateString(),
    ]));

    actingAlumniBatchOneAdmin($setup['admin'])
        ->get('/admin/alumni/job-postings?validity=expired')
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->where('stats.expired', 1)
            ->has('data.rows', 1)
            ->where('data.rows.0.title', 'Lowongan Kedaluwarsa'));

    actingAlumniBatchOneAdmin($setup['admin'])
        ->get('/admin/alumni/job-postings?validity=open')
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->has('data.rows', 1)
            ->where('data.rows.0.title', 'Lowongan Terbuka'));

    actingAlumniBatchOneAdmin($setup['admin'])
        ->get('/admin/alumni/job-postings?job_type=internship')
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->has('data.rows', 1)
            ->where('data.rows.0.jobTypeLabel', 'Magang'));
});

it('mengubah status, menghapus, memulihkan, dan mengekspor lowongan', function () {
    $setup = alumniBatchOneSetup();
    giveAlumniBatchOnePermissions([
        'job-posting.viewAny', 'job-posting.update', 'job-posting.delete',
    ]);

    $partner = EmployerPartner::create([
        'name' => 'PT Mitra Karir',
        'is_active' => true,
    ]);

    $job = JobPosting::create(alumniBatchOneJobPayload($partner));

    actingAlumniBatchOneAdmin($setup['admin'])
        ->post("/admin/alumni/job-postings/{$job->id}/toggle", ['is_active' => false])
        ->assertSessionHas('success');

    expect($job->refresh()->is_active)->toBeFalse();

    $response = actingAlumniBatchOneAdmin($setup['admin'])
        ->get('/admin/alumni/job-postings/export?format=csv')
        ->assertOk();

    expect($response->headers->get('Content-Type'))->toContain('text/csv')
        ->and($response->streamedContent())->toContain('Judul', 'Backend Engineer');

    actingAlumniBatchOneAdmin($setup['admin'])
        ->delete("/admin/alumni/job-postings/{$job->id}")
        ->assertRedirect('/admin/alumni/job-postings');

    actingAlumniBatchOneAdmin($setup['admin'])
        ->get('/admin/alumni/job-postings?mode=trash')
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->where('stats.trashed', 1)
            ->has('data.rows', 1));

    actingAlumniBatchOneAdmin($setup['admin'])
        ->post("/admin/alumni/job-postings/{$job->id}/restore")
        ->assertSessionHas('success');

    expect(JobPosting::find($job->id))->not->toBeNull();

    actingAlumniBatchOneAdmin($setup['admin'])
        ->delete("/admin/alumni/job-postings/{$job->id}")
        ->assertRedirect('/admin/alumni/job-postings');

    actingAlumniBatchOneAdmin($setup['admin'])
        ->delete("/admin/alumni/job-postings/{$job->id}/force")
        ->assertSessionHas('success');

    expect(JobPosting::withTrashed()->find($job->id))->toBeNull();
});

it('mengonversi yudisium final menjadi alumni secara idempoten dan memulihkan profil terhapus', function () {
    $setup = alumniBatchOneSetup();
    giveAlumniBatchOnePermissions(['alumni-profile.create']);

    $year = AcademicYear::create([
        'name' => '2026/2027 Ganjil',
        'code' => 'Y26G',
        'semester' => 'Ganjil',
        'start_date' => '2026-08-01',
        'end_date' => '2027-01-31',
        'is_active' => true,
    ]);

    $period = AcademicPeriod::create([
        'academic_year_id' => $year->id,
        'name' => 'Yudisium Agustus 2026',
        'code' => 'YUD26',
        'type' => 'Yudisium',
        'start_at' => '2026-08-01 00:00:00',
        'end_at' => '2026-08-31 23:59:59',
        'is_active' => true,
    ]);

    $batch = GraduationBatch::create([
        'academic_period_id' => $period->id,
        'name' => 'Batch Yudisium 1',
        'code' => 'GB-1',
        'yudisium_date' => '2026-08-31',
        'status' => 'finalized',
    ]);

    $studentUser = User::factory()->create([
        'first_name' => 'Siti',
        'last_name' => 'Alumni',
        'gender' => 'Perempuan',
        'date_of_birth' => '2002-02-02',
        'phone' => '081298765432',
        'email' => 'siti.alumni@example.test',
    ]);

    $studentProfile = StudentProfile::create([
        'user_id' => $studentUser->id,
        'study_program_id' => $setup['program']->id,
        'nim' => '2020010099',
        'entry_year' => 2020,
        'academic_status' => 'Lulus',
        'is_active' => false,
    ]);

    StudyResult::create([
        'student_profile_id' => $studentProfile->id,
        'academic_year_id' => $year->id,
        'semester_no' => 8,
        'cumulative_gpa' => 3.67,
    ]);

    $application = GraduationApplication::create([
        'application_number' => 'YUD-2026-0001',
        'student_profile_id' => $studentProfile->id,
        'academic_period_id' => $period->id,
        'graduation_batch_id' => $batch->id,
        'status' => 'finalized',
        'graduation_date' => null,
    ]);

    $endpoint = '/admin/alumni/alumni-profiles/batch-convert';

    actingAlumniBatchOneAdmin($setup['admin'])
        ->post($endpoint, ['graduation_batch_id' => $batch->id])
        ->assertRedirect('/admin/alumni/alumni-profiles')
        ->assertSessionHas('success', fn (string $message) => str_contains($message, '1 alumni berhasil dikonversi'));

    $profile = AlumniProfile::where('nim', '2020010099')->firstOrFail();

    expect($profile->user_id)->toBe($studentUser->id)
        ->and($profile->student_profile_id)->toBe($studentProfile->id)
        ->and($profile->faculty_id)->toBe($setup['faculty']->id)
        ->and($profile->study_program_id)->toBe($setup['program']->id)
        ->and($profile->gender)->toBe('P')
        ->and($profile->birth_date->toDateString())->toBe('2002-02-02')
        ->and($profile->phone)->toBe('081298765432')
        ->and($profile->gpa)->toBe('3.67')
        ->and($profile->employment_status)->toBe('unemployed')
        ->and($profile->graduation_date->toDateString())->toBe('2026-08-31')
        ->and($profile->graduation_year)->toBe(2026);

    actingAlumniBatchOneAdmin($setup['admin'])
        ->post($endpoint, ['graduation_batch_id' => $batch->id])
        ->assertSessionHas('success', fn (string $message) => str_contains($message, '1 dilewati'));

    expect(AlumniProfile::where('nim', '2020010099')->count())->toBe(1);

    $profile->delete();

    actingAlumniBatchOneAdmin($setup['admin'])
        ->post($endpoint, ['graduation_batch_id' => $batch->id])
        ->assertSessionHas('success', fn (string $message) => str_contains($message, '1 alumni berhasil dikonversi'));

    $restored = AlumniProfile::where('nim', '2020010099')->first();

    expect($restored)->not->toBeNull()
        ->and($restored->deleted_at)->toBeNull();

    $application->update(['status' => 'submitted']);

    expect(fn () => app(AlumniConversionService::class)
        ->convertFromGraduation($application->fresh()))
        ->toThrow(RuntimeException::class);
});
