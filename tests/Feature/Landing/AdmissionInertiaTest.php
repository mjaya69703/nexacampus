<?php

use App\Mail\AdmissionApplicationSubmitted;
use App\Models\Academic\AcademicYear;
use App\Models\Academic\Faculty;
use App\Models\Academic\StudentProfile;
use App\Models\Academic\StudyProgram;
use App\Models\Admission\AdmissionApplication;
use App\Models\Admission\AdmissionDocumentRequirement;
use App\Models\Admission\AdmissionPeriod;
use App\Models\Financial\TuitionFee;
use App\Models\Publication\Faq;
use App\Models\User;
use App\Support\Student\DigitalStudentIdService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Testing\File;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;

uses(RefreshDatabase::class);

function ensureSystemInstalled(): void
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
}

function seedAdmissionData(): array
{
    $faculty = Faculty::create(['name' => 'Fakultas Teknologi', 'code' => 'FT', 'is_active' => true]);
    $program = StudyProgram::create([
        'faculty_id' => $faculty->id,
        'name' => 'Teknik Informatika',
        'code' => 'TI',
        'degree' => 'S1',
        'is_active' => true,
    ]);

    $period = AdmissionPeriod::create([
        'name' => 'Gelombang 1 2026',
        'code' => 'ADM2026W1',
        'academic_year' => 2026,
        'wave' => 1,
        'opens_at' => now()->subDays(7)->toDateString(),
        'closes_at' => now()->addDays(30)->toDateString(),
        'is_active' => true,
        'is_published' => true,
    ]);

    AdmissionDocumentRequirement::create([
        'admission_period_id' => $period->id,
        'document_type' => 'photo',
        'label' => 'Pas Foto',
        'is_required' => true,
        'allowed_extensions' => 'jpg,png',
        'max_size_kb' => 2048,
    ]);

    return [$faculty, $program, $period];
}

it('renders all public admission pages through Inertia', function () {
    $this->withoutMiddleware();
    [, $program] = seedAdmissionData();

    $academicYear = AcademicYear::create([
        'name' => '2026/2027',
        'code' => '2627',
        'semester' => 'Ganjil',
        'start_date' => '2026-08-01',
        'end_date' => '2027-07-31',
    ]);
    TuitionFee::create([
        'academic_year_id' => $academicYear->id,
        'study_program_id' => $program->id,
        'semester' => 1,
        'base_fee' => 5000000,
        'payment_deadline' => now()->addMonth()->toDateString(),
    ]);

    Faq::create([
        'type' => 'admission',
        'category' => 'Pendaftaran',
        'question' => 'Bagaimana cara mendaftar?',
        'answer' => 'Isi formulir pendaftaran online.',
    ]);

    $pages = [
        '/admission/apply' => 'Home/Admission/Apply',
        '/admission/status' => 'Home/Admission/Status',
        '/admission/requirements' => 'Home/Admission/Requirements',
        '/admission/tuition' => 'Home/Admission/Tuition',
        '/admission/faq' => 'Home/Admission/Faq',
    ];

    foreach ($pages as $url => $component) {
        $this->get($url)
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component($component)
                ->has('campus')
                ->has('links')
                ->has('user'));
    }
});

it('renders the applicant portal through Inertia', function () {
    $this->withoutMiddleware();
    [, , $period] = seedAdmissionData();

    $application = AdmissionApplication::create([
        'admission_period_id' => $period->id,
        'application_number' => 'ADM2026W1-0001',
        'access_token' => str_repeat('a', 48),
        'full_name' => 'Ahmad Rizki Pratama',
        'email' => 'ahmad@example.com',
        'phone' => '081234567890',
        'birth_date' => '2005-05-10',
        'gender' => 'male',
        'address' => 'Jl. Pendidikan No. 1',
        'emergency_contact_name' => 'Budi',
        'emergency_contact_phone' => '081298765432',
        'high_school_name' => 'SMAN 1 Jakarta',
        'high_school_major' => 'IPA',
        'high_school_graduation_year' => 2024,
        'status' => 'submitted',
        'submitted_at' => now(),
    ]);

    $this->get(route('root.admission.portal', ['applicationNumber' => $application->application_number, 'token' => $application->access_token]))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('Home/Admission/Portal')
            ->has('application.applicationNumber')
            ->has('requirements')
            ->has('documents')
            ->where('application.fullName', 'Ahmad Rizki Pratama'));
});

