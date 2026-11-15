<?php

use App\Models\Academic\AcademicYear;
use App\Models\Academic\Faculty;
use App\Models\Academic\StudyProgram;
use App\Models\Admission\AdmissionApplication;
use App\Models\Admission\AdmissionDocumentRequirement;
use App\Models\Admission\AdmissionPeriod;
use App\Models\Admission\AdmissionQuota;
use App\Models\Admission\NimGenerationRule;
use App\Models\Settings\System;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission as SpatiePermission;
use Spatie\Permission\Models\Role as SpatieRole;

uses(RefreshDatabase::class);

function admissionBatchOneSetup(): array
{
    SpatieRole::firstOrCreate(['name' => 'operator', 'guard_name' => 'web']);

    System::create([
        'app_name' => 'NexaCampus Test',
        'app_version' => '1.0.0',
        'app_description' => 'Test',
        'app_url' => 'http://localhost',
        'app_email' => 'test@localhost',
    ])->forceFill(['is_installed' => true])->save();

    $operator = User::factory()->create();
    $operator->assignRole('operator');

    $year = AcademicYear::create([
        'name' => '2026/2027 Ganjil', 'code' => 'Y26G', 'semester' => 'Ganjil',
        'start_date' => '2026-08-01', 'end_date' => '2027-01-31', 'is_active' => true,
    ]);

    $faculty = Faculty::create([
        'name' => 'Fakultas Teknik', 'code' => 'FT', 'is_active' => true,
    ]);

    $program = StudyProgram::create([
        'faculty_id' => $faculty->id, 'name' => 'Informatika', 'code' => 'IF',
        'degree' => 'S1', 'is_active' => true,
    ]);

    return compact('operator', 'year', 'faculty', 'program');
}

function giveAdmissionBatchOnePermissions(array $names): void
{
    $role = SpatieRole::where('name', 'operator')->firstOrFail();

    foreach ($names as $name) {
        $role->givePermissionTo(SpatiePermission::firstOrCreate([
            'name' => $name,
            'guard_name' => 'web',
        ]));
    }
}

function actingAdmissionBatchOneOperator($operator)
{
    return test()->actingAs($operator)->withSession(['active_role' => 'operator']);
}

function admissionBatchOnePeriodPayload(AcademicYear $year, array $overrides = []): array
{
    return array_merge([
        'name' => 'PMB 2026 Gelombang 1',
        'code' => 'ADM2026W1',
        'academic_year_id' => $year->id,
        'wave' => 1,
        'opens_at' => '2026-01-01',
        'closes_at' => '2026-03-01',
        'is_active' => true,
        'is_published' => false,
        'description' => 'Gelombang awal.',
        'document_requirements' => [
            [
                'document_type' => 'id_card',
                'label' => 'Kartu Identitas',
                'is_required' => true,
                'allowed_extensions' => 'PDF, JPG, exe',
                'max_size_kb' => 2048,
            ],
        ],
    ], $overrides);
}

function admissionBatchOneApplication(AdmissionPeriod $period, string $number = 'APP-1'): AdmissionApplication
{
    return AdmissionApplication::create([
        'admission_period_id' => $period->id,
        'application_number' => $number,
        'access_token' => 'token-'.$number,
        'full_name' => 'Calon Mahasiswa',
        'email' => strtolower($number).'@example.test',
        'phone' => '08123456789',
        'birth_date' => '2008-05-01',
        'gender' => 'male',
        'address' => 'Jl. Contoh No. 1',
        'emergency_contact_name' => 'Orang Tua',
        'emergency_contact_phone' => '08129876543',
        'high_school_name' => 'SMA Negeri 1',
        'high_school_major' => 'IPA',
        'high_school_graduation_year' => 2026,
        'status' => 'submitted',
    ]);
}

