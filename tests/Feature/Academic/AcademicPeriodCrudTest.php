<?php

use App\Models\Academic\AcademicPeriod;
use App\Models\Academic\AcademicYear;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission as SpatiePermission;
use Spatie\Permission\Models\Role as SpatieRole;

uses(RefreshDatabase::class);

function academicPeriodCrudSetup(): array
{
    SpatieRole::firstOrCreate(['name' => 'operator', 'guard_name' => 'web']);

    App\Models\Settings\System::create([
        'app_name' => 'NexaCampus Test',
        'app_version' => '1.0.0',
        'app_description' => 'Test',
        'app_url' => 'http://localhost',
        'app_email' => 'test@localhost',
    ])->forceFill(['is_installed' => true])->save();

    $operator = App\Models\User::factory()->create();
    $operator->assignRole('operator');

    $year = AcademicYear::create([
        'name' => '2026/2027 Ganjil', 'code' => 'Y26G', 'semester' => 'Ganjil',
        'start_date' => '2026-08-01', 'end_date' => '2027-01-31', 'is_active' => true,
    ]);

    return compact('operator', 'year');
}

function giveAcademicPeriodPermissionsToOperator(array $names): void
{
    $role = SpatieRole::where('name', 'operator')->firstOrFail();

    foreach ($names as $name) {
        $role->givePermissionTo(SpatiePermission::firstOrCreate([
            'name' => $name,
            'guard_name' => 'web',
        ]));
    }
}

function actingAcademicPeriodOperator($operator)
{
    return test()->actingAs($operator)->withSession(['active_role' => 'operator']);
}

it('menampilkan daftar periode dengan nama tahun', function () {
    $setup = academicPeriodCrudSetup();
    giveAcademicPeriodPermissionsToOperator(['academic-period.viewAny']);

    AcademicPeriod::create([
        'academic_year_id' => $setup['year']->id, 'name' => 'KRS Ganjil',
        'code' => 'KRS26G', 'type' => 'Study Plan',
        'start_at' => '2026-08-01 00:00:00', 'end_at' => '2026-08-31 23:59:59',
        'is_active' => true,
    ]);

    actingAcademicPeriodOperator($setup['operator'])
        ->get('/admin/academic/academic-periods')
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('Admin/Academic/AcademicPeriod/Index')
            ->where('stats.total', 1)
            ->has('data.rows', 1)
            ->where('data.rows.0.year', '2026/2027 Ganjil')
            ->where('data.rows.0.type', 'Study Plan'));
});

it('memvalidasi urutan tanggal dan kode unik', function () {
    $setup = academicPeriodCrudSetup();
    giveAcademicPeriodPermissionsToOperator(['academic-period.create']);

    actingAcademicPeriodOperator($setup['operator'])
        ->post('/admin/academic/academic-periods', [
            'academic_year_id' => $setup['year']->id, 'name' => 'Rusak',
            'type' => 'Study Plan', 'start_at' => '2026-09-01T00:00', 'end_at' => '2026-08-01T00:00',
        ])
        ->assertSessionHasErrors('end_at');

    actingAcademicPeriodOperator($setup['operator'])
        ->post('/admin/academic/academic-periods', [
            'academic_year_id' => $setup['year']->id, 'name' => 'Pertama', 'code' => 'DUP',
            'type' => 'Study Plan', 'start_at' => '2026-08-01T00:00', 'end_at' => '2026-08-31T00:00',
        ])
        ->assertSessionHasNoErrors();

    actingAcademicPeriodOperator($setup['operator'])
        ->post('/admin/academic/academic-periods', [
            'academic_year_id' => $setup['year']->id, 'name' => 'Kedua', 'code' => 'DUP',
            'type' => 'Study Plan', 'start_at' => '2026-08-01T00:00', 'end_at' => '2026-08-31T00:00',
        ])
        ->assertSessionHasErrors('code');
});

it('mengelola sampah periode dan mengekspornya', function () {
    $setup = academicPeriodCrudSetup();
    giveAcademicPeriodPermissionsToOperator(['academic-period.viewAny', 'academic-period.delete']);

    $period = AcademicPeriod::create([
        'academic_year_id' => $setup['year']->id, 'name' => 'Sementara',
        'type' => 'Custom', 'start_at' => '2026-08-01 00:00:00',
        'end_at' => '2026-08-31 23:59:59', 'is_active' => true,
    ]);

    actingAcademicPeriodOperator($setup['operator'])
        ->delete("/admin/academic/academic-periods/{$period->id}")
        ->assertRedirect('/admin/academic/academic-periods');

    actingAcademicPeriodOperator($setup['operator'])
        ->get('/admin/academic/academic-periods?mode=trash')
        ->assertOk()
        ->assertInertia(fn ($page) => $page->has('data.rows', 1));

    actingAcademicPeriodOperator($setup['operator'])
        ->post("/admin/academic/academic-periods/{$period->id}/restore")
        ->assertSessionHas('success');

    expect(AcademicPeriod::find($period->id))->not->toBeNull();

    $response = actingAcademicPeriodOperator($setup['operator'])
        ->get('/admin/academic/academic-periods/export?format=csv')
        ->assertOk();

    expect($response->headers->get('Content-Type'))->toContain('text/csv');
});