it('aborts the applicant portal for an invalid token', function () {
    $this->withoutMiddleware();
    seedAdmissionData();

    $this->get('/admission/applications/ADM2026W1-0001/wrong-token')->assertNotFound();
});

it('renders the digital student id verification page through Inertia', function () {
    ensureSystemInstalled();
    [, $program] = seedAdmissionData();

    $user = User::factory()->create([
        'first_name' => 'Ahmad Rizki',
        'last_name' => 'Pratama',
        'email' => 'ahmad@example.com',
    ]);

    $studentProfile = StudentProfile::create([
        'user_id' => $user->id,
        'study_program_id' => $program->id,
        'nim' => '2610001',
        'academic_status' => 'Aktif',
        'is_active' => true,
    ]);

    $service = app(DigitalStudentIdService::class);

    $this->get($service->verificationUrl($studentProfile))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('Home/DigitalIdVerify')
            ->where('student.nim', '2610001')
            ->where('student.isActive', true));
});

it('aborts digital student id verification for an invalid token', function () {
    ensureSystemInstalled();
    [, $program] = seedAdmissionData();

    $user = User::factory()->create([
        'first_name' => 'Ahmad Rizki',
        'last_name' => 'Pratama',
        'email' => 'ahmad2@example.com',
    ]);

    $studentProfile = StudentProfile::create([
        'user_id' => $user->id,
        'study_program_id' => $program->id,
        'nim' => '2610002',
    ]);

    $this->get("/student-id/verify/{$studentProfile->id}/invalid-token")->assertNotFound();
});

it('redirects a matching status check to the applicant portal', function () {
    $this->withoutMiddleware();
    [, , $period] = seedAdmissionData();

    AdmissionApplication::create([
        'admission_period_id' => $period->id,
        'application_number' => 'ADM2026W1-0001',
        'access_token' => str_repeat('a', 48),
        'full_name' => 'Ahmad Rizki Pratama',
        'email' => 'ahmad@example.com',
        'phone' => '081234567890',
        'birth_date' => '2005-05-10',
        'gender' => 'male',
        'address' => 'Jl. Pendidikan No. 1',
        'emergency_contact_name' => 'Budi',
        'emergency_contact_phone' => '081298765432',
        'high_school_name' => 'SMAN 1 Jakarta',
        'high_school_major' => 'IPA',
        'high_school_graduation_year' => 2024,
        'status' => 'submitted',
        'submitted_at' => now(),
    ]);

    $response = $this->post(route('root.admission.check'), [
        'applicationNumber' => 'ADM2026W1-0001',
        'email' => 'ahmad@example.com',
    ]);

    $response->assertRedirect(route('root.admission.portal', [
        'applicationNumber' => 'ADM2026W1-0001',
        'token' => str_repeat('a', 48),
    ]));
});

it('stores a public application and opens its portal', function () {
    $this->withoutMiddleware();
    Mail::fake();
    [, $program, $period] = seedAdmissionData();

    $payload = [
        'fullName' => 'Ahmad Rizki Pratama',
        'email' => 'ahmad@example.com',
        'phone' => '081234567890',
        'birthDate' => '2005-05-10',
        'gender' => 'male',
        'address' => 'Jl. Pendidikan No. 1',
        'emergencyContactName' => 'Budi',
        'emergencyContactPhone' => '081298765432',
        'highSchoolName' => 'SMAN 1 Jakarta',
        'highSchoolMajor' => 'IPA',
        'highSchoolGraduationYear' => 2024,
        'studyProgramId' => $program->id,
        'classType' => 'regular',
        'documents' => ['photo' => File::fake()->image('pas-foto.png')],
    ];

    $response = $this->post(route('root.admission.store'), $payload);

    $application = AdmissionApplication::query()->where('email', 'ahmad@example.com')->firstOrFail();

    expect($application->full_name)->toBe('Ahmad Rizki Pratama')
        ->and($application->status)->toBe('submitted')
        ->and($application->admission_period_id)->toBe($period->id)
        ->and($application->documents()->count())->toBe(1);

    $response->assertRedirect(route('root.admission.portal', [
        'applicationNumber' => $application->application_number,
        'token' => $application->access_token,
    ]));

    Mail::assertSent(AdmissionApplicationSubmitted::class);
});