it('menampilkan daftar periode PMB dan menyimpan periode beserta syarat dokumen', function () {
    $setup = admissionBatchOneSetup();
    giveAdmissionBatchOnePermissions(['admission-period.viewAny', 'admission-period.create']);

    actingAdmissionBatchOneOperator($setup['operator'])
        ->get('/admin/admission/admission-periods')
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('Admin/Admission/Period/Index')
            ->where('stats.total', 0)
            ->has('data.rows', 0));

    actingAdmissionBatchOneOperator($setup['operator'])
        ->post('/admin/admission/admission-periods', admissionBatchOnePeriodPayload($setup['year']))
        ->assertRedirect('/admin/admission/admission-periods')
        ->assertSessionHas('success');

    $period = AdmissionPeriod::where('code', 'ADM2026W1')->firstOrFail();

    expect($period->academic_year)->toBe(2026)
        ->and($period->academicYear->name)->toBe('2026/2027 Ganjil')
        ->and($period->documentRequirements)->toHaveCount(1)
        ->and($period->documentRequirements->first()->allowed_extensions)->toBe('pdf,jpg');
});

it('menolak kode periode duplikat dan rentang tanggal terbalik', function () {
    $setup = admissionBatchOneSetup();
    giveAdmissionBatchOnePermissions(['admission-period.create']);

    AdmissionPeriod::create([
        'name' => 'Periode Lama', 'code' => 'ADM2026W1', 'academic_year_id' => $setup['year']->id,
        'academic_year' => 2026, 'wave' => 1, 'opens_at' => '2026-01-01', 'closes_at' => '2026-02-01',
    ]);

    actingAdmissionBatchOneOperator($setup['operator'])
        ->post('/admin/admission/admission-periods', admissionBatchOnePeriodPayload($setup['year']))
        ->assertSessionHasErrors('code');

    actingAdmissionBatchOneOperator($setup['operator'])
        ->post('/admin/admission/admission-periods', admissionBatchOnePeriodPayload($setup['year'], [
            'code' => 'ADM2026W2',
            'opens_at' => '2026-05-01',
            'closes_at' => '2026-04-01',
        ]))
        ->assertSessionHasErrors('closes_at');
});

it('memperbarui periode dan menyinkronkan syarat dokumen', function () {
    $setup = admissionBatchOneSetup();
    giveAdmissionBatchOnePermissions(['admission-period.update']);

    $period = AdmissionPeriod::create([
        'name' => 'PMB Awal', 'code' => 'ADM2026W1', 'academic_year_id' => $setup['year']->id,
        'academic_year' => 2026, 'wave' => 1, 'opens_at' => '2026-01-01', 'closes_at' => '2026-02-01',
        'is_active' => true,
    ]);

    $keep = AdmissionDocumentRequirement::create([
        'admission_period_id' => $period->id, 'document_type' => 'id_card',
        'label' => 'KTP Lama', 'is_required' => true, 'allowed_extensions' => 'pdf', 'sort_order' => 1,
    ]);
    $remove = AdmissionDocumentRequirement::create([
        'admission_period_id' => $period->id, 'document_type' => 'photo',
        'label' => 'Foto Lama', 'is_required' => false, 'allowed_extensions' => 'jpg', 'sort_order' => 2,
    ]);

    actingAdmissionBatchOneOperator($setup['operator'])
        ->put("/admin/admission/admission-periods/{$period->id}", admissionBatchOnePeriodPayload($setup['year'], [
            'name' => 'PMB Diperbarui',
            'is_published' => true,
            'document_requirements' => [
                [
                    'id' => $keep->id,
                    'document_type' => 'id_card',
                    'label' => 'KTP Baru',
                    'is_required' => true,
                    'allowed_extensions' => 'pdf,png',
                    'max_size_kb' => 1024,
                ],
            ],
        ]))
        ->assertRedirect('/admin/admission/admission-periods')
        ->assertSessionHas('success');

    $period->refresh();

    expect($period->name)->toBe('PMB Diperbarui')
        ->and($period->is_published)->toBeTrue()
        ->and($period->documentRequirements()->count())->toBe(1)
        ->and($period->documentRequirements()->first()->label)->toBe('KTP Baru')
        ->and(AdmissionDocumentRequirement::find($remove->id))->toBeNull();
});

