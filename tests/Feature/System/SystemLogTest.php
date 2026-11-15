<?php

use App\Models\Settings\NotificationLog;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Activitylog\Models\Activity;
use Spatie\Permission\Models\Permission as SpatiePermission;
use Spatie\Permission\Models\Role as SpatieRole;

uses(RefreshDatabase::class);

function systemLogSetup(): array
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

    return compact('operator');
}

function giveSystemLogPermissions(array $names): void
{
    $role = SpatieRole::where('name', 'operator')->firstOrFail();

    foreach ($names as $name) {
        $role->givePermissionTo(SpatiePermission::firstOrCreate([
            'name' => $name,
            'guard_name' => 'web',
        ]));
    }
}

function actingSystemOperator($operator)
{
    return test()->actingAs($operator)->withSession(['active_role' => 'operator']);
}

it('menampilkan log notifikasi dengan statistik dan filter', function () {
    $setup = systemLogSetup();
    giveSystemLogPermissions(['notification-log.viewAny']);

    NotificationLog::create([
        'event_key' => 'invoice.issued', 'channel' => 'whatsapp', 'provider' => 'cloud',
        'status' => 'sent', 'recipient_name' => 'Budi', 'recipient_phone' => '0811',
        'subject' => 'Tagihan', 'sent_at' => now(),
    ]);
    NotificationLog::create([
        'event_key' => 'invoice.overdue', 'channel' => 'web_push', 'provider' => 'vapid',
        'status' => 'failed', 'recipient_name' => 'Siti', 'error_message' => 'expired',
    ]);

    actingSystemOperator($setup['operator'])
        ->get('/admin/system/notification-logs')
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('Admin/System/NotificationLog/Index')
            ->where('stats.total', 2)
            ->where('stats.sent', 1)
            ->where('stats.failed', 1)
            ->has('data.rows', 2)
            ->where('data.rows.0.statusTone', 'red'));

    actingSystemOperator($setup['operator'])
        ->get('/admin/system/notification-logs?channel=whatsapp&status=sent')
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->has('data.rows', 1)
            ->where('data.rows.0.event', 'invoice.issued'));
});

it('menolak log notifikasi tanpa permission dan mengekspor hasil saring', function () {
    $setup = systemLogSetup();

    actingSystemOperator($setup['operator'])
        ->get('/admin/system/notification-logs')
        ->assertForbidden();

    giveSystemLogPermissions(['notification-log.viewAny']);

    NotificationLog::create([
        'event_key' => 'invoice.issued', 'channel' => 'whatsapp',
        'status' => 'sent', 'recipient_name' => 'Budi',
    ]);

    $response = actingSystemOperator($setup['operator'])
        ->get('/admin/system/notification-logs/export?format=csv&q=invoice')
        ->assertOk();

    expect($response->headers->get('Content-Type'))->toContain('text/csv');
});

it('menampilkan log aktivitas beserta detailnya', function () {
    $setup = systemLogSetup();
    giveSystemLogPermissions(['activity-log.viewAny', 'activity-log.view']);

    $activity = Activity::create([
        'log_name' => 'audit-uji', 'description' => 'pengujian-unik-123',
        'properties' => ['attributes' => ['name' => 'Budi']],
    ]);

    actingSystemOperator($setup['operator'])
        ->get('/admin/system/activity-logs?q=pengujian-unik-123')
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('Admin/System/ActivityLog/Index')
            ->has('stats')
            ->has('data.rows', 1)
            ->where('data.rows.0.preview', 'Target: Budi')
            ->where('data.rows.0.causer', 'System'));

    actingSystemOperator($setup['operator'])
        ->get("/admin/system/activity-logs/{$activity->id}")
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('Admin/System/ActivityLog/Show')
            ->where('activity.description', 'pengujian-unik-123')
            ->where('activity.causer', 'System'));
});

it('menolak log aktivitas tanpa permission', function () {
    $setup = systemLogSetup();

    actingSystemOperator($setup['operator'])
        ->get('/admin/system/activity-logs')
        ->assertForbidden();
});
