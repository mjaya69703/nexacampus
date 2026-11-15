<?php

use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Spatie\Permission\Models\Role;

function profilePageTestSetup(array $roles = ['student']): User
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

    foreach ($roles as $role) {
        Role::firstOrCreate(['name' => $role, 'guard_name' => 'web']);
    }

    $user = User::factory()->create();
    $user->assignRole($roles);

    return $user;
}

it('redirects guest away from profile to login', function () {
    profilePageTestSetup();

    $this->get('/profile')->assertRedirect('/auth/login');
});

it('renders the profile dashboard through Inertia', function () {
    $user = profilePageTestSetup();

    $this->actingAs($user)
        ->withSession(['active_role' => 'student'])
        ->get('/profile')
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('Shared/Profile')
            ->has('shell')
            ->has('shell.menus')
            ->has('shell.commandItems')
            ->where('shell.pageTitle', 'User Profile')
            ->has('profile')
            ->has('completeness')
            ->has('stats')
            ->has('options')
            ->where('profile.email', $user->email));
});

it('updates biodata and persists the changes', function () {
    $user = profilePageTestSetup();

    $this->actingAs($user)
        ->withSession(['active_role' => 'student'])
        ->post('/profile', [
            'first_name' => 'Budi',
            'last_name' => 'Santoso',
            'username' => $user->username,
            'phone' => $user->phone,
            'email' => $user->email,
            'place_of_birth' => 'Bandung',
            'date_of_birth' => '2000-01-15',
        ])
        ->assertRedirect('/profile')
        ->assertSessionHas('success');

    expect($user->fresh())
        ->first_name->toBe('Budi')
        ->place_of_birth->toBe('Bandung');
});

it('changes password with the correct current password', function () {
    $user = profilePageTestSetup();

    $this->actingAs($user)
        ->withSession(['active_role' => 'student'])
        ->post('/profile/password', [
            'current_password' => 'password',
            'new_password' => 'rahasia-baru-123',
            'new_password_confirmation' => 'rahasia-baru-123',
        ])
        ->assertRedirect('/profile')
        ->assertSessionHas('success');

    expect(Hash::check('rahasia-baru-123', $user->fresh()->password))->toBeTrue();
});

it('rejects password change with the wrong current password', function () {
    $user = profilePageTestSetup();

    $this->actingAs($user)
        ->withSession(['active_role' => 'student'])
        ->post('/profile/password', [
            'current_password' => 'salah',
            'new_password' => 'rahasia-baru-123',
            'new_password_confirmation' => 'rahasia-baru-123',
        ])
        ->assertSessionHasErrors('current_password');

    expect(Hash::check('password', $user->fresh()->password))->toBeTrue();
});

it('stores a certificate submission with its document', function () {
    Storage::fake();
    $user = profilePageTestSetup();

    $this->actingAs($user)
        ->withSession(['active_role' => 'student'])
        ->post('/profile/certificates', [
            'type' => 'certification',
            'title' => 'AWS Certified Developer',
            'organizer' => 'Amazon Web Services',
            'start_date' => '2026-01-10',
            'document' => UploadedFile::fake()->create('sertifikat.pdf', 100, 'application/pdf'),
        ])
        ->assertRedirect('/profile')
        ->assertSessionHas('success');

    $record = $user->fresh()->developmentRecords()->first();

    expect($record)->not->toBeNull()
        ->and($record->title)->toBe('AWS Certified Developer')
        ->and($record->is_verified)->toBeFalse()
        ->and($record->attachments)->toHaveCount(1);
});