it('mengubah status aktif dan publikasi periode', function () {
    $setup = admissionBatchOneSetup();
    giveAdmissionBatchOnePermissions(['admission-period.update']);

    $period = AdmissionPeriod::create([
        'name' => 'PMB', 'code' => 'ADM2026W1', 'academic_year_id' => $setup['year']->id,
        'academic_year' => 2026, 'wave' => 1, 'opens_at' => '2026-01-01', 'closes_at' => '2026-02-01',
        'is_active' => true, 'is_published' => false,
    ]);

    actingAdmissionBatchOneOperator($setup['operator'])
        ->post("/admin/admission/admission-periods/{$period->id}/toggle", ['field' => 'is_active', 'value' => false])
        ->assertSessionHas('success');

    actingAdmissionBatchOneOperator($setup['operator'])
        ->post("/admin/admission/admission-periods/{$period->id}/toggle", ['field' => 'is_published', 'value' => true])
        ->assertSessionHas('success');

    $period->refresh();

    expect($period->is_active)->toBeFalse()
        ->and($period->is_published)->toBeTrue();
});

it('menolak hapus periode yang sudah memiliki pendaftar', function () {
    $setup = admissionBatchOneSetup();
    giveAdmissionBatchOnePermissions(['admission-period.delete']);

    $period = AdmissionPeriod::create([
        'name' => 'PMB', 'code' => 'ADM2026W1', 'academic_year_id' => $setup['year']->id,
        'academic_year' => 2026, 'wave' => 1, 'opens_at' => '2026-01-01', 'closes_at' => '2026-02-01',
    ]);

    admissionBatchOneApplication($period);

    actingAdmissionBatchOneOperator($setup['operator'])
        ->delete("/admin/admission/admission-periods/{$period->id}")
        ->assertSessionHas('error');

    expect(AdmissionPeriod::find($period->id))->not->toBeNull();
});

it('mengelola sampah periode dan mengekspornya', function () {
    $setup = admissionBatchOneSetup();
    giveAdmissionBatchOnePermissions([
        'admission-period.viewAny', 'admission-period.delete',
        'admission-period.update', 'admission-period.create',
    ]);

    actingAdmissionBatchOneOperator($setup['operator'])
        ->post('/admin/admission/admission-periods', admissionBatchOnePeriodPayload($setup['year']))
        ->assertSessionHas('success');

    $period = AdmissionPeriod::where('code', 'ADM2026W1')->firstOrFail();

    actingAdmissionBatchOneOperator($setup['operator'])
        ->delete("/admin/admission/admission-periods/{$period->id}")
        ->assertRedirect('/admin/admission/admission-periods');

    actingAdmissionBatchOneOperator($setup['operator'])
        ->get('/admin/admission/admission-periods?mode=trash')
        ->assertOk()
        ->assertInertia(fn ($page) => $page->has('data.rows', 1));

    actingAdmissionBatchOneOperator($setup['operator'])
        ->post("/admin/admission/admission-periods/{$period->id}/restore")
        ->assertSessionHas('success');

    expect(AdmissionPeriod::find($period->id))->not->toBeNull();

    $response = actingAdmissionBatchOneOperator($setup['operator'])
        ->get('/admin/admission/admission-periods/export?format=csv')
        ->assertOk();

    expect($response->headers->get('Content-Type'))->toContain('text/csv');

    actingAdmissionBatchOneOperator($setup['operator'])
        ->delete("/admin/admission/admission-periods/{$period->id}")
        ->assertRedirect('/admin/admission/admission-periods');

    actingAdmissionBatchOneOperator($setup['operator'])
        ->delete("/admin/admission/admission-periods/{$period->id}/force")
        ->assertSessionHas('success');

    expect(AdmissionPeriod::withTrashed()->find($period->id))->toBeNull();
});

