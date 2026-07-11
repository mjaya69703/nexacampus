<?php

use App\Models\Academic\AcademicYear;
use App\Models\Academic\Faculty;
use App\Models\Academic\StudentProfile;
use App\Models\Academic\StudyProgram;
use App\Models\Access\Role;
use App\Models\Admission\AdmissionApplication;
use App\Models\Admission\AdmissionPeriod;
use App\Models\Admission\NimGenerationRule;
use App\Models\User;
use App\Support\Admission\AdmissionConversionService;
use App\Support\Admission\NimGenerationService;
use Illuminate\Support\Str;

function admissionTestSetup(): array
{
    Role::firstOrCreate(['name' => 'student', 'guard_name' => 'web']);
    $admin = User::factory()->create();
    $faculty = Faculty::create(['name' => 'Fakultas Teknik', 'code' => 'FT', 'is_active' => true]);
    $program = StudyProgram::create(['faculty_id' => $faculty->id, 'name' => 'Sistem Informasi', 'code' => 'SI', 'degree' => 'S1', 'is_active' => true]);
    $academicYear = AcademicYear::create(['name' => '2026/2027 Ganjil', 'code' => '2026G', 'semester' => 'Ganjil', 'start_date' => '2026-08-01', 'end_date' => '2026-12-31', 'is_active' => true]);
    $period = AdmissionPeriod::create([
        'name' => 'PMB Gelombang 1 2026',
        'code' => 'PMB2026-G1',
        'academic_year_id' => $academicYear->id,
        'academic_year' => 2026,
        'wave' => 1,
        'opens_at' => '2026-01-01',
        'closes_at' => '2026-06-30',
        'is_active' => true,
        'is_published' => true,
        'created_by' => $admin->id,
        'updated_by' => $admin->id,
    ]);

    $rule = NimGenerationRule::create([
        'name' => 'Aturan NIM Standar 2026',
        'pattern' => '{year}{program_code}{sequence}',
        'sequence_scope' => 'study_program',
        'sequence_padding' => 4,
        'sequence_start' => 1,
        'is_active' => true,
        'created_by' => $admin->id,
        'updated_by' => $admin->id,
    ]);

    return compact('admin', 'faculty', 'program', 'academicYear', 'period', 'rule');
}

function createAdmissionApp(array $setup, array $overrides = []): AdmissionApplication
{
    return AdmissionApplication::create(array_merge([
        'admission_period_id' => $setup['period']->id,
        'application_number' => 'APP-2026-' . fake()->unique()->numerify('####'),
        'access_token' => Str::random(32),
        'full_name' => fake()->name(),
        'email' => fake()->unique()->safeEmail(),
        'phone' => '081234567890',
        'birth_date' => '2005-05-15',
        'gender' => 'male',
        'address' => 'Jl. Merdeka No. 10 Jakarta',
        'emergency_contact_name' => 'Bapak Santoso',
        'emergency_contact_phone' => '081122334455',
        'high_school_name' => 'SMAN 1 Jakarta',
        'high_school_major' => 'IPA',
        'high_school_graduation_year' => 2024,
        'faculty_id' => $setup['faculty']->id,
        'study_program_id' => $setup['program']->id,
        'class_type' => 'regular',
        'status' => 'accepted',
        'submitted_at' => now(),
        'accepted_at' => now(),
    ], $overrides));
}

it('generates nim using active rule and sequence scope', function () {
    $setup = admissionTestSetup();
    $application = createAdmissionApp($setup, [
        'application_number' => 'APP-2026-0001',
        'full_name' => 'Budi Santoso',
    ]);

    $nimService = app(NimGenerationService::class);
    $preview = $nimService->preview($application, $setup['rule']);
    expect($preview)->toBe('2026SI0001');

    $generated = $nimService->generate($application, $setup['rule']);
    expect($generated)->toBe('2026SI0001');

    // Generating for second applicant should increment sequence
    $application2 = createAdmissionApp($setup, [
        'application_number' => 'APP-2026-0002',
        'full_name' => 'Siti Aminah',
    ]);

    $generated2 = $nimService->generate($application2, $setup['rule']);
    expect($generated2)->toBe('2026SI0002');
});

it('converts accepted admission application into active user and student profile', function () {
    $setup = admissionTestSetup();
    $application = createAdmissionApp($setup, [
        'application_number' => 'APP-2026-0003',
        'full_name' => 'Andi Wijaya',
        'email' => 'andi.wijaya.test@example.com',
    ]);

    $conversionService = app(AdmissionConversionService::class);
    $studentProfile = $conversionService->convert($application, $setup['admin']->id);

    expect($studentProfile)->toBeInstanceOf(StudentProfile::class)
        ->and($studentProfile->nim)->toBe('2026SI0001')
        ->and($studentProfile->user->email)->toBe('andi.wijaya.test@example.com')
        ->and($studentProfile->user->hasRole('student'))->toBeTrue()
        ->and($application->fresh()->user_id)->toBe($studentProfile->user_id)
        ->and($application->fresh()->converted_at)->not->toBeNull();
});

it('prevents double conversion of the same admission application', function () {
    $setup = admissionTestSetup();
    $application = createAdmissionApp($setup, [
        'application_number' => 'APP-2026-0004',
        'full_name' => 'Rina Kurnia',
    ]);

    $conversionService = app(AdmissionConversionService::class);
    $conversionService->convert($application, $setup['admin']->id);

    expect(fn () => $conversionService->convert($application->fresh(), $setup['admin']->id))
        ->toThrow(\RuntimeException::class, 'Applicant ini sudah pernah dikonversi.');
});

it('throws runtime exception if converting non-accepted application', function () {
    $setup = admissionTestSetup();
    $application = createAdmissionApp($setup, [
        'application_number' => 'APP-2026-0005',
        'full_name' => 'Dewi Lestari',
        'status' => 'submitted',
    ]);

    $conversionService = app(AdmissionConversionService::class);

    expect(fn () => $conversionService->convert($application, $setup['admin']->id))
        ->toThrow(\RuntimeException::class, 'Hanya applicant dengan status accepted yang bisa dikonversi.');
});
