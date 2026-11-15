<?php

use App\Models\Access\Role;
use App\Models\Settings\Campus;
use App\Models\Settings\System;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia as Assert;

function setupWizardPayload(array $overrides = []): array
{
    return array_merge([
        'app_name' => 'Kampus Uji',
        'app_version' => '1.0.0',
        'app_description' => 'Sistem informasi akademik kampus uji.',
        'app_url' => 'https://kampus-uji.test',
        'app_email' => 'admin@kampus-uji.test',
        'maintenance_mode' => false,
        'enable_captcha' => false,
        'max_login_attempts' => 5,
        'login_decay_seconds' => 300,
        'campus_name' => 'Kampus Uji',
        'campus_domain' => 'kampus-uji.test',
        'campus_phone' => '021-1234567',
        'campus_whatsapp' => '081234567890',
        'campus_email_info' => 'info@kampus-uji.test',
        'campus_email_humas' => 'humas@kampus-uji.test',
        'campus_address' => 'Jl. Pendidikan No. 1',
        'campus_city' => 'Bandung',
        'campus_province' => 'Jawa Barat',
        'campus_postal_code' => '40123',
        'campus_tiktok' => '',
        'campus_instagram' => '',
        'campus_facebook' => '',
        'campus_xtwitter' => '',
        'campus_linkedin' => '',
        'admin_first_name' => 'Admin',
        'admin_last_name' => 'Utama',
        'admin_username' => 'admin.utama',
        'admin_email' => 'admin.utama@kampus-uji.test',
        'admin_phone' => '081200000001',
        'admin_password' => 'password123',
        'admin_password_confirmation' => 'password123',
        'admin_gender' => 'Laki-laki',
        'admin_place_of_birth' => 'Bandung',
        'admin_date_of_birth' => '1990-01-01',
        'admin_religion' => 'Islam',
        'confirm_data' => true,
        'accept_terms' => true,
    ], $overrides);
}

function markSystemAsInstalled(): System
{
    $system = new System([
        'app_name' => 'NexaCampus Test',
        'app_version' => 'test',
        'app_description' => 'NexaCampus Test',
        'app_url' => 'https://example.test',
        'app_email' => 'campus@example.test',
    ]);
    $system->forceFill(['is_installed' => true]);
    $system->save();

    return $system;
}

it('renders the setup wizard when the system is not installed', function () {
    $this->get('/welcome')
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Setup/Wizard')
            ->where('defaults.app_name', 'NexaCampus')
            ->has('defaults.campus_name')
            ->has('urls.submit'));
});

it('redirects the setup wizard to login when the system is already installed', function () {
    markSystemAsInstalled();

    $this->get('/welcome')->assertRedirect('/auth/login');
});

it('installs the system, campus, and superuser account', function () {
    Storage::fake('public');

    $this->post('/welcome', setupWizardPayload([
        'app_favicon' => UploadedFile::fake()->create('favicon.png', 12, 'image/png'),
        'app_logo_vertikal' => UploadedFile::fake()->create('logo-vertikal.png', 24, 'image/png'),
        'app_logo_horizontal' => UploadedFile::fake()->create('logo-horizontal.png', 24, 'image/png'),
    ]))
        ->assertRedirect('/auth/login')
        ->assertSessionHas('success');

    $system = System::query()->firstOrFail();

    expect($system->is_installed)->toBeTrue()
        ->and($system->app_name)->toBe('Kampus Uji')
        ->and($system->max_login_attempts)->toBe(5);

    $campus = Campus::query()->firstOrFail();

    expect($campus->name)->toBe('Kampus Uji')
        ->and($campus->domain)->toBe('kampus-uji.test');

    $admin = User::query()->where('username', 'admin.utama')->firstOrFail();

    expect($admin->email)->toBe('admin.utama@kampus-uji.test')
        ->and($admin->hasRole('superuser'))->toBeTrue()
        ->and(Hash::check('password123', $admin->password))->toBeTrue();

    expect(Storage::disk('public')->allFiles('images/logo'))->toHaveCount(3);

    // resources:sync dijalankan agar akun superuser langsung punya akses.
    expect(Role::findByName('superuser', 'web')->hasPermissionTo('faculty.viewAny'))->toBeTrue();
});

it('validates the required installation fields', function () {
    $this->post('/welcome', [])
        ->assertSessionHasErrors([
            'app_name',
            'app_version',
            'app_url',
            'app_email',
            'campus_name',
            'campus_domain',
            'admin_first_name',
            'admin_username',
            'admin_email',
            'admin_phone',
            'admin_password',
            'admin_gender',
            'confirm_data',
            'accept_terms',
        ]);

    expect(System::query()->count())->toBe(0)
        ->and(Campus::query()->count())->toBe(0)
        ->and(User::query()->count())->toBe(0);
});

it('does not run the installer twice', function () {
    markSystemAsInstalled();

    $this->post('/welcome', setupWizardPayload())
        ->assertRedirect('/auth/login');

    expect(Campus::query()->count())->toBe(0)
        ->and(User::query()->count())->toBe(0);
});

it('rejects duplicate admin credentials', function () {
    User::factory()->create([
        'username' => 'admin.utama',
        'email' => 'admin.utama@kampus-uji.test',
    ]);

    $this->post('/welcome', setupWizardPayload())
        ->assertSessionHasErrors(['admin_username', 'admin_email']);
});