it('menghapus massal periode dan melewati yang memiliki pendaftar', function () {
    $setup = admissionBatchOneSetup();
    giveAdmissionBatchOnePermissions(['admission-period.delete']);

    $used = AdmissionPeriod::create([
        'name' => 'Dipakai', 'code' => 'ADM2026W1', 'academic_year_id' => $setup['year']->id,
        'academic_year' => 2026, 'wave' => 1, 'opens_at' => '2026-01-01', 'closes_at' => '2026-02-01',
    ]);
    $free = AdmissionPeriod::create([
        'name' => 'Kosong', 'code' => 'ADM2026W2', 'academic_year_id' => $setup['year']->id,
        'academic_year' => 2026, 'wave' => 2, 'opens_at' => '2026-03-01', 'closes_at' => '2026-04-01',
    ]);

    admissionBatchOneApplication($used);

    actingAdmissionBatchOneOperator($setup['operator'])
        ->post('/admin/admission/admission-periods/bulk-destroy', ['ids' => [$used->id, $free->id]])
        ->assertSessionHas('success')
        ->assertSessionHas('warning');

    expect(AdmissionPeriod::find($used->id))->not->toBeNull()
        ->and(AdmissionPeriod::find($free->id))->toBeNull();
});

it('menampilkan, menyimpan, dan mengekspor kuota PMB', function () {
    $setup = admissionBatchOneSetup();
    giveAdmissionBatchOnePermissions([
        'admission-quota.viewAny', 'admission-quota.create', 'admission-quota.update',
    ]);

    $period = AdmissionPeriod::create([
        'name' => 'PMB', 'code' => 'ADM2026W1', 'academic_year_id' => $setup['year']->id,
        'academic_year' => 2026, 'wave' => 1, 'opens_at' => '2026-01-01', 'closes_at' => '2026-02-01',
    ]);

    actingAdmissionBatchOneOperator($setup['operator'])
        ->get('/admin/admission/admission-quotas')
        ->assertOk()
        ->assertInertia(fn ($page) => $page->component('Admin/Admission/Quota/Index'));

    actingAdmissionBatchOneOperator($setup['operator'])
        ->post('/admin/admission/admission-quotas', [
            'admission_period_id' => $period->id,
            'faculty_id' => $setup['faculty']->id,
            'study_program_id' => $setup['program']->id,
            'class_type' => 'regular',
            'quota' => 40,
        ])
        ->assertRedirect('/admin/admission/admission-quotas')
        ->assertSessionHas('success');

    $quota = AdmissionQuota::where('quota', 40)->firstOrFail();

    actingAdmissionBatchOneOperator($setup['operator'])
        ->put("/admin/admission/admission-quotas/{$quota->id}", [
            'admission_period_id' => $period->id,
            'faculty_id' => $setup['faculty']->id,
            'study_program_id' => $setup['program']->id,
            'class_type' => 'evening',
            'quota' => 55,
        ])
        ->assertSessionHas('success');

    expect($quota->refresh()->quota)->toBe(55)
        ->and($quota->class_type)->toBe('evening');

    actingAdmissionBatchOneOperator($setup['operator'])
        ->post('/admin/admission/admission-quotas', [
            'admission_period_id' => $period->id,
            'quota' => 0,
        ])
        ->assertSessionHasErrors('quota');

    $response = actingAdmissionBatchOneOperator($setup['operator'])
        ->get('/admin/admission/admission-quotas/export?format=csv')
        ->assertOk();

    expect($response->headers->get('Content-Type'))->toContain('text/csv');
});

