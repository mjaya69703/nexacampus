<?php

use App\Models\Settings\Campus;
use App\Models\Settings\NotificationSetting;
use App\Models\Settings\System;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Spatie\Permission\Models\Permission as SpatiePermission;
use Spatie\Permission\Models\Role as SpatieRole;

uses(RefreshDatabase::class);

function settingTestSetup(): array
{
    SpatieRole::firstOrCreate(['name' => 'operator', 'guard_name' => 'web']);

    $system = System::create([
        'app_name' => 'NexaCampus Test',
        'app_version' => '1.0.0',
        'app_description' => 'Test',
        'app_url' => 'http://localhost',
        'app_email' => 'test@localhost',
    ]);
    $system->forceFill(['is_installed' => true])->save();

    $operator = App\Models\User::factory()->create();
    $operator->assignRole('operator');

    return compact('operator');
}

function giveSettingPermission(): void
{
    SpatieRole::where('name', 'operator')->firstOrFail()
        ->givePermissionTo(SpatiePermission::firstOrCreate([
            'name' => 'setting.viewAny',
            'guard_name' => 'web',
        ]));
}

function actingSettingOperator($operator)
{
    return test()->actingAs($operator)->withSession(['active_role' => 'operator']);
}

function settingPayload(array $overrides = []): array
{
    return array_merge([
        'system' => [
            'app_name' => 'NexaCampus Baru',
            'app_version' => '2.0.0',
            'maintenance_mode' => false,
            'enable_captcha' => true,
            'max_login_attempts' => 5,
            'login_decay_seconds' => 300,
        ],
        'campus' => ['name' => 'Kampus Utama'],
        'notification' => [
            'whatsapp_enabled' => false,
            'web_push_enabled' => false,
            'whatsapp_provider' => 'official_cloud_api',
            'fallback_channel' => 'in_app',
            'retry_attempts' => 3,
            'timeout_seconds' => 15,
            'log_retention_days' => 90,
            'provider_response_retention_days' => 30,
        ],
    ], $overrides);
}

it('menampilkan halaman pengaturan dengan 3 model', function () {
    $setup = settingTestSetup();
    giveSettingPermission();

    NotificationSetting::current();

    actingSettingOperator($setup['operator'])
        ->get('/admin/system/settings')
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('Admin/System/Setting/Index')
            ->has('shell')
            ->has('system')
            ->has('campus')
            ->has('notification')
            ->has('health')
            ->has('sidecar')
            ->has('urls'));
});

it('menyimpan pengaturan dan membersihkan cache global', function () {
    $setup = settingTestSetup();
    giveSettingPermission();

    Cache::put('global_campus', 'lama');
    Cache::put('global_system', 'lama');

    actingSettingOperator($setup['operator'])
        ->put('/admin/system/settings', settingPayload())
        ->assertRedirect('/admin/system/settings')
        ->assertSessionHas('success');

    expect(System::first()->app_name)->toBe('NexaCampus Baru')
        ->and(Campus::first()->name)->toBe('Kampus Utama')
        ->and(NotificationSetting::current()->timeout_seconds)->toBe(15)
        ->and(Cache::has('global_campus'))->toBeFalse()
        ->and(Cache::has('global_system'))->toBeFalse();
});

it('memvalidasi url aplikasi dan batas retensi', function () {
    $setup = settingTestSetup();
    giveSettingPermission();

    $payload = settingPayload();
    $payload['system']['app_url'] = 'bukan-url';
    $payload['notification']['provider_response_retention_days'] = 9999;

    actingSettingOperator($setup['operator'])
        ->put('/admin/system/settings', $payload)
        ->assertSessionHasErrors(['system.app_url', 'notification.provider_response_retention_days']);
});

it('mengirim test whatsapp yang ter-skip tanpa konfigurasi', function () {
    $setup = settingTestSetup();
    giveSettingPermission();

    NotificationSetting::current();

    actingSettingOperator($setup['operator'])
        ->post('/admin/system/settings/test-whatsapp', ['recipient' => '0811'])
        ->assertSessionHas('warning');
});

it('me-refresh status sidecar tanpa sidecar berjalan', function () {
    $setup = settingTestSetup();
    giveSettingPermission();

    actingSettingOperator($setup['operator'])
        ->post('/admin/system/settings/sidecar-refresh')
        ->assertSessionHas('warning');
});

it('menolak session whatsapp tidak valid dan akses tanpa permission', function () {
    $setup = settingTestSetup();

    actingSettingOperator($setup['operator'])
        ->get('/admin/system/settings')
        ->assertForbidden();

    giveSettingPermission();

    actingSettingOperator($setup['operator'])
        ->post('/admin/system/settings/use-session', ['session' => 'tidak valid!'])
        ->assertSessionHasErrors('session');
});
