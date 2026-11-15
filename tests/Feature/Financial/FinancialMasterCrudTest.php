<?php

use App\Models\Financial\FinancialClearancePolicy;
use App\Models\Financial\Scholarship;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

uses(RefreshDatabase::class);

function financialMasterSetup(): array
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
    foreach ([
        'clearance-policy.viewAny', 'clearance-policy.create', 'clearance-policy.update', 'clearance-policy.delete',
        'scholarship.viewAny', 'scholarship.create', 'scholarship.update', 'scholarship.delete',
    ] as $permission) {
        $role->givePermissionTo(Permission::findOrCreate($permission, 'web'));
    }

    $admin = App\Models\User::factory()->create();
    $admin->assignRole($role);

    return compact('admin');
}

function financialMasterActingAs($admin)
{
    return test()->actingAs($admin)->withSession(['active_role' => 'admin']);
}

it('CRUD kebijakan bebas tanggungan', function () {
    $setup = financialMasterSetup();

    financialMasterActingAs($setup['admin'])
        ->post(route('admin.financial.clearance-policies.store'), [
            'invoice_type' => 'exam',
            'hold_type' => 'transcript',
            'mode' => 'blocking',
            'grace_days' => 7,
            'is_active' => true,
            'description' => 'Blokir transkrip bila ada tunggakan ujian',
        ])
        ->assertRedirect(route('admin.financial.clearance-policies.index'));

    $policy = FinancialClearancePolicy::where('invoice_type', 'exam')->where('hold_type', 'transcript')->first();
    expect($policy)->not->toBeNull();

    financialMasterActingAs($setup['admin'])
        ->get(route('admin.financial.clearance-policies.index'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('Admin/Financial/ClearancePolicy/Index'));

    financialMasterActingAs($setup['admin'])
        ->put(route('admin.financial.clearance-policies.update', $policy), [
            'invoice_type' => 'exam',
            'hold_type' => 'transcript',
            'mode' => 'warning',
            'grace_days' => 14,
            'is_active' => true,
        ])
        ->assertSessionHasNoErrors()
        ->assertRedirect();

    expect($policy->fresh()->mode)->toBe('warning');

    financialMasterActingAs($setup['admin'])
        ->post(route('admin.financial.clearance-policies.toggle', $policy))
        ->assertRedirect();

    expect($policy->fresh()->is_active)->toBeFalse();

    financialMasterActingAs($setup['admin'])
        ->delete(route('admin.financial.clearance-policies.destroy', $policy))
        ->assertRedirect();

    expect(FinancialClearancePolicy::where('invoice_type', 'exam')->where('hold_type', 'transcript')->count())->toBe(0);
});

it('menolak duplikat kombinasi tagihan dan target (termasuk bawaan migrasi)', function () {
    $setup = financialMasterSetup();

    // Kombinasi tuition/study_plan sudah di-seed oleh migrasi.
    financialMasterActingAs($setup['admin'])
        ->post(route('admin.financial.clearance-policies.store'), [
            'invoice_type' => 'tuition',
            'hold_type' => 'study_plan',
            'mode' => 'blocking',
            'grace_days' => 7,
            'is_active' => true,
        ])
        ->assertSessionHasErrors('invoice_type');
});

it('menolak tipe tagihan dan mode yang tidak dikenal', function () {
    $setup = financialMasterSetup();

    financialMasterActingAs($setup['admin'])
        ->post(route('admin.financial.clearance-policies.store'), [
            'invoice_type' => 'ngasal',
            'hold_type' => 'study_plan',
            'mode' => 'destroy',
            'grace_days' => 7,
            'is_active' => true,
        ])
        ->assertSessionHasErrors(['invoice_type', 'mode']);
});

it('CRUD beasiswa dengan sampah dan toggle', function () {
    $setup = financialMasterSetup();

    financialMasterActingAs($setup['admin'])
        ->post(route('admin.financial.scholarships.store'), [
            'name' => 'Beasiswa Prestasi',
            'type' => 'merit',
            'discount_type' => 'percentage',
            'discount_percentage' => 50,
            'duration_semesters' => 8,
            'is_active' => true,
        ])
        ->assertRedirect(route('admin.financial.scholarships.index'));

    $scholarship = Scholarship::first();
    expect($scholarship)->not->toBeNull();

    financialMasterActingAs($setup['admin'])
        ->get(route('admin.financial.scholarships.index'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('Admin/Financial/Scholarship/Index')
            ->where('stats.total', 1));

    financialMasterActingAs($setup['admin'])
        ->post(route('admin.financial.scholarships.toggle', $scholarship))
        ->assertRedirect();

    expect($scholarship->fresh()->is_active)->toBeFalse();

    financialMasterActingAs($setup['admin'])
        ->delete(route('admin.financial.scholarships.destroy', $scholarship))
        ->assertRedirect();

    expect(Scholarship::count())->toBe(0)
        ->and(Scholarship::onlyTrashed()->count())->toBe(1);

    financialMasterActingAs($setup['admin'])
        ->post(route('admin.financial.scholarships.restore', $scholarship->id))
        ->assertRedirect();

    expect(Scholarship::count())->toBe(1);
});

it('beasiswa nominal wajib isi fixed amount', function () {
    $setup = financialMasterSetup();

    financialMasterActingAs($setup['admin'])
        ->post(route('admin.financial.scholarships.store'), [
            'name' => 'Beasiswa Nominal',
            'type' => 'need_based',
            'discount_type' => 'fixed',
            'duration_semesters' => 4,
            'is_active' => true,
        ])
        ->assertSessionHasErrors('fixed_amount');

    financialMasterActingAs($setup['admin'])
        ->post(route('admin.financial.scholarships.store'), [
            'name' => 'Beasiswa Nominal',
            'type' => 'need_based',
            'discount_type' => 'fixed',
            'fixed_amount' => 2500000,
            'duration_semesters' => 4,
            'is_active' => true,
        ])
        ->assertRedirect();

    expect(Scholarship::count())->toBe(1);
});

it('menghitung potongan beasiswa dengan benar', function () {
    $percentage = Scholarship::create([
        'name' => 'Persen', 'type' => 'merit', 'discount_type' => 'percentage',
        'discount_percentage' => 50, 'duration_semesters' => 8, 'is_active' => true,
    ]);
    $fixed = Scholarship::create([
        'name' => 'Tetap', 'type' => 'need_based', 'discount_type' => 'fixed',
        'fixed_amount' => 2500000, 'duration_semesters' => 4, 'is_active' => true,
    ]);
    $inactive = Scholarship::create([
        'name' => 'Mati', 'type' => 'merit', 'discount_type' => 'percentage',
        'discount_percentage' => 100, 'duration_semesters' => 8, 'is_active' => false,
    ]);

    expect($percentage->calculateDiscount(10000000))->toBe(5000000.0)
        ->and($fixed->calculateDiscount(1000000))->toBe(1000000.0)
        ->and($inactive->calculateDiscount(10000000))->toBe(0.0);
});
