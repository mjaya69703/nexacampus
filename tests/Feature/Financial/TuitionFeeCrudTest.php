<?php

use App\Models\Academic\AcademicYear;
use App\Models\Academic\Faculty;
use App\Models\Academic\StudyProgram;
use App\Models\Financial\TuitionFee;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

uses(RefreshDatabase::class);

function tuitionFeeSetup(): array
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

    $role = Role::findOrCreate('admin', 'web');
    foreach (['tuition-fee.viewAny', 'tuition-fee.create', 'tuition-fee.update', 'tuition-fee.delete'] as $permission) {
        $role->givePermissionTo(Permission::findOrCreate($permission, 'web'));
    }

    $admin = App\Models\User::factory()->create();
    $admin->assignRole($role);

    $suffix = (string) random_int(10000, 99999);
    $faculty = Faculty::create(['name' => 'FF'.$suffix, 'code' => 'FF'.$suffix, 'is_active' => true]);
    $program = StudyProgram::create(['faculty_id' => $faculty->id, 'name' => 'PF'.$suffix, 'code' => 'PF'.$suffix, 'degree' => 'S1', 'is_active' => true]);
    $year = AcademicYear::create(['name' => 'YF'.$suffix, 'code' => 'YF'.$suffix, 'semester' => 'Ganjil', 'start_date' => '2026-08-01', 'end_date' => '2027-01-31', 'is_active' => true]);

    return compact('admin', 'program', 'year');
}

function tuitionFeeActingAs($admin)
{
    return test()->actingAs($admin)->withSession(['active_role' => 'admin']);
}

function tuitionFeePayload(array $setup, array $overrides = []): array
{
    return array_merge([
        'academic_year_id' => $setup['year']->id,
        'study_program_id' => $setup['program']->id,
        'semester' => 1,
        'base_fee' => 5000000,
        'lab_fee' => 500000,
        'library_fee' => 200000,
        'activity_fee' => 300000,
        'late_penalty_per_day' => 50000,
        'payment_deadline' => '2026-09-30',
        'is_active' => true,
        'notes' => 'SK Rektor',
    ], $overrides);
}

it('menampilkan daftar tarif', function () {
    $setup = tuitionFeeSetup();
    TuitionFee::create(array_merge(tuitionFeePayload($setup), ['created_by' => $setup['admin']->id]));

    tuitionFeeActingAs($setup['admin'])
        ->get(route('admin.financial.tuition-fees.index'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('Admin/Financial/TuitionFee/Index')
            ->where('stats.total', 1)
            ->has('data.rows', 1));
});

it('membuat tarif baru', function () {
    $setup = tuitionFeeSetup();

    tuitionFeeActingAs($setup['admin'])
        ->post(route('admin.financial.tuition-fees.store'), tuitionFeePayload($setup))
        ->assertRedirect(route('admin.financial.tuition-fees.index'));

    expect(TuitionFee::count())->toBe(1);
});

it('menolak duplikat kombinasi tahun prodi semester', function () {
    $setup = tuitionFeeSetup();
    TuitionFee::create(array_merge(tuitionFeePayload($setup), ['created_by' => $setup['admin']->id]));

    tuitionFeeActingAs($setup['admin'])
        ->post(route('admin.financial.tuition-fees.store'), tuitionFeePayload($setup))
        ->assertSessionHasErrors('academic_year_id');
});

it('mengubah dan toggle tarif', function () {
    $setup = tuitionFeeSetup();
    $fee = TuitionFee::create(array_merge(tuitionFeePayload($setup), ['created_by' => $setup['admin']->id]));

    tuitionFeeActingAs($setup['admin'])
        ->put(route('admin.financial.tuition-fees.update', $fee), tuitionFeePayload($setup, ['base_fee' => 6000000]))
        ->assertRedirect();

    expect($fee->fresh()->base_fee)->toBe('6000000.00');

    tuitionFeeActingAs($setup['admin'])
        ->post(route('admin.financial.tuition-fees.toggle', $fee))
        ->assertRedirect();

    expect($fee->fresh()->is_active)->toBeFalse();
});

it('hapus lunak restore dan hapus permanen', function () {
    $setup = tuitionFeeSetup();
    $fee = TuitionFee::create(array_merge(tuitionFeePayload($setup), ['created_by' => $setup['admin']->id]));

    tuitionFeeActingAs($setup['admin'])
        ->delete(route('admin.financial.tuition-fees.destroy', $fee))
        ->assertRedirect();

    expect(TuitionFee::count())->toBe(0)
        ->and(TuitionFee::onlyTrashed()->count())->toBe(1);

    tuitionFeeActingAs($setup['admin'])
        ->post(route('admin.financial.tuition-fees.restore', $fee->id))
        ->assertRedirect();

    expect(TuitionFee::count())->toBe(1);

    tuitionFeeActingAs($setup['admin'])
        ->delete(route('admin.financial.tuition-fees.destroy', $fee))
        ->assertRedirect();

    tuitionFeeActingAs($setup['admin'])
        ->delete(route('admin.financial.tuition-fees.force-destroy', $fee->id))
        ->assertRedirect();

    expect(TuitionFee::onlyTrashed()->count())->toBe(0);
});

it('bulk hapus dan restore', function () {
    $setup = tuitionFeeSetup();
    $one = TuitionFee::create(array_merge(tuitionFeePayload($setup), ['semester' => 1]));
    $two = TuitionFee::create(array_merge(tuitionFeePayload($setup), ['semester' => 2]));

    tuitionFeeActingAs($setup['admin'])
        ->post(route('admin.financial.tuition-fees.bulk-destroy'), ['ids' => [$one->id, $two->id]])
        ->assertRedirect();

    expect(TuitionFee::count())->toBe(0);

    tuitionFeeActingAs($setup['admin'])
        ->post(route('admin.financial.tuition-fees.bulk-restore'), ['ids' => [$one->id, $two->id]])
        ->assertRedirect();

    expect(TuitionFee::count())->toBe(2);
});

it('mengekspor xlsx tarif', function () {
    $setup = tuitionFeeSetup();
    TuitionFee::create(tuitionFeePayload($setup));

    tuitionFeeActingAs($setup['admin'])
        ->get(route('admin.financial.tuition-fees.export', ['format' => 'xlsx']))
        ->assertOk();
});
