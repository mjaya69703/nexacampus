<?php

namespace App\Http\Controllers\Setup;

use App\Http\Controllers\Controller;
use App\Models\Access\Role;
use App\Models\Settings\Campus;
use App\Models\Settings\System;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class SetupWizardController extends Controller
{
    /**
     * Tampilkan wizard instalasi.
     */
    public function show(): Response|RedirectResponse
    {
        if (! Schema::hasTable('systems')) {
            abort(500, 'Database belum di migrate. Jalankan: php artisan migrate');
        }

        if (System::query()->where('is_installed', true)->exists()) {
            return redirect()->route('auth.signin-index');
        }

        return Inertia::render('Setup/Wizard', [
            'defaults' => $this->defaults(),
            'urls' => [
                'submit' => route('system.setup-wizard.store'),
            ],
        ]);
    }

    /**
     * Simpan konfigurasi awal dan tandai sistem terpasang.
     */
    public function store(Request $request): RedirectResponse
    {
        if (System::query()->where('is_installed', true)->exists()) {
            return redirect()->route('auth.signin-index');
        }

        $validated = $request->validate(
            $this->rules(),
            $this->messages(),
            $this->attributes(),
        );

        DB::transaction(function () use ($request, $validated): void {
            $logos = $this->storeLogos($request);

            $systemData = [
                'app_name' => $validated['app_name'],
                'app_version' => $validated['app_version'],
                'app_description' => $validated['app_description'] ?? null,
                'app_url' => $validated['app_url'],
                'app_email' => $validated['app_email'],
                'maintenance_mode' => (bool) ($validated['maintenance_mode'] ?? false),
                'enable_captcha' => (bool) ($validated['enable_captcha'] ?? false),
                'max_login_attempts' => (int) ($validated['max_login_attempts'] ?? 5),
                'login_decay_seconds' => (int) ($validated['login_decay_seconds'] ?? 300),
            ];

            if (isset($logos['app_favicon'])) {
                $systemData['app_favicon'] = $logos['app_favicon'];
            }

            if (isset($logos['app_logo_vertikal'])) {
                $systemData['app_logo_vertikal'] = $logos['app_logo_vertikal'];
            }

            if (isset($logos['app_logo_horizontal'])) {
                $systemData['app_logo_horizontal'] = $logos['app_logo_horizontal'];
            }

            $system = new System($systemData);
            $system->forceFill(['is_installed' => true]);
            $system->save();

            $campusData = [
                'name' => $validated['campus_name'],
                'domain' => $validated['campus_domain'],
                'phone' => $validated['campus_phone'] ?? '',
                'whatsapp' => $validated['campus_whatsapp'] ?? '',
                'email_info' => $validated['campus_email_info'] ?? '',
                'email_humas' => $validated['campus_email_humas'] ?? '',
                'address' => $validated['campus_address'] ?? null,
                'city' => $validated['campus_city'] ?? null,
                'province' => $validated['campus_province'] ?? null,
                'postal_code' => $validated['campus_postal_code'] ?? null,
                'tiktok' => $validated['campus_tiktok'] ?? null,
                'instagram' => $validated['campus_instagram'] ?? null,
                'facebook' => $validated['campus_facebook'] ?? null,
                'xtwitter' => $validated['campus_xtwitter'] ?? null,
                'linkedin' => $validated['campus_linkedin'] ?? null,
            ];

            if (isset($logos['app_favicon'])) {
                $campusData['favicon'] = $logos['app_favicon'];
            }

            if (isset($logos['app_logo_vertikal'])) {
                $campusData['logo_vertikal'] = $logos['app_logo_vertikal'];
            }

            if (isset($logos['app_logo_horizontal'])) {
                $campusData['logo_horizontal'] = $logos['app_logo_horizontal'];
            }

            Campus::create($campusData);

            $admin = User::create([
                'first_name' => $validated['admin_first_name'],
                'last_name' => $validated['admin_last_name'] ?? null,
                'username' => $validated['admin_username'],
                'email' => $validated['admin_email'],
                'phone' => $validated['admin_phone'],
                'gender' => $validated['admin_gender'],
                'place_of_birth' => $validated['admin_place_of_birth'] ?? null,
                'date_of_birth' => $validated['admin_date_of_birth'] ?? null,
                'religion' => $validated['admin_religion'] ?? null,
                'code' => Str::upper(Str::random(10)),
                'password' => $validated['admin_password'],
            ]);

            $superuserRole = Role::firstOrCreate([
                'name' => 'superuser',
                'guard_name' => 'web',
            ]);

            $admin->assignRole($superuserRole);
        });

        // View composer menyimpan hasil query pertama (null saat wizard dibuka)
        // secara permanen; bersihkan agar data instalasi langsung terbaca.
        Cache::forget('global_system');
        Cache::forget('global_campus');

        // Siapkan permission & menu agar akun superuser langsung bisa dipakai.
        Artisan::call('resources:sync');

        return redirect()->route('auth.signin-index')
            ->with('success', 'Instalasi berhasil diselesaikan! Silakan login dengan akun admin yang telah dibuat.');
    }

    /**
     * @return array<string, mixed>
     */
    private function defaults(): array
    {
        return [
            'app_name' => 'NexaCampus',
            'app_version' => '1.0.0',
            'app_description' => 'Sistem Informasi Akademik',
            'app_url' => config('app.url') ?: url('/'),
            'app_email' => '',
            'maintenance_mode' => false,
            'enable_captcha' => false,
            'max_login_attempts' => 5,
            'login_decay_seconds' => 300,
            'campus_name' => '',
            'campus_domain' => '',
            'campus_phone' => '',
            'campus_whatsapp' => '',
            'campus_email_info' => '',
            'campus_email_humas' => '',
            'campus_address' => '',
            'campus_city' => '',
            'campus_province' => '',
            'campus_postal_code' => '',
            'campus_tiktok' => '',
            'campus_instagram' => '',
            'campus_facebook' => '',
            'campus_xtwitter' => '',
            'campus_linkedin' => '',
            'admin_first_name' => '',
            'admin_last_name' => '',
            'admin_username' => '',
            'admin_email' => '',
            'admin_phone' => '',
            'admin_password' => '',
            'admin_password_confirmation' => '',
            'admin_gender' => '',
            'admin_place_of_birth' => '',
            'admin_date_of_birth' => '',
            'admin_religion' => '',
        ];
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    private function rules(): array
    {
        return [
            // System
            'app_name' => ['required', 'string', 'max:255'],
            'app_version' => ['required', 'string', 'max:50'],
            'app_description' => ['nullable', 'string', 'max:500'],
            'app_url' => ['required', 'url:http,https', 'max:255'],
            'app_email' => ['required', 'email', 'max:255'],
            'maintenance_mode' => ['boolean'],
            'enable_captcha' => ['boolean'],
            'max_login_attempts' => ['required', 'integer', 'min:0', 'max:100'],
            'login_decay_seconds' => ['required', 'integer', 'min:0', 'max:86400'],
            'app_favicon' => ['nullable', 'file', 'mimes:png,ico', 'max:2048'],
            'app_logo_vertikal' => ['nullable', 'file', 'mimes:png,jpg,jpeg', 'max:4096'],
            'app_logo_horizontal' => ['nullable', 'file', 'mimes:png,jpg,jpeg', 'max:4096'],

            // Campus
            'campus_name' => ['required', 'string', 'max:255'],
            'campus_domain' => ['required', 'string', 'max:255'],
            'campus_phone' => ['nullable', 'string', 'max:20'],
            'campus_whatsapp' => ['nullable', 'string', 'max:20'],
            'campus_email_info' => ['nullable', 'email', 'max:255'],
            'campus_email_humas' => ['nullable', 'email', 'max:255'],
            'campus_address' => ['nullable', 'string', 'max:500'],
            'campus_city' => ['nullable', 'string', 'max:100'],
            'campus_province' => ['nullable', 'string', 'max:100'],
            'campus_postal_code' => ['nullable', 'string', 'max:20'],
            'campus_tiktok' => ['nullable', 'string', 'max:255'],
            'campus_instagram' => ['nullable', 'string', 'max:255'],
            'campus_facebook' => ['nullable', 'string', 'max:255'],
            'campus_xtwitter' => ['nullable', 'string', 'max:255'],
            'campus_linkedin' => ['nullable', 'string', 'max:255'],

            // Super admin
            'admin_first_name' => ['required', 'string', 'max:255'],
            'admin_last_name' => ['nullable', 'string', 'max:255'],
            'admin_username' => ['required', 'string', 'max:255', Rule::unique('users', 'username')],
            'admin_email' => ['required', 'email', 'max:255', Rule::unique('users', 'email')],
            'admin_phone' => ['required', 'string', 'max:20', Rule::unique('users', 'phone')],
            'admin_password' => ['required', 'string', 'min:8', 'confirmed'],
            'admin_password_confirmation' => ['required', 'string', 'min:8'],
            'admin_gender' => ['required', Rule::in(['Laki-laki', 'Perempuan'])],
            'admin_place_of_birth' => ['nullable', 'string', 'max:255'],
            'admin_date_of_birth' => ['nullable', 'date'],
            'admin_religion' => ['nullable', Rule::in(['Islam', 'Kristen', 'Katolik', 'Hindu', 'Buddha', 'Khonghucu'])],

            // Konfirmasi
            'confirm_data' => ['accepted'],
            'accept_terms' => ['accepted'],
        ];
    }

    /**
     * @return array<string, string>
     */
    private function messages(): array
    {
        return [
            'required' => 'Kolom :attribute wajib diisi.',
            'email' => 'Format :attribute tidak valid.',
            'url' => 'Format :attribute harus berupa URL yang valid.',
            'integer' => ':attribute harus berupa angka.',
            'min' => ':attribute minimal :min.',
            'max' => ':attribute maksimal :max.',
            'confirmed' => 'Konfirmasi :attribute tidak cocok.',
            'unique' => ':attribute sudah digunakan.',
            'accepted' => ':attribute harus disetujui.',
            'in' => 'Pilihan :attribute tidak valid.',
            'date' => ':attribute harus berupa tanggal yang valid.',
            'mimes' => ':attribute harus berupa file dengan tipe: :values.',
        ];
    }

    /**
     * @return array<string, string>
     */
    private function attributes(): array
    {
        return [
            'app_name' => 'nama aplikasi',
            'app_version' => 'versi aplikasi',
            'app_description' => 'deskripsi aplikasi',
            'app_url' => 'URL aplikasi',
            'app_email' => 'email sistem',
            'max_login_attempts' => 'batas percobaan login',
            'login_decay_seconds' => 'durasi kunci login',
            'app_favicon' => 'favicon',
            'app_logo_vertikal' => 'logo vertikal',
            'app_logo_horizontal' => 'logo horizontal',
            'campus_name' => 'nama kampus',
            'campus_domain' => 'domain kampus',
            'campus_phone' => 'telepon kampus',
            'campus_whatsapp' => 'WhatsApp kampus',
            'campus_email_info' => 'email info',
            'campus_email_humas' => 'email humas',
            'campus_address' => 'alamat kampus',
            'admin_first_name' => 'nama depan',
            'admin_last_name' => 'nama belakang',
            'admin_username' => 'username',
            'admin_email' => 'email',
            'admin_phone' => 'nomor HP',
            'admin_password' => 'password',
            'admin_password_confirmation' => 'konfirmasi password',
            'admin_gender' => 'jenis kelamin',
            'admin_place_of_birth' => 'tempat lahir',
            'admin_date_of_birth' => 'tanggal lahir',
            'admin_religion' => 'agama',
            'confirm_data' => 'konfirmasi kebenaran data',
            'accept_terms' => 'persetujuan syarat & ketentuan',
        ];
    }

    /**
     * Simpan logo/favicon ke disk public dan kembalikan nama filenya.
     *
     * @return array<string, string>
     */
    private function storeLogos(Request $request): array
    {
        $stored = [];

        foreach (['app_favicon', 'app_logo_vertikal', 'app_logo_horizontal'] as $key) {
            if (! $request->hasFile($key)) {
                continue;
            }

            $file = $request->file($key);
            $filename = $file->hashName();
            $file->storeAs('images/logo', $filename, 'public');
            $stored[$key] = $filename;
        }

        return $stored;
    }
}
