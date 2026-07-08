<?php

use App\Models\Academic\AcademicYear;
use App\Models\Academic\Faculty;
use App\Models\Academic\StudentProfile;
use App\Models\Academic\StudyProgram;
use App\Models\Settings\Campus;
use App\Models\Settings\System;
use App\Models\User;
use App\Support\Student\DigitalStudentIdService;
use Illuminate\Support\Facades\DB;

function createDigitalIdStudent(): StudentProfile
{
    ensureDigitalIdAppInstalled();

    $faculty = Faculty::create(['name' => 'Fakultas Teknik', 'code' => 'FT', 'is_active' => true]);
    $program = StudyProgram::create(['faculty_id' => $faculty->id, 'name' => 'Sistem Informasi', 'code' => 'SI', 'degree' => 'S1', 'is_active' => true]);
    $year = AcademicYear::create(['name' => '2026/2027', 'code' => '2026', 'semester' => 'Ganjil', 'start_date' => '2026-08-01', 'end_date' => '2027-01-31', 'is_active' => true]);
    $user = User::factory()->create([
        'first_name' => 'Mahasiswa',
        'last_name' => 'Digital',
    ]);

    return StudentProfile::create([
        'user_id' => $user->id,
        'study_program_id' => $program->id,
        'entry_academic_year_id' => $year->id,
        'nim' => 'DIG-2026-001',
        'entry_year' => 2026,
        'academic_status' => 'Aktif',
        'current_semester' => 3,
        'class_type' => 'Reguler',
        'is_active' => true,
    ]);
}

function ensureDigitalIdAppInstalled(): void
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

    Campus::query()->updateOrCreate(['id' => 1], [
        'name' => 'NexaCampus',
        'phone' => '021',
        'whatsapp' => '021',
        'email_info' => 'info@example.test',
        'email_humas' => 'humas@example.test',
        'domain' => 'example.test',
        'address' => 'Jl. Kampus',
    ]);
}

it('verifies a digital student id token', function () {
    $student = createDigitalIdStudent();
    $service = app(DigitalStudentIdService::class);

    $this->get($service->verificationUrl($student))
        ->assertOk()
        ->assertSee('Kartu Mahasiswa Valid')
        ->assertSee('DIG-2026-001');
});

it('rejects an invalid digital student id token', function () {
    $student = createDigitalIdStudent();

    $this->get(route('student.digital-id.verify', [
        'studentProfile' => $student->id,
        'token' => 'invalid-token',
    ]))->assertNotFound();
});
