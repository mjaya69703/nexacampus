<?php

use App\Models\User;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Role;

function ensureAuthSystemInstalled(): void
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

function makeAuthUser(array $roles): User
{
    foreach ($roles as $role) {
        Role::firstOrCreate(['name' => $role, 'guard_name' => 'web']);
    }

    $user = User::factory()->create();

    foreach ($roles as $role) {
        $user->assignRole($role);
    }

    return $user;
}

it('renders the login page through Inertia', function () {
    ensureAuthSystemInstalled();

    $response = $this->get('/auth/login');

    $response->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('Auth/Login')
            ->has('campus')
            ->has('links'));

    expect($response->getContent())
        ->not->toContain('tabler.css')
        ->toContain('data-page');
});

it('redirects guest away from select-role to login', function () {
    ensureAuthSystemInstalled();

    $this->get('/auth/select-role')->assertRedirect('/auth/login');
});

it('logs in a single-role user directly to its dashboard', function () {
    ensureAuthSystemInstalled();
    $user = makeAuthUser(['student']);

    $response = $this->withHeader('X-Inertia', 'true')->post('/auth/login', [
        'login' => $user->username,
        'password' => 'password',
    ]);

    // Dashboard Livewire → 409 X-Inertia-Location (full visit, bukan 302).
    $response->assertStatus(409);
    $response->assertHeader('X-Inertia-Location', route('student.dashboard.index'));
    expect(session('active_role'))->toBe('student');
    $this->assertAuthenticatedAs($user);
});

it('sends a multi-role user to select-role and stores the chosen role', function () {
    ensureAuthSystemInstalled();
    $user = makeAuthUser(['student', 'lecturer']);

    $this->post('/auth/login', [
        'login' => $user->username,
        'password' => 'password',
    ])->assertRedirect(route('auth.select-role'));

    $this->actingAs($user)
        ->get('/auth/select-role')
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('Auth/SelectRole')
            ->has('roles')
            ->has('roleMeta'));

    $this->actingAs($user)
        ->withHeader('X-Inertia', 'true')
        ->post('/auth/select-role', ['role' => 'lecturer'])
        ->assertStatus(409)
        ->assertHeader('X-Inertia-Location', route('lecturer.dashboard.index'));

    expect(session('active_role'))->toBe('lecturer');
});

it('rejects an invalid role choice', function () {
    ensureAuthSystemInstalled();
    $user = makeAuthUser(['student']);

    $this->actingAs($user)
        ->post('/auth/select-role', ['role' => 'superuser'])
        ->assertSessionHasErrors('role');
});

it('rejects invalid credentials', function () {
    ensureAuthSystemInstalled();
    $user = makeAuthUser(['student']);

    $this->post('/auth/login', [
        'login' => $user->username,
        'password' => 'salah-password',
    ])->assertSessionHasErrors('login');

    $this->assertGuest();
});
