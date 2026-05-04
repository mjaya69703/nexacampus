<?php

use Livewire\Component;
use Livewire\WithFileUploads;
use App\Models\Settings\System;
use App\Models\Settings\Campus;
use Illuminate\Support\Facades\DB;
use App\Models\User;
use Livewire\Attributes\Session;
use Illuminate\Support\Facades\Hash;

new class extends Component {
    use WithFileUploads;

    #[Session]
    public $step = 1; // Step saat ini

    #[Session]
    public $systemForm = [];
    #[Session]
    public $campusForm = [];
    #[Session]
    public $adminForm = [];

    public $appFavicon = null;
    public $appLogoVertikal = null;
    public $appLogoHorizontal = null;

    public function mount()
    {
        if (System::first()?->is_installed) {
            return redirect('/');
        }
    }

    public function prev()
    {
        if ($this->step === 1) {
            return; // Tidak bisa mundur dari step pertama
        }
        $this->step--;
    }

    public function next()
    {
        if ($this->step == 1) {
            $validatedSystem = $this->validate([
                'systemForm.app_name' => 'required|string|max:255',
                'systemForm.app_version' => 'required|string|max:50',
                'systemForm.app_description' => 'nullable|string|max:500',
                'systemForm.app_url' => 'required|url|max:255',
                'systemForm.app_email' => 'required|email|max:255',
                'systemForm.maintenance_mode' => 'boolean',
                'systemForm.enable_captcha' => 'boolean',
                'systemForm.login_decay_seconds' => 'required|integer|min:0',
                'systemForm.max_login_attempts' => 'required|integer|min:0',
                // Validasi untuk file logo bisa ditambahkan di sini jika diperlukan
                'appFavicon' => 'nullable|image|mimes:png,ico|max:2048',
                'appLogoVertikal' => 'nullable|image|mimes:png,jpg,jpeg|max:4096',
                'appLogoHorizontal' => 'nullable|image|mimes:png,jpg,jpeg|max:4096',
            ]);
        }

        if ($this->step == 2) {
            $validatedCampus = $this->validate([
                'campusForm.name' => 'required|string|max:255',
                'campusForm.domain' => 'required|string|max:255',
                // 'campusForm.tahun_akademik_id' => 'required|string|max:50',
                'campusForm.phone' => 'nullable|string|max:20',
                'campusForm.whatsapp' => 'nullable|string|max:20',
                // 'campusForm.faximile' => 'nullable|string|max:20',
                'campusForm.email_info' => 'nullable|email|max:255',
                'campusForm.email_humas' => 'nullable|email|max:255',
                'campusForm.address' => 'nullable|string|max:500',
                'campusForm.city' => 'nullable|string|max:100',
                'campusForm.province' => 'nullable|string|max:100',
                'campusForm.postal_code' => 'nullable|string|max:20',
                'campusForm.tiktok' => 'nullable|string|max:255',
                'campusForm.instagram' => 'nullable|string|max:255',
                'campusForm.facebook' => 'nullable|string|max:255',
                'campusForm.xtwitter' => 'nullable|string|max:255',
                'campusForm.linkedin' => 'nullable|string|max:255',
            ]);
        }

        if ($this->step == 3) {
            $validatedAdmin = $this->validate([
                'adminForm.first_name' => 'required|string|max:255',
                'adminForm.last_name' => 'nullable|string|max:255',
                'adminForm.username' => 'required|string|max:255|unique:users,username',
                'adminForm.phone' => 'nullable|string|max:20',
                'adminForm.email' => 'required|email|max:255',
                'adminForm.password' => 'required|string|min:8|confirmed',
                'adminForm.password_confirmation' => 'required|string|min:8',
                'adminForm.gender' => 'required|in:Laki-laki,Perempuan',
                'adminForm.place_of_birth' => 'nullable|string|max:255',
                'adminForm.date_of_birth' => 'nullable|date',
                'adminForm.religion' => 'nullable|in:Islam,Katolik,Protestan,Hindu,Buddha,Khonghucu',
            ]);
        }

        if ($this->step < 4) {
            $this->step++;
        }
    }

    public function finish()
    {
        // dd($this->appFavicon, $this->appLogoVertikal, $this->appLogoHorizontal, $this->system, $this->campus, $this->admin);
        DB::beginTransaction();

        // Handle upload logo dan favicon
        if ($this->appFavicon) {
            $filename = $this->appFavicon->hashName() ;
            $this->appFavicon->store('images/logo', 'public');
            $favicon = $filename;
        }

        if ($this->appLogoVertikal) {
            $filename = $this->appLogoVertikal->hashName();
            $this->appLogoVertikal->store('images/logo', 'public');
            $logoVertikal = $filename;
        }

        if ($this->appLogoHorizontal) {
            $filename = $this->appLogoHorizontal->hashName();
            $this->appLogoHorizontal->store('images/logo', 'public');
            $logoHorizontal = $filename;
        }

        // Save data system
        $systemForm = new System();

        $systemForm->app_name = $this->systemForm['app_name'];
        $systemForm->app_version = $this->systemForm['app_version'] ?? null;
        $systemForm->app_description = $this->systemForm['app_description'] ?? null;
        $systemForm->app_url = $this->systemForm['app_url'] ?? null;
        $systemForm->app_email = $this->systemForm['app_email'] ?? null;
        $systemForm->maintenance_mode = $this->systemForm['maintenance_mode'] ?? false;
        $systemForm->enable_captcha = $this->systemForm['enable_captcha'] ?? false;
        $systemForm->max_login_attempts = $this->systemForm['max_login_attempts'] ?? 5;
        $systemForm->login_decay_seconds = $this->systemForm['login_decay_seconds'] ?? 300;
        $systemForm->is_installed = true;

        if (isset($favicon)) {
            $systemForm->app_favicon = $favicon;
        }

        if (isset($logoVertikal)) {
            $systemForm->app_logo_vertikal = $logoVertikal;
        }

        if (isset($logoHorizontal)) {
            $systemForm->app_logo_horizontal = $logoHorizontal;
        }

        $systemForm->save();

        // Save data campus
        $campusForm = new Campus();

        $campusForm->name = $this->campusForm['name'];
        $campusForm->domain = $this->campusForm['domain'];
        $campusForm->phone = $this->campusForm['phone'] ?? null;
        $campusForm->whatsapp = $this->campusForm['whatsapp'] ?? null;
        $campusForm->email_info = $this->campusForm['email_info'] ?? null;
        $campusForm->email_humas = $this->campusForm['email_humas'] ?? null;
        $campusForm->address = $this->campusForm['address'] ?? null;
        $campusForm->city = $this->campusForm['city'] ?? null;
        $campusForm->province = $this->campusForm['province'] ?? null;
        $campusForm->postal_code = $this->campusForm['postal_code'] ?? null;
        $campusForm->tiktok = $this->campusForm['tiktok'] ?? null;
        $campusForm->instagram = $this->campusForm['instagram'] ?? null;
        $campusForm->facebook = $this->campusForm['facebook'] ?? null;
        $campusForm->xtwitter = $this->campusForm['xtwitter'] ?? null;
        $campusForm->linkedin = $this->campusForm['linkedin'] ?? null;

        if (isset($favicon)) {
            $campusForm->favicon = $favicon;
        }

        if (isset($logoVertikal)) {
            $campusForm->logo_vertikal = $logoVertikal;
        }

        if (isset($logoHorizontal)) {
            $campusForm->logo_horizontal = $logoHorizontal;
        }

        $campusForm->save();

        // Save data admin
        $adminForm = new User();

        $adminForm->first_name = $this->adminForm['first_name'];
        $adminForm->last_name = $this->adminForm['last_name'] ?? null;
        $adminForm->username = $this->adminForm['username'];
        $adminForm->email = $this->adminForm['email'];
        $adminForm->code = uniqid();
        $adminForm->phone = $this->adminForm['phone'] ?? null;
        $adminForm->gender = $this->adminForm['gender'];
        $adminForm->place_of_birth = $this->adminForm['place_of_birth'] ?? null;
        $adminForm->date_of_birth = $this->adminForm['date_of_birth'] ?? null;
        $adminForm->religion = $this->adminForm['religion'] ?? null;
        $adminForm->password = Hash::make($this->adminForm['password']);

        $adminForm->save();

        $adminForm->assignRole(['superuser']);

        DB::commit();

        $this->reset();
        session()->flash('success', 'Instalasi berhasil diselesaikan! Silakan login dengan akun admin yang telah dibuat.');
        return redirect()->route('auth.signin-index');
    }

    public function render()
    {
        // Data untuk dikirim ke view dan layout
        $data = [
            'menus' => 'Setup Wizard', // Data menu
            'pages' => 'Neco Siakad', // Data halaman
            'user' => null, // Data user
            'activeRole' => 'admin', // Data role aktif
        ];

        // Kirim data ke view dan layout secara langsung
        return $this->view($data)->layout('layouts.setup', $data);
    }
};
?>