it('menghapus kuota satuan dan massal', function () {
    $setup = admissionBatchOneSetup();
    giveAdmissionBatchOnePermissions(['admission-quota.delete']);

    $period = AdmissionPeriod::create([
        'name' => 'PMB', 'code' => 'ADM2026W1', 'academic_year_id' => $setup['year']->id,
        'academic_year' => 2026, 'wave' => 1, 'opens_at' => '2026-01-01', 'closes_at' => '2026-02-01',
    ]);

    $first = AdmissionQuota::create(['admission_period_id' => $period->id, 'quota' => 10]);
    $second = AdmissionQuota::create(['admission_period_id' => $period->id, 'quota' => 20]);

    actingAdmissionBatchOneOperator($setup['operator'])
        ->delete("/admin/admission/admission-quotas/{$first->id}")
        ->assertRedirect('/admin/admission/admission-quotas');

    actingAdmissionBatchOneOperator($setup['operator'])
        ->post('/admin/admission/admission-quotas/bulk-destroy', ['ids' => [$second->id]])
        ->assertSessionHas('success');

    expect(AdmissionQuota::count())->toBe(0);
});

it('menyimpan aturan NIM aktif dan menonaktifkan aturan lain', function () {
    $setup = admissionBatchOneSetup();
    giveAdmissionBatchOnePermissions([
        'nim-generation-rule.viewAny', 'nim-generation-rule.create',
        'nim-generation-rule.update', 'nim-generation-rule.delete',
    ]);

    $old = NimGenerationRule::create([
        'name' => 'Lama', 'pattern' => '{year}{sequence}', 'sequence_scope' => 'year',
        'sequence_padding' => 4, 'sequence_start' => 1, 'is_active' => true,
    ]);

    actingAdmissionBatchOneOperator($setup['operator'])
        ->get('/admin/admission/nim-generation-rules')
        ->assertOk()
        ->assertInertia(fn ($page) => $page->component('Admin/Admission/NimRule/Index'));

    actingAdmissionBatchOneOperator($setup['operator'])
        ->post('/admin/admission/nim-generation-rules', [
            'name' => 'Baru', 'pattern' => '{yy}{program_code}{sequence}',
            'sequence_scope' => 'study_program_year', 'sequence_padding' => 4,
            'sequence_start' => 1, 'is_active' => true, 'description' => 'Aturan baru.',
        ])
        ->assertRedirect('/admin/admission/nim-generation-rules')
        ->assertSessionHas('success');

    expect($old->refresh()->is_active)->toBeFalse()
        ->and(NimGenerationRule::where('name', 'Baru')->firstOrFail()->is_active)->toBeTrue();
});

it('mengubah status aturan NIM dan menghapus massal', function () {
    $setup = admissionBatchOneSetup();
    giveAdmissionBatchOnePermissions(['nim-generation-rule.update', 'nim-generation-rule.delete']);

    $first = NimGenerationRule::create([
        'name' => 'Satu', 'pattern' => '{year}{sequence}', 'sequence_scope' => 'year',
        'sequence_padding' => 4, 'sequence_start' => 1, 'is_active' => false,
    ]);
    $second = NimGenerationRule::create([
        'name' => 'Dua', 'pattern' => '{yy}{sequence}', 'sequence_scope' => 'global',
        'sequence_padding' => 3, 'sequence_start' => 1, 'is_active' => true,
    ]);

    actingAdmissionBatchOneOperator($setup['operator'])
        ->post("/admin/admission/nim-generation-rules/{$first->id}/toggle", ['is_active' => true])
        ->assertSessionHas('success');

    expect($first->refresh()->is_active)->toBeTrue()
        ->and($second->refresh()->is_active)->toBeFalse();

    actingAdmissionBatchOneOperator($setup['operator'])
        ->post('/admin/admission/nim-generation-rules/bulk-destroy', ['ids' => [$first->id, $second->id]])
        ->assertSessionHas('success');

    expect(NimGenerationRule::count())->toBe(0);
});