<div class="bg-white w-full max-w-4xl rounded-2xl shadow-xl overflow-hidden flex flex-col md:flex-row min-h-[600px]">

    <!-- Sidebar / Progress -->
    <div class="bg-slate-900 text-white p-8 md:w-1/3 flex flex-col justify-between">
        <div>
            <div class="flex items-center gap-3 mb-8">
                <div class="w-10 h-10 bg-blue-500 rounded-lg flex items-center justify-center">
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6 text-white" fill="none" viewBox="0 0 24 24"
                        stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                            d="M19.428 15.428a2 2 0 00-1.022-.547l-2.384-.477a6 6 0 00-3.86.517l-.318.158a6 6 0 01-3.86.517L6.05 15.21a2 2 0 00-1.806.547M8 4h8l-1 1v5.172a2 2 0 00.586 1.414l5 5c1.26 1.26.367 3.414-1.415 3.414H4.828c-1.782 0-2.674-2.154-1.414-3.414l5-5A2 2 0 009 10.172V5L8 4z" />
                    </svg>
                </div>
                <h1 class="text-xl font-bold tracking-tight">Instalasi System</h1>
            </div>

            <div class="space-y-6 relative">
                <!-- Connecting Line -->
                <div class="absolute left-[11px] top-2 bottom-2 w-0.5 bg-slate-700 -z-10"></div>

                <!-- Step 1 Indicator -->
                <div class="flex items-center gap-3 transition-colors duration-300" id="indicator-1">
                    <div
                        class="w-6 h-6 rounded-full border-2 {{ $step >= 1 ? 'border-blue-500 bg-blue-500' : 'border-slate-600 bg-slate-800' }} flex items-center justify-center text-xs font-bold {{ $step >= 1 ? 'text-white' : 'text-slate-400' }}">
                        {{ $step > 1 ? '✓' : '1' }}</div>
                    <span class="font-medium text-sm">Pengaturan System</span>
                </div>

                <!-- Step 2 Indicator -->
                <div class="flex items-center gap-3 transition-colors duration-300" id="indicator-2">
                    <div
                        class="w-6 h-6 rounded-full border-2 {{ $step >= 2 ? 'border-blue-500 bg-blue-500' : 'border-slate-600 bg-slate-800' }} flex items-center justify-center text-xs font-bold {{ $step >= 2 ? 'text-white' : 'text-slate-400' }}">
                        {{ $step > 2 ? '✓' : '2' }}</div>
                    <span class="font-medium text-sm {{ $step >= 2 ? 'text-blue-500' : 'text-slate-400' }}">Pengaturan
                        Kampus</span>
                </div>

                <!-- Step 3 Indicator -->
                <div class="flex items-center gap-3 transition-colors duration-300" id="indicator-3">
                    <div
                        class="w-6 h-6 rounded-full border-2 {{ $step >= 3 ? 'border-blue-500 bg-blue-500' : 'border-slate-600 bg-slate-800' }} flex items-center justify-center text-xs font-bold {{ $step >= 3 ? 'text-white' : 'text-slate-400' }}">
                        {{ $step > 3 ? '✓' : '3' }}</div>
                    <span class="font-medium text-sm {{ $step >= 3 ? 'text-blue-500' : 'text-slate-400' }}">Buat
                        Akun</span>
                </div>

                <!-- Step 4 Indicator -->
                <div class="flex items-center gap-3 transition-colors duration-300" id="indicator-4">
                    <div
                        class="w-6 h-6 rounded-full border-2 {{ $step >= 4 ? 'border-blue-500 bg-blue-500' : 'border-slate-600 bg-slate-800' }} flex items-center justify-center text-xs font-bold {{ $step >= 4 ? 'text-white' : 'text-slate-400' }}">
                        {{ $step > 4 ? '✓' : '4' }}</div>
                    <span
                        class="font-medium text-sm {{ $step >= 4 ? 'text-blue-500' : 'text-slate-400' }}">Konfirmasi</span>
                </div>
            </div>
        </div>

        <div class="mt-8 text-xs text-slate-400">
            <p>© {{ date('Y F') }} - NecoSys Installer v1.0.0</p>
        </div>
    </div>

    <!-- Content Area -->
    <div class="flex-1 p-8 md:p-12 bg-white relative overflow-y-auto custom-scroll">
        <form wire:submit.prevent="finish" class="max-w-3xl mx-auto">

            <!-- STEP 1: System Settings -->
            @if ($step == 1)
                <div id="step-1" class="step-content {{ $step == 1 ? 'active' : '' }}">
                    <h2 class="text-2xl font-bold text-slate-800 mb-2">Pengaturan System</h2>
                    <p class="text-slate-500 mb-6 text-sm">Konfigurasi dasar aplikasi dan identitas system.</p>

                    <div class="space-y-4">
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                            <div>
                                <label class="block text-xs font-semibold text-slate-600 uppercase mb-1">Nama
                                    Aplikasi</label>
                                <input type="text" wire:model="systemForm.app_name"
                                    class="w-full px-4 py-2 border border-slate-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 outline-none transition-all"
                                    placeholder="Contoh: Kampus Pintar">
                                @error('systemForm.app_name')
                                    <span class="text-red-500 text-xs mt-1">{{ $message }}</span>
                                @enderror
                            </div>
                            <div>
                                <label class="block text-xs font-semibold text-slate-600 uppercase mb-1">Versi</label>
                                <input type="text" wire:model="systemForm.app_version"
                                    class="w-full px-4 py-2 border border-slate-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 outline-none transition-all"
                                    placeholder="1.0.0">
                                @error('systemForm.app_version')
                                    <span class="text-red-500 text-xs mt-1">{{ $message }}</span>
                                @enderror
                            </div>
                        </div>

                        <div>
                            <label class="block text-xs font-semibold text-slate-600 uppercase mb-1">Deskripsi
                                Aplikasi</label>
                            <textarea wire:model="systemForm.app_description" rows="2"
                                class="w-full px-4 py-2 border border-slate-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 outline-none transition-all"
                                placeholder="Deskripsi singkat aplikasi..."></textarea>
                            @error('systemForm.app_description')
                                <span class="text-red-500 text-xs mt-1">{{ $message }}</span>
                            @enderror
                        </div>

                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                            <div>
                                <label class="block text-xs font-semibold text-slate-600 uppercase mb-1">URL
                                    Aplikasi</label>
                                <input type="url" wire:model="systemForm.app_url"
                                    class="w-full px-4 py-2 border border-slate-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 outline-none transition-all"
                                    placeholder="https://kampus.com">
                                @error('systemForm.app_url')
                                    <span class="text-red-500 text-xs mt-1">{{ $message }}</span>
                                @enderror
                            </div>
                            <div>
                                <label class="block text-xs font-semibold text-slate-600 uppercase mb-1">Email
                                    System</label>
                                <input type="email" wire:model="systemForm.app_email"
                                    class="w-full px-4 py-2 border border-slate-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 outline-none transition-all"
                                    placeholder="admin@kampus.com">
                                @error('systemForm.app_email')
                                    <span class="text-red-500 text-xs mt-1">{{ $message }}</span>
                                @enderror
                            </div>
                        </div>

                        <!-- Logos -->
                        <div class="border-t border-slate-100 pt-4 mt-4">
                            <h3 class="text-sm font-bold text-slate-700 mb-3">Aset Logo</h3>
                            <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                                <!-- Favicon -->
                                <div>
                                    <label class="block text-xs text-slate-500 mb-1">Favicon</label>
                                    <input type="file" wire:model="appFavicon"
                                        class="block w-full text-xs text-slate-500 file:mr-4 file:py-2 file:px-4 file:rounded-full file:border-0 file:text-xs file:font-semibold file:bg-blue-50 file:text-blue-700 hover:file:bg-blue-100" />
                                    <div wire:loading wire:target="appFavicon" class="text-muted small mt-1">
                                        <i class="fas fa-spinner fa-spin me-1"></i> Mengupload...
                                    </div>
                                    <div id="preview-favicon" class="preview-box">
                                        @if ($appFavicon)
                                            <img src="{{ $appFavicon->temporaryUrl() }}">
                                        @else
                                            <div class="preview-placeholder">
                                                <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6" fill="none"
                                                    viewBox="0 0 24 24" stroke="currentColor">
                                                    <path stroke-linecap="round" stroke-linejoin="round"
                                                        stroke-width="2"
                                                        d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z" />
                                                </svg>
                                                <span>Preview</span>
                                            </div>
                                        @endif
                                    </div>
                                </div>
                                <!-- Logo Vertikal -->
                                <div>
                                    <label class="block text-xs text-slate-500 mb-1">Logo Vertikal</label>
                                    <input type="file" wire:model="appLogoVertikal"
                                        onchange="previewImage(this, 'preview-logo-vertikal')"
                                        class="block w-full text-xs text-slate-500 file:mr-4 file:py-2 file:px-4 file:rounded-full file:border-0 file:text-xs file:font-semibold file:bg-blue-50 file:text-blue-700 hover:file:bg-blue-100" />
                                    <div id="preview-logo-vertikal" class="preview-box">
                                        @if ($appLogoVertikal)
                                            <img src="{{ $appLogoVertikal->temporaryUrl() }}"
                                                alt="Logo Vertikal Preview">
                                        @else
                                            <div class="preview-placeholder">
                                                <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6"
                                                    fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                                    <path stroke-linecap="round" stroke-linejoin="round"
                                                        stroke-width="2"
                                                        d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z" />
                                                </svg>
                                                <span>Preview</span>
                                            </div>
                                        @endif

                                    </div>
                                </div>
                                <!-- Logo Horizontal -->
                                <div>
                                    <label class="block text-xs text-slate-500 mb-1">Logo Horizontal</label>
                                    <input type="file" wire:model="appLogoHorizontal"
                                        onchange="previewImage(this, 'preview-logo-horizontal')"
                                        class="block w-full text-xs text-slate-500 file:mr-4 file:py-2 file:px-4 file:rounded-full file:border-0 file:text-xs file:font-semibold file:bg-blue-50 file:text-blue-700 hover:file:bg-blue-100" />
                                    <div id="preview-logo-horizontal" class="preview-box">
                                        @if ($appLogoHorizontal)
                                            <img src="{{ $appLogoHorizontal->temporaryUrl() }}"
                                                alt="Logo Horizontal Preview">
                                        @else
                                            <div class="preview-placeholder">
                                                <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6"
                                                    fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                                    <path stroke-linecap="round" stroke-linejoin="round"
                                                        stroke-width="2"
                                                        d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z" />
                                                </svg>
                                                <span>Preview</span>
                                            </div>
                                        @endif
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- System Toggles -->
                        <div class="border-t border-slate-100 pt-4 mt-4 bg-slate-50 p-4 rounded-lg">
                            <h3 class="text-sm font-bold text-slate-700 mb-3">Keamanan & System</h3>
                            <div class="flex flex-col gap-3">
                                <label class="flex items-center gap-3 cursor-pointer">
                                    <input type="checkbox" wire:model="systemForm.maintenance_mode"
                                        class="w-4 h-4 text-blue-600 rounded focus:ring-blue-500 border-gray-300">
                                    <span class="text-sm text-slate-700">Aktifkan Maintenance Mode</span>
                                </label>
                                <label class="flex items-center gap-3 cursor-pointer">
                                    <input type="checkbox" wire:model="systemForm.enable_captcha"
                                        class="w-4 h-4 text-blue-600 rounded focus:ring-blue-500 border-gray-300">
                                    <span class="text-sm text-slate-700">Aktifkan Captcha Login</span>
                                </label>
                                <div class="grid grid-cols-2 gap-4 mt-2">
                                    <div>
                                        <label class="block text-xs text-slate-500 mb-1">Max Login Attempts</label>
                                        <input type="number" wire:model="systemForm.max_login_attempts"
                                            class="w-full px-3 py-1.5 border border-slate-300 rounded text-sm">
                                        @error('systemForm.max_login_attempts')
                                            <span class="text-red-500 text-xs mt-1">{{ $message }}</span>
                                        @enderror
                                    </div>
                                    <div>
                                        <label class="block text-xs text-slate-500 mb-1">Timeout (detik)</label>
                                        <input type="number" wire:model="systemForm.login_decay_seconds"
                                            class="w-full px-3 py-1.5 border border-slate-300 rounded text-sm">
                                        @error('systemForm.login_decay_seconds')
                                            <span class="text-red-500 text-xs mt-1">{{ $message }}</span>
                                        @enderror
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            @endif

            <!-- STEP 2: Campus Settings -->
            @if ($step === 2)
                <div id="step-2" class="step-content {{ $step === 2 ? 'active' : '' }}">
                    <h2 class="text-2xl font-bold text-slate-800 mb-2">Pengaturan Kampus</h2>
                    <p class="text-slate-500 mb-6 text-sm">Identitas dan kontak institusi pendidikan.</p>

                    <div class="space-y-4">
                        <div>
                            <label class="block text-xs font-semibold text-slate-600 uppercase mb-1">Nama
                                Kampus</label>
                            <input type="text" wire:model="campusForm.name"
                                class="w-full px-4 py-2 border border-slate-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 outline-none transition-all">
                            @error('campusForm.name')
                                <span class="text-red-500 text-xs mt-1">{{ $message }}</span>
                            @enderror
                        </div>


                        <div>
                            <label class="block text-xs font-semibold text-slate-600 uppercase mb-1">Domain</label>
                            <input type="text" wire:model="campusForm.domain"
                                class="w-full px-4 py-2 border border-slate-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 outline-none transition-all"
                                placeholder="kampus.ac.id">
                            @error('campusForm.domain')
                                <span class="text-red-500 text-xs mt-1">{{ $message }}</span>
                            @enderror
                        </div>

                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                            <div>
                                <label class="block text-xs text-slate-500 mb-1">Telepon</label>
                                <input type="text" wire:model="campusForm.phone"
                                    class="w-full px-4 py-2 border border-slate-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 outline-none transition-all">
                                @error('campusForm.phone')
                                    <span class="text-red-500 text-xs mt-1">{{ $message }}</span>
                                @enderror
                            </div>
                            <div>
                                <label class="block text-xs text-slate-500 mb-1">WhatsApp</label>
                                <input type="text" wire:model="campusForm.whatsapp"
                                    class="w-full px-4 py-2 border border-slate-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 outline-none transition-all">
                                @error('campusForm.whatsapp')
                                    <span class="text-red-500 text-xs mt-1">{{ $message }}</span>
                                @enderror
                            </div>
                        </div>

                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                            <div>
                                <label class="block text-xs text-slate-500 mb-1">Email Info</label>
                                <input type="email" wire:model="campusForm.email_info"
                                    class="w-full px-4 py-2 border border-slate-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 outline-none transition-all">
                                @error('campusForm.email_info')
                                    <span class="text-red-500 text-xs mt-1">{{ $message }}</span>
                                @enderror
                            </div>
                            <div>
                                <label class="block text-xs text-slate-500 mb-1">Email Humas</label>
                                <input type="email" wire:model="campusForm.email_humas"
                                    class="w-full px-4 py-2 border border-slate-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 outline-none transition-all">
                                @error('campusForm.email_humas')
                                    <span class="text-red-500 text-xs mt-1">{{ $message }}</span>
                                @enderror
                            </div>
                        </div>

                        <div class="border-t border-slate-100 pt-4 mt-4">
                            <h3 class="text-sm font-bold text-slate-700 mb-3">Alamat & Lokasi</h3>
                            <textarea wire:model="campusForm.address" rows="2"
                                class="w-full px-4 py-2 border border-slate-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 outline-none transition-all mb-3"
                                placeholder="Alamat lengkap..."></textarea>
                            <div class="grid grid-cols-3 gap-4">
                                <input type="text" wire:model="campusForm.city" placeholder="Kota"
                                    class="w-full px-4 py-2 border border-slate-300 rounded-lg text-sm">
                                @error('campusForm.city')
                                    <span class="text-red-500 text-xs mt-1">{{ $message }}</span>
                                @enderror
                                <input type="text" wire:model="campusForm.province" placeholder="Provinsi"
                                    class="w-full px-4 py-2 border border-slate-300 rounded-lg text-sm">
                                @error('campusForm.province')
                                    <span class="text-red-500 text-xs mt-1">{{ $message }}</span>
                                @enderror
                                <input type="text" wire:model="campusForm.postal_code" placeholder="Kode Pos"
                                    class="w-full px-4 py-2 border border-slate-300 rounded-lg text-sm">
                                @error('campusForm.postal_code')
                                    <span class="text-red-500 text-xs mt-1">{{ $message }}</span>
                                @enderror
                            </div>
                        </div>

                        <div class="border-t border-slate-100 pt-4 mt-4">
                            <h3 class="text-sm font-bold text-slate-700 mb-3">Sosial Media</h3>
                            <div class="grid grid-cols-2 md:grid-cols-5 gap-2">
                                <input type="text" wire:model="campusForm.tiktok" placeholder="TikTok"
                                    class="w-full px-3 py-2 border border-slate-300 rounded-lg text-sm">
                                @error('campusForm.tiktok')
                                    <span class="text-red-500 text-xs mt-1">{{ $message }}</span>
                                @enderror
                                <input type="text" wire:model="campusForm.instagram" placeholder="Instagram"
                                    class="w-full px-3 py-2 border border-slate-300 rounded-lg text-sm">
                                @error('campusForm.instagram')
                                    <span class="text-red-500 text-xs mt-1">{{ $message }}</span>
                                @enderror
                                <input type="text" wire:model="campusForm.facebook" placeholder="Facebook"
                                    class="w-full px-3 py-2 border border-slate-300 rounded-lg text-sm">
                                @error('campusForm.facebook')
                                    <span class="text-red-500 text-xs mt-1">{{ $message }}</span>
                                @enderror
                                <input type="text" wire:model="campusForm.xtwitter" placeholder="X / Twitter"
                                    class="w-full px-3 py-2 border border-slate-300 rounded-lg text-sm">
                                @error('campusForm.xtwitter')
                                    <span class="text-red-500 text-xs mt-1">{{ $message }}</span>
                                @enderror
                                <input type="text" wire:model="campusForm.linkedin" placeholder="LinkedIn"
                                    class="w-full px-3 py-2 border border-slate-300 rounded-lg text-sm">
                                @error('campusForm.linkedin')
                                    <span class="text-red-500 text-xs mt-1">{{ $message }}</span>
                                @enderror
                            </div>
                        </div>
                    </div>
                </div>
            @endif

            <!-- STEP 3: Buat Akun -->
            @if ($step === 3)
                <div id="step-3" class="step-content {{ $step === 3 ? 'active' : '' }}">
                    <h2 class="text-2xl font-bold text-slate-800 mb-2">Super Admin</h2>
                    <p class="text-slate-500 mb-6 text-sm">Buat akun administrator utama untuk mengakses systemForm.</p>

                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                        <div class="space-y-4">
                            <h3 class="text-sm font-bold text-slate-700 border-b pb-2">Identitas Akun</h3>
                            <div>
                                <label class="block text-xs text-slate-500 mb-1">Nama Depan</label>
                                <input type="text" wire:model="adminForm.first_name"
                                    class="w-full px-4 py-2 border border-slate-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 outline-none transition-all">
                                @error('adminForm.first_name')
                                    <span class="text-red-500 text-xs mt-1">{{ $message }}</span>
                                @enderror
                            </div>
                            <div>
                                <label class="block text-xs text-slate-500 mb-1">Nama Belakang</label>
                                <input type="text" wire:model="adminForm.last_name"
                                    class="w-full px-4 py-2 border border-slate-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 outline-none transition-all">
                                @error('adminForm.last_name')
                                    <span class="text-red-500 text-xs mt-1">{{ $message }}</span>
                                @enderror
                            </div>
                            <div>
                                <label class="block text-xs text-slate-500 mb-1">Username</label>
                                <input type="text" wire:model="adminForm.username"
                                    class="w-full px-4 py-2 border border-slate-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 outline-none transition-all">
                                @error('adminForm.username')
                                    <span class="text-red-500 text-xs mt-1">{{ $message }}</span>
                                @enderror
                            </div>
                            <div>
                                <label class="block text-xs text-slate-500 mb-1">Email</label>
                                <input type="email" wire:model="adminForm.email"
                                    class="w-full px-4 py-2 border border-slate-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 outline-none transition-all">
                                @error('adminForm.email')
                                    <span class="text-red-500 text-xs mt-1">{{ $message }}</span>
                                @enderror
                            </div>
                            <div>
                                <label class="block text-xs text-slate-500 mb-1">Password</label>
                                <input type="password" wire:model="adminForm.password"
                                    class="w-full px-4 py-2 border border-slate-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 outline-none transition-all">
                                @error('adminForm.password')
                                    <span class="text-red-500 text-xs mt-1">{{ $message }}</span>
                                @enderror
                            </div>
                        </div>

                        <div class="space-y-4">
                            <h3 class="text-sm font-bold text-slate-700 border-b pb-2">Data Pribadi</h3>
                            <div>
                                <label class="block text-xs text-slate-500 mb-1">Jenis Kelamin</label>
                                <select wire:model="adminForm.gender"
                                    class="w-full px-4 py-2 border border-slate-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 outline-none transition-all bg-white">
                                    <option value="">Pilih...</option>
                                    <option value="Laki-laki">Laki-laki</option>
                                    <option value="Perempuan">Perempuan</option>
                                </select>
                                @error('adminForm.gender')
                                    <span class="text-red-500 text-xs mt-1">{{ $message }}</span>
                                @enderror
                            </div>
                            <div>
                                <label class="block text-xs text-slate-500 mb-1">religion</label>
                                <select wire:model="adminForm.religion"
                                    class="w-full px-4 py-2 border border-slate-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 outline-none transition-all bg-white">
                                    <option value="">Pilih...</option>
                                    <option value="Islam">Islam</option>
                                    <option value="Kristen">Kristen</option>
                                    <option value="Katolik">Katolik</option>
                                    <option value="Hindu">Hindu</option>
                                    <option value="Buddha">Buddha</option>
                                    <option value="Khonghucu">Khonghucu</option>
                                </select>
                                @error('adminForm.religion')
                                    <span class="text-red-500 text-xs mt-1">{{ $message }}</span>
                                @enderror
                            </div>
                            <div class="grid grid-cols-2 gap-2">
                                <div>
                                    <label class="block text-xs text-slate-500 mb-1">Tempat Lahir</label>
                                    <input type="text" wire:model="adminForm.place_of_birth"
                                        class="w-full px-4 py-2 border border-slate-300 rounded-lg text-sm">
                                    @error('adminForm.place_of_birth')
                                        <span class="text-red-500 text-xs mt-1">{{ $message }}</span>
                                    @enderror
                                </div>
                                <div>
                                    <label class="block text-xs text-slate-500 mb-1">Tanggal Lahir</label>
                                    <input type="date" wire:model="adminForm.date_of_birth"
                                        class="w-full px-4 py-2 border border-slate-300 rounded-lg text-sm">
                                    @error('adminForm.date_of_birth')
                                        <span class="text-red-500 text-xs mt-1">{{ $message }}</span>
                                    @enderror
                                </div>
                            </div>
                            <div>
                                <label class="block text-xs text-slate-500 mb-1">No. HP / WA</label>
                                <input type="text" wire:model="adminForm.phone"
                                    class="w-full px-4 py-2 border border-slate-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 outline-none transition-all">
                                @error('adminForm.phone')
                                    <span class="text-red-500 text-xs mt-1">{{ $message }}</span>
                                @enderror
                            </div>

                            <div>
                                <label class="block text-xs text-slate-500 mb-1">Password</label>
                                <input type="password" wire:model="adminForm.password_confirmation"
                                    class="w-full px-4 py-2 border border-slate-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 outline-none transition-all">
                                @error('adminForm.password_confirmation')
                                    <span class="text-red-500 text-xs mt-1">{{ $message }}</span>
                                @enderror
                            </div>
                        </div>
                    </div>
                </div>
            @endif

            <!-- STEP 4: Summary -->
            @if ($step === 4)
                <div id="step-4" class="step-content {{ $step === 4 ? 'active' : '' }}">
                    <h2 class="text-2xl font-bold text-slate-800 mb-2">Ringkasan & Validasi</h2>
                    <p class="text-slate-500 mb-6 text-sm">Pastikan data sudah benar sebelum melakukan instalasi.</p>
                    <div class="bg-slate-50 border border-slate-200 rounded-xl p-6 h-[400px] overflow-y-auto custom-scroll mb-6">
                        <!-- System Data -->
                        <div class="mb-6">
                            <h3
                                class="text-blue-600 font-bold text-sm uppercase tracking-wider mb-2 border-b border-blue-200 pb-1">
                                1. Pengaturan System</h3>
                            <div class="grid grid-cols-2 gap-x-4 gap-y-2 text-sm">
                                <div class="text-slate-500">Nama Aplikasi:</div>
                                <div class="font-medium text-slate-800" id="sum-app-name">
                                    {{ $systemForm['app_name'] ?? '-' }}</div>
                                <div class="text-slate-500">Versi:</div>
                                <div class="font-medium text-slate-800" id="sum-app-ver">
                                    {{ $systemForm['app_version'] ?? '-' }}</div>
                                <div class="text-slate-500">URL:</div>
                                <div class="font-medium text-slate-800" id="sum-app-url">
                                    {{ $systemForm['app_url'] ?? '-' }}</div>
                                <div class="text-slate-500">Email:</div>
                                <div class="font-medium text-slate-800" id="sum-app-email">
                                    {{ $systemForm['app_email'] ?? '-' }}</div>
                                <div class="text-slate-500">Maintenance:</div>
                                <div class="font-medium text-slate-800" id="sum-maint">
                                    {{ $systemForm['maintenance_mode'] ? 'Ya' : 'Tidak' }}</div>
                            </div>
                        </div>

                        <!-- Campus Data -->
                        <div class="mb-6">
                            <h3
                                class="text-blue-600 font-bold text-sm uppercase tracking-wider mb-2 border-b border-blue-200 pb-1">
                                2. Pengaturan Kampus</h3>
                            <div class="grid grid-cols-2 gap-x-4 gap-y-2 text-sm">
                                <div class="text-slate-500">Nama Kampus:</div>
                                <div class="font-medium text-slate-800" id="sum-campus-name">
                                    {{ $campusForm['name'] ?? '-' }}</div>
                                <div class="text-slate-500">Domain:</div>
                                <div class="font-medium text-slate-800" id="sum-domain">
                                    {{ $campusForm['domain'] ?? '-' }}</div>
                                <div class="text-slate-500">Telepon:</div>
                                <div class="font-medium text-slate-800" id="sum-phone">{{ $campusForm['phone'] ?? '-' }}
                                </div>
                                <div class="text-slate-500">Email Info:</div>
                                <div class="font-medium text-slate-800" id="sum-email-info">
                                    {{ $campusForm['email_info'] ?? '-' }}</div>
                                <div class="text-slate-500">Alamat:</div>
                                <div class="font-medium text-slate-800 col-span-2" id="sum-address">
                                    {{ $campusForm['address'] ?? '-' }}</div>
                            </div>
                        </div>

                        <!-- Admin Data -->
                        <div>
                            <h3
                                class="text-blue-600 font-bold text-sm uppercase tracking-wider mb-2 border-b border-blue-200 pb-1">
                                3. Akun Super Admin</h3>
                            <div class="grid grid-cols-2 gap-x-4 gap-y-2 text-sm">
                                <div class="text-slate-500">Nama Lengkap:</div>
                                <div class="font-medium text-slate-800" id="sum-admin-name">
                                    {{ $adminForm['first_name'] . ' ' . $adminForm['last_name'] ?? '-' }}</div>
                                <div class="text-slate-500">Username:</div>
                                <div class="font-medium text-slate-800" id="sum-admin-user">
                                    {{ $adminForm['username'] ?? '-' }}</div>
                                <div class="text-slate-500">Email:</div>
                                <div class="font-medium text-slate-800" id="sum-admin-email">
                                    {{ $adminForm['email'] ?? '-' }}</div>
                                <div class="text-slate-500">No. HP:</div>
                                <div class="font-medium text-slate-800" id="sum-admin-phone">
                                    {{ $adminForm['phone'] ?? '-' }}</div>
                            </div>
                        </div>
                    </div>

                    <div class="space-y-3">
                        <label
                            class="flex items-start gap-3 p-3 border border-slate-200 rounded-lg hover:bg-slate-50 cursor-pointer transition-colors">
                            <input type="checkbox" id="check-valid"
                                class="mt-1 w-4 h-4 text-blue-600 rounded focus:ring-blue-500 border-gray-300">
                            <span class="text-sm text-slate-700">Saya telah memastikan bahwa semua data yang dimasukkan
                                di atas adalah valid dan benar.</span>
                        </label>
                        <label
                            class="flex items-start gap-3 p-3 border border-slate-200 rounded-lg hover:bg-slate-50 cursor-pointer transition-colors">
                            <input type="checkbox" id="check-tos"
                                class="mt-1 w-4 h-4 text-blue-600 rounded focus:ring-blue-500 border-gray-300">
                            <span class="text-sm text-slate-700">Saya menyetujui Syarat & Ketentuan penggunaan software
                                ini dan lisensi yang berlaku.</span>
                        </label>
                    </div>
                </div>
            @endif

            <!-- Navigation Buttons -->
            <div class="flex justify-between mt-8 pt-6 border-t border-slate-100">
                <button type="button" wire:click="prev"
                    class="px-6 py-2.5 rounded-lg border border-slate-300 text-slate-600 font-medium hover:bg-slate-50 hover:text-slate-800 transition-all disabled:opacity-50 disabled:cursor-not-allowed">
                    Kembali
                </button>

                @if ($step < 4)
                    <button type="button" id="btn-next" wire:click="next"
                        class="px-6 py-2.5 rounded-lg bg-blue-600 text-white font-medium hover:bg-blue-700 shadow-lg shadow-blue-500/30 transition-all flex items-center gap-2">
                        <span>Lanjut</span>
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24"
                            stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                d="M14 5l7 7m0 0l-7 7m7-7H3" />
                        </svg>
                    </button>
                @endif

                @if ($step === 4)
                    <button type="submit" wire:click="finish"
                        class=" px-6 py-2.5 rounded-lg bg-green-600 text-white font-medium hover:bg-green-700 shadow-lg shadow-green-500/30 transition-all flex items-center gap-2">
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24"
                            stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                d="M5 13l4 4L19 7" />
                        </svg>
                        <span>Instalasi System</span>
                    </button>
                @endif

            </div>
        </form>
    </div>


</div>
