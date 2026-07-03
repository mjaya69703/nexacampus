<?php

use Livewire\Component;
use Livewire\WithFileUploads;
use App\Models\Settings\Campus;
use App\Models\Settings\NotificationSetting;
use App\Models\Settings\System;
use App\Models\User;
use App\Support\Notifications\NotificationDispatchService;
use App\Support\Notifications\WhatsAppProviderManager;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Cache;
use Kstmostofa\LaravelWhatsApp\Exceptions\SidecarException;
use Kstmostofa\LaravelWhatsApp\Web\SidecarManager;
use Kstmostofa\LaravelWhatsApp\Web\WebClient;

new class extends Component
{
    use WithFileUploads;

    public $campusForm = [];
    public $systemForm = [];
    public $appFavicon = null;
    public $appLogoVertikal = null;
    public $appLogoHorizontal = null;
    public $tab = 'aplikasi';
    public $campus;
    public $system;
    public $notificationSetting;
    public $notificationForm = [];
    public $notificationHealth = [];
    public ?string $testWhatsappRecipient = null;
    public ?string $sidecarStatusMessage = null;
    public bool $sidecarReachable = false;
    public array $storedSidecarSessions = [];
    
    public function mount()
    {

        $this->campus = Campus::first() ?? new Campus();
        $this->system = System::first() ?? new System();
        $this->notificationSetting = NotificationSetting::current();

        $this->campusForm = $this->campus->toArray();
        $this->systemForm = $this->system->toArray();
        $this->notificationForm = $this->notificationSettingToForm($this->notificationSetting);
        $this->notificationHealth = app(WhatsAppProviderManager::class)->health($this->notificationSetting);
        $this->refreshSidecarStatus();
    }

    public function update()
    {
        $validatedData = $this->validate([
            // Validasi untuk data sistem
            'systemForm.app_name' => 'required|string|max:255',
            'systemForm.app_version' => 'nullable|string|max:50',
            'systemForm.app_description' => 'nullable|string|max:1000',
            'systemForm.app_url' => 'nullable|url|max:255',
            'systemForm.app_email' => 'nullable|email|max:255',
            'systemForm.maintenance_mode' => 'boolean',
            'systemForm.enable_captcha' => 'boolean',
            'systemForm.max_login_attempts' => 'nullable|integer|min:1|max:10',
            'systemForm.login_decay_seconds' => 'nullable|integer|min:30|max:3600',
            // Validasi untuk file upload
            'appFavicon' => 'nullable|image|mimes:png,ico|max:2048',
            'appLogoVertikal' => 'nullable|image|mimes:png,jpg,jpeg|max:4096',
            'appLogoHorizontal' => 'nullable|image|mimes:png,jpg,jpeg|max:4096',
            // Validasi untuk data kampus
            'campusForm.name' => 'required|string|max:255',
            'campusForm.domain' => 'nullable|string|max:255',
            'campusForm.phone' => 'nullable|string|max:20',
            'campusForm.faximile' => 'nullable|string|max:20',
            'campusForm.whatsapp' => 'nullable|string|max:20',
            'campusForm.email_info' => 'nullable|email|max:255',
            'campusForm.email_humas' => 'nullable|email|max:255',
            'campusForm.address' => 'nullable|string|max:1000',
            'campusForm.city' => 'nullable|string|max:255',
            'campusForm.province' => 'nullable|string|max:255',
            'campusForm.postal_code' => 'nullable|string|max:20',
            'campusForm.latitude' => 'nullable|numeric|between:-90,90',
            'campusForm.longitude' => 'nullable|numeric|between:-180,180',
            'campusForm.instagram' => 'nullable|string|max:255',
            'campusForm.facebook' => 'nullable|string|max:255',
            'campusForm.linkedin' => 'nullable|string|max:255',
            'campusForm.xtwitter' => 'nullable|string|max:255',
            'campusForm.tiktok' => 'nullable|string|max:255',
            // Validasi untuk notifikasi WhatsApp
            'notificationForm.whatsapp_enabled' => 'boolean',
            'notificationForm.whatsapp_provider' => 'required|in:official_cloud_api,unofficial_web_session',
            'notificationForm.fallback_channel' => 'required|in:in_app,email,none',
            'notificationForm.retry_attempts' => 'required|integer|min:0|max:5',
            'notificationForm.timeout_seconds' => 'required|integer|min:5|max:120',
            'notificationForm.official_config.access_token' => 'nullable|string|max:2000',
            'notificationForm.official_config.phone_number_id' => 'nullable|string|max:255',
            'notificationForm.official_config.business_account_id' => 'nullable|string|max:255',
            'notificationForm.official_config.app_secret' => 'nullable|string|max:2000',
            'notificationForm.official_config.verify_token' => 'nullable|string|max:255',
            'notificationForm.unofficial_config.sidecar_url' => 'nullable|url|starts_with:http://,https://|max:255',
            'notificationForm.unofficial_config.session_name' => 'nullable|string|max:64|regex:/^[A-Za-z0-9_\-]+$/',
            'notificationForm.unofficial_config.shared_token' => 'nullable|string|max:2000',
        ]);

        DB::transaction(function () use ($validatedData) {

            $system = $this->system;
            $system->app_name = $validatedData['systemForm']['app_name'];
            $system->app_version = $validatedData['systemForm']['app_version'] ?? null;
            $system->app_description = $validatedData['systemForm']['app_description'] ?? null;
            $system->app_url = $validatedData['systemForm']['app_url'] ?? null;
            $system->app_email = $validatedData['systemForm']['app_email'] ?? null;
            $system->maintenance_mode = $validatedData['systemForm']['maintenance_mode'] ?? false;
            $system->enable_captcha = $validatedData['systemForm']['enable_captcha'] ?? false;
            $system->max_login_attempts = $validatedData['systemForm']['max_login_attempts'] ?? 5;
            $system->login_decay_seconds = $validatedData['systemForm']['login_decay_seconds'] ?? 300;  

            // Handle upload logo dan favicon
            foreach ([
                'appFavicon'        => 'app_favicon',
                'appLogoVertikal'   => 'app_logo_vertikal',
                'appLogoHorizontal' => 'app_logo_horizontal',
            ] as $property => $column) {
                if ($this->$property) {
                    if ($system->$column) {
                        Storage::disk('public')->delete('images/logo/' . $system->$column);
                    }
                    $filename = $this->$property->hashName();
                    $this->$property->store('images/logo', 'public');
                    $system->$column = $filename;
                }
            }

            $system->save();
            $campus = $this->campus;
            $campus->fill($validatedData['campusForm']);
            $campus->save();

            $notification = $this->notificationSetting;
            $notification->fill([
                'whatsapp_enabled' => $validatedData['notificationForm']['whatsapp_enabled'] ?? false,
                'whatsapp_provider' => $validatedData['notificationForm']['whatsapp_provider'],
                'fallback_channel' => $validatedData['notificationForm']['fallback_channel'],
                'retry_attempts' => $validatedData['notificationForm']['retry_attempts'],
                'timeout_seconds' => $validatedData['notificationForm']['timeout_seconds'],
                'official_config' => $validatedData['notificationForm']['official_config'] ?? [],
                'unofficial_config' => $validatedData['notificationForm']['unofficial_config'] ?? [],
            ]);
            $notification->save();
            $this->notificationSetting = $notification->fresh();
            $this->notificationHealth = app(WhatsAppProviderManager::class)->health($this->notificationSetting);

            $this->reset([
                'appFavicon',
                'appLogoVertikal',
                'appLogoHorizontal'
            ]);
        });
        Cache::forget('global_campus');
        Cache::forget('global_system');

        session()->flash('success', 'Pengaturan kampus berhasil disimpan.');
    }

    public function checkWhatsappConfiguration()
    {
        $this->notificationHealth = app(WhatsAppProviderManager::class)->persistHealth($this->notificationSetting->fresh());

        session()->flash(
            $this->notificationHealth['status'] === 'ready' ? 'success' : 'warning',
            $this->notificationHealth['message']
        );
    }

    public function sendTestWhatsapp()
    {
        $validated = $this->validate([
            'testWhatsappRecipient' => 'required|string|max:30',
        ]);

        $recipient = new User([
            'first_name' => auth()->user()?->first_name ?: 'Admin',
            'last_name' => auth()->user()?->last_name ?: '',
            'email' => auth()->user()?->email ?: 'admin@example.test',
            'phone' => $validated['testWhatsappRecipient'],
        ]);

        $log = app(NotificationDispatchService::class)->whatsapp($recipient, 'system.whatsapp_test', [
            'recipient_name' => $recipient->name,
            'provider' => str_replace('_', ' ', $this->notificationSetting->fresh()->whatsapp_provider),
        ]);

        session()->flash(
            $log->status === 'sent' ? 'success' : 'warning',
            'Test WhatsApp status: '.$log->status.($log->error_message ? ' - '.$log->error_message : '')
        );
    }

    public function startBundledWhatsappSidecar()
    {
        $manager = app(SidecarManager::class);
        $message = 'WhatsApp sidecar siap digunakan.';
        $status = 'success';

        try {
            if (! app(WebClient::class)->ping()) {
                $pid = $manager->start();
                $message = 'WhatsApp sidecar sedang dinyalakan (pid '.$pid.').';
            }

            if (method_exists($manager, 'restorePersistedSessions')) {
                $restored = $manager->restorePersistedSessions();

                if ($restored !== []) {
                    $message .= ' Session tersimpan dipulihkan: '.implode(', ', $restored).'.';
                }
            }

            $this->waitForSidecar();
        } catch (SidecarException $exception) {
            $status = 'warning';
            $message = $exception->getMessage();
        } catch (\Throwable $exception) {
            $status = 'warning';
            $message = 'WhatsApp sidecar belum bisa dinyalakan: '.$exception->getMessage();
        }

        $this->refreshSidecarStatus();

        session()->flash($status, $message);
    }

    public function stopBundledWhatsappSidecar()
    {
        $manager = app(SidecarManager::class);
        $status = 'success';
        $message = 'WhatsApp sidecar sudah dihentikan.';

        try {
            if (! $manager->stop()) {
                $status = 'warning';
                $message = 'Tidak ada proses sidecar yang tercatat aktif.';
            }

            $this->waitForSidecarStop();
        } catch (\Throwable $exception) {
            $status = 'warning';
            $message = 'WhatsApp sidecar belum bisa dihentikan: '.$exception->getMessage();
        }

        $this->refreshSidecarStatus();

        session()->flash($status, $message);
    }

    public function refreshSidecarStatus(bool $notify = false)
    {
        $manager = app(SidecarManager::class);
        $this->storedSidecarSessions = method_exists($manager, 'persistedSessionIds')
            ? $manager->persistedSessionIds()
            : [];

        try {
            $this->sidecarReachable = app(WebClient::class)->ping();
            $this->sidecarStatusMessage = $this->sidecarReachable
                ? 'Sidecar bawaan aktif di http://'.config('laravel-whatsapp.web.host').':'.config('laravel-whatsapp.web.port')
                : 'Sidecar bawaan belum aktif.';
        } catch (\Throwable $exception) {
            $this->sidecarReachable = false;
            $this->sidecarStatusMessage = 'Sidecar bawaan belum aktif.';
        }

        if ($notify) {
            session()->flash(
                $this->sidecarReachable ? 'success' : 'warning',
                'Status sidecar diperbarui: '.$this->sidecarStatusMessage
            );
        }
    }

    public function useStoredWhatsappSession(string $sessionId)
    {
        if (! preg_match('/^[A-Za-z0-9_\-]{1,64}$/', $sessionId)) {
            session()->flash('warning', 'Session WhatsApp tidak valid.');

            return;
        }

        $this->notificationForm['unofficial_config']['session_name'] = $sessionId;

        session()->flash('success', 'Session Name diganti ke '.$sessionId.'. Simpan pengaturan agar dipakai untuk pengiriman.');
    }

    private function waitForSidecar(): void
    {
        for ($i = 0; $i < 30; $i++) {
            usleep(250_000);

            try {
                if (app(WebClient::class)->ping()) {
                    return;
                }
            } catch (\Throwable) {
                //
            }
        }
    }

    private function waitForSidecarStop(): void
    {
        for ($i = 0; $i < 20; $i++) {
            usleep(150_000);

            try {
                if (! app(WebClient::class)->ping()) {
                    return;
                }
            } catch (\Throwable) {
                return;
            }
        }
    }

    private function notificationSettingToForm(NotificationSetting $setting): array
    {
        return [
            'whatsapp_enabled' => $setting->whatsapp_enabled,
            'whatsapp_provider' => $setting->whatsapp_provider ?: NotificationSetting::PROVIDER_OFFICIAL,
            'fallback_channel' => $setting->fallback_channel ?: 'in_app',
            'retry_attempts' => $setting->retry_attempts ?: 3,
            'timeout_seconds' => $setting->timeout_seconds ?: 15,
            'official_config' => array_merge([
                'access_token' => '',
                'phone_number_id' => '',
                'business_account_id' => '',
                'app_secret' => '',
                'verify_token' => '',
            ], $setting->official_config ?? []),
            'unofficial_config' => array_merge([
                'sidecar_url' => '',
                'session_name' => 'main',
                'shared_token' => '',
            ], $setting->unofficial_config ?? []),
        ];
    }

    public function render()
    {
        // Data untuk dikirim ke view dan layout
        $data = [
            'menus' => 'System Management',      // Data menu
            'pages' => 'Pengaturan Sistem',   // Data halaman
            // 'user' => null,              // Data user
            // 'activeRole' => 'admin',     // Data role aktif
        ];

        // Kirim data ke view dan layout secara langsung
        return $this->view($data)
            ->layout('layouts.app', $data); // Teruskan semua data ke layout
    }
};
?>

@push('styles')
<style>
    .settings-header {
        background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        padding: 2rem;
        border-radius: 10px;
        color: white;
        margin-bottom: 2rem;
    }
    .settings-logo {
        width: 150px;
        height: 150px;
        border-radius: 10px;
        border: 5px solid white;
        object-fit: contain;
        background-color: white;
    }
    .nav-tabs .nav-link.active {
        background-color: #667eea;
        color: white !important;
        border-color: #667eea;
    }
    .nav-tabs .nav-link.active i {
        color: white !important;
    }
</style>
@endpush

<div class="row">
    <div class="col-12">

        <x-alert />

        <div class="card">
            <div class="card-body">
                <!-- Settings Header -->
                <div class="settings-header">
                    <div class="row align-items-center">
                        <div class="col-md-2 text-center">
                            <img src="{{ $system->app_logo_vertikal }}" alt="Logo Vertikal" class="settings-logo" id="previewLogoVertikal">
                        </div>
                        <div class="col-md-10">
                            <h2 class="mb-0">{{ $system->app_name }} - {{ $campus->name }}</h2>
                            <p class="mb-1">{{ $system->app_description }}</p>
                            <p class="mb-0"><i class="fas fa-envelope"></i> {{ $system->app_email }} | <i class="fas fa-globe"></i> {{ $system->app_url }}</p>
                        </div>
                    </div>
                </div>

                <!-- Form Update Settings -->
                <form wire:submit.prevent="update" enctype="multipart/form-data">
                    @csrf
                    

                    <ul class="nav nav-tabs" role="tablist">
                        <li class="nav-item">
                            <a class="nav-link {{ $tab === 'aplikasi' ? 'active' : '' }}" wire:click="$set('tab', 'aplikasi')" href="#aplikasi" >
                                <i class="fas fa-cogs me-2"></i> Aplikasi
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link {{ $tab === 'kampus' ? 'active' : '' }}" wire:click="$set('tab', 'kampus')" href="#kampus" >
                                <i class="fas fa-university me-2"></i> Kampus
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link {{ $tab === 'logo' ? 'active' : '' }}" wire:click="$set('tab', 'logo')" href="#logo" >
                                <i class="fas fa-image me-2"></i> Logo & Favicon
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link {{ $tab === 'alamat' ? 'active' : '' }}" wire:click="$set('tab', 'alamat')" href="#alamat" >
                                <i class="fas fa-map-marker-alt me-2"></i> Alamat
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link {{ $tab === 'sosmed' ? 'active' : '' }}" wire:click="$set('tab', 'sosmed')" href="#sosmed" >
                                <i class="fas fa-share-alt me-2"></i> Sosial Media
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link {{ $tab === 'keamanan' ? 'active' : '' }}" wire:click="$set('tab', 'keamanan')" href="#keamanan" >
                                <i class="fas fa-lock me-2"></i> Keamanan
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link {{ $tab === 'notifikasi' ? 'active' : '' }}" wire:click="$set('tab', 'notifikasi')" href="#notifikasi" >
                                <i class="fab fa-whatsapp me-2"></i> Notifikasi
                            </a>
                        </li>
                    </ul>

                    <!-- Tab Content -->
                    <div class="tab-content p-3">
                        <!-- Tab Aplikasi -->
                        <div class="tab-pane {{ $tab === 'aplikasi' ? 'active show' : '' }}" id="aplikasi" role="tabpanel">
                            <div class="form-section">
                                <h5 class="mb-3">Informasi Aplikasi</h5>
                                <div class="row">
                                    <div class="col-md-6 mb-3">
                                        <label class="form-label">Nama Aplikasi <span class="text-danger">*</span></label>
                                        <input type="text" class="form-control" wire:model="systemForm.app_name" placeholder="Nama Aplikasi" required>
                                    </div>
                                    <div class="col-md-6 mb-3">
                                        <label class="form-label">Versi Aplikasi</label>
                                        <input type="text" class="form-control" wire:model="systemForm.app_version" placeholder="Versi Aplikasi">
                                    </div>
                                    <div class="col-md-12 mb-3">
                                        <label class="form-label">Deskripsi Aplikasi</label>
                                        <textarea class="form-control" wire:model="systemForm.app_description" rows="3" placeholder="Deskripsi singkat tentang aplikasi"></textarea>
                                    </div>
                                    <div class="col-md-6 mb-3">
                                        <label class="form-label">URL Aplikasi</label>
                                        <input type="url" class="form-control" wire:model="systemForm.app_url" placeholder="https://example.com">
                                    </div>
                                    <div class="col-md-6 mb-3">
                                        <label class="form-label">Email Aplikasi</label>
                                        <input type="email" class="form-control" wire:model="systemForm.app_email" placeholder="info@example.com">
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Tab Kampus -->
                        <div class="tab-pane {{ $tab === 'kampus' ? 'active show' : '' }}" id="kampus" role="tabpanel">
                            <div class="form-section">
                                <h5 class="mb-3">Informasi Kampus</h5>
                                <div class="row">
                                    <div class="col-md-6 mb-3">
                                        <label class="form-label">Nama Kampus <span class="text-danger">*</span></label>
                                        <input type="text" class="form-control" wire:model="campusForm.name" placeholder="Nama Kampus" required>
                                    </div>
                                    <div class="col-md-6 mb-3">
                                        <label class="form-label">Domain</label>
                                        <input type="text" class="form-control" wire:model="campusForm.domain" placeholder="example.com">
                                    </div>
                                    <div class="col-md-4 mb-3">
                                        <label class="form-label">Nomor Telepon</label>
                                        <input type="text" class="form-control" wire:model="campusForm.phone" placeholder="081234567890">
                                    </div>
                                    <div class="col-md-4 mb-3">
                                        <label class="form-label">Faximile</label>
                                        <input type="text" class="form-control" wire:model="campusForm.faximile" placeholder="081234567890">
                                    </div>
                                    <div class="col-md-4 mb-3">
                                        <label class="form-label">WhatsApp</label>
                                        <input type="text" class="form-control" wire:model="campusForm.whatsapp" placeholder="081234567890">
                                    </div>
                                    <div class="col-md-6 mb-3">
                                        <label class="form-label">Email Info</label>
                                        <input type="email" class="form-control" wire:model="campusForm.email_info" placeholder="info@example.com">
                                    </div>
                                    <div class="col-md-6 mb-3">
                                        <label class="form-label">Email Humas</label>
                                        <input type="email" class="form-control" wire:model="campusForm.email_humas" placeholder="humas@example.com">
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Tab Logo -->
                        <div class="tab-pane {{ $tab === 'logo' ? 'active show' : '' }}" id="logo" role="tabpanel">
                            <div class="form-section">
                                <h5 class="mb-3">Logo & Favicon</h5>
                                <div class="row">
                                    <div class="col-md-4 mb-3">
                                        <label class="form-label">Favicon</label>
                                        <input type="file" class="form-control" wire:model="appFavicon" onchange="previewImage(this, 'previewFavicon')" accept="image/png,image/x-icon">
                                        <small class="text-muted">Ukuran yang disarankan: 32x32 pixel</small>
                                        <div wire:loading wire:target="appFavicon" class="text-muted small mt-1">
                                            <i class="fas fa-spinner fa-spin me-1"></i> Mengupload...
                                        </div>
                                        <div class="mt-2">
                                            @if ($appFavicon)
                                                <img src="{{ $appFavicon->temporaryUrl() }}" class="preview-image">
                                            @else
                                                <img src="{{ $system->app_favicon }}" class="preview-image">
                                            @endif
                                        </div>
                                    </div>
                                    <div class="col-md-4 mb-3">
                                        <label class="form-label">Logo Vertikal</label>
                                        <input type="file" class="form-control" wire:model="appLogoVertikal" onchange="previewImage(this, 'previewLogoVertikal')" accept="image/*">
                                        <small class="text-muted">Ukuran yang disarankan: 200x200 pixel</small>
                                        <div wire:loading wire:target="appLogoVertikal" class="text-muted small mt-1">
                                            <i class="fas fa-spinner fa-spin me-1"></i> Mengupload...
                                        </div>
                                        <div class="mt-2">
                                            @if ($appLogoVertikal)
                                                <img src="{{ $appLogoVertikal->temporaryUrl() }}" alt="Logo Vertikal" class="preview-image">
                                            @else
                                                <img src="{{ $system->app_logo_vertikal }}" alt="Logo Vertikal" class="preview-image">
                                            @endif
                                        </div>
                                    </div>
                                    <div class="col-md-4 mb-3">
                                        <label class="form-label">Logo Horizontal</label>
                                        <input type="file" class="form-control" wire:model="appLogoHorizontal" onchange="previewImage(this, 'previewLogoHorizontal')" accept="image/*">
                                        <small class="text-muted">Ukuran yang disarankan: 300x100 pixel</small>
                                        <div wire:loading wire:target="appLogoHorizontal" class="text-muted small mt-1">
                                            <i class="fas fa-spinner fa-spin me-1"></i> Mengupload...
                                        </div>
                                        <div class="mt-2">
                                            @if ($appLogoHorizontal)
                                                <img src="{{ $appLogoHorizontal->temporaryUrl() }}" alt="Logo Horizontal" class="preview-image">
                                            @else
                                                <img src="{{ $system->app_logo_horizontal }}" alt="Logo Horizontal" class="preview-image">
                                            @endif
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Tab Alamat -->
                        <div class="tab-pane {{ $tab === 'alamat' ? 'active show' : '' }}" id="alamat" role="tabpanel">
                            <div class="form-section">
                                <h5 class="mb-3">Alamat Kampus</h5>
                                <div class="row">
                                    <div class="col-md-12 mb-3">
                                        <label class="form-label">Alamat Lengkap</label>
                                        <textarea class="form-control" wire:model="campusForm.address" rows="3" placeholder="Jl. Contoh No. 123, RT 01/RW 02"></textarea>
                                    </div>
                                    <div class="col-md-4 mb-3">
                                        <label class="form-label">Kota/Kabupaten</label>
                                        <input type="text" class="form-control" wire:model="campusForm.city" placeholder="Nama kota/kabupaten">
                                    </div>
                                    <div class="col-md-4 mb-3">
                                        <label class="form-label">Provinsi</label>
                                        <input type="text" class="form-control" wire:model="campusForm.province" placeholder="Nama provinsi">
                                    </div>
                                    <div class="col-md-4 mb-3">
                                        <label class="form-label">Kode Pos</label>
                                        <input type="text" class="form-control" wire:model="campusForm.postal_code" placeholder="12345" maxlength="10">
                                    </div>
                                </div>
                            </div>
                            <div class="form-section">
                                <h5 class="mb-3">Koordinat Lokasi</h5>
                                <div class="row">
                                    <div class="col-md-6 mb-3">
                                        <label class="form-label">Latitude</label>
                                        <input type="text" class="form-control" wire:model="campusForm.latitude" placeholder="-6.1234567">
                                    </div>
                                    <div class="col-md-6 mb-3">
                                        <label class="form-label">Longitude</label>
                                        <input type="text" class="form-control" wire:model="campusForm.longitude" placeholder="106.1234567">
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Tab Sosial Media -->
                        <div class="tab-pane {{ $tab === 'sosmed' ? 'active show' : '' }}" id="sosmed" role="tabpanel">
                            <div class="form-section">
                                <h5 class="mb-3">Sosial Media Kampus</h5>
                                <div class="row">
                                    <div class="col-md-4 mb-3">
                                        <label class="form-label"><i class="fab fa-instagram"></i> Instagram</label>
                                        <input type="text" class="form-control" wire:model="campusForm.instagram" placeholder="@username_instagram">
                                    </div>
                                    <div class="col-md-4 mb-3">
                                        <label class="form-label"><i class="fab fa-facebook"></i> Facebook</label>
                                        <input type="text" class="form-control" wire:model="campusForm.facebook" placeholder="facebook.com/username">
                                    </div>
                                    <div class="col-md-4 mb-3">
                                        <label class="form-label"><i class="fab fa-linkedin"></i> LinkedIn</label>
                                        <input type="text" class="form-control" wire:model="campusForm.linkedin" placeholder="linkedin.com/in/username">
                                    </div>
                                    <div class="col-md-4 mb-3">
                                        <label class="form-label"><i class="fab fa-twitter"></i> X/Twitter</label>
                                        <input type="text" class="form-control" wire:model="campusForm.xtwitter" placeholder="twitter.com/username">
                                    </div>
                                    <div class="col-md-4 mb-3">
                                        <label class="form-label"><i class="fab fa-tiktok"></i> TikTok</label>
                                        <input type="text" class="form-control" wire:model="campusForm.tiktok" placeholder="tiktok.com/@username">
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Tab Keamanan -->
                        <div class="tab-pane {{ $tab === 'keamanan' ? 'active show' : '' }}" id="keamanan" role="tabpanel">
                            <div class="form-section">
                                <h5 class="mb-3">Pengaturan Keamanan</h5>
                                <div class="row">
                                    <div class="col-md-6 mb-3">
                                        <div class="form-check form-switch">
                                            <input class="form-check-input" type="checkbox" wire:model="systemForm.maintenance_mode">
                                            <label class="form-check-label" for="maintenance_mode">Mode Maintenance</label>
                                        </div>
                                        <small class="text-muted">Aktifkan mode maintenance untuk menonaktifkan akses ke aplikasi sementara.</small>
                                    </div>
                                    <div class="col-md-6 mb-3">
                                        <div class="form-check form-switch">
                                            <input class="form-check-input" type="checkbox" wire:model="systemForm.enable_captcha" >
                                            <label class="form-check-label" for="enable_captcha">Aktifkan CAPTCHA</label>
                                        </div>
                                        <small class="text-muted">Aktifkan CAPTCHA untuk melindungi form login dari bot.</small>
                                    </div>
                                    <div class="col-md-6 mb-3">
                                        <label class="form-label">Batas Percobaan Login</label>
                                        <input type="number" class="form-control" wire:model="systemForm.max_login_attempts" min="1" max="10">
                                        <small class="text-muted">Jumlah maksimal percobaan login yang diizinkan sebelum akun dikunci.</small>
                                    </div>
                                    <div class="col-md-6 mb-3">
                                        <label class="form-label">Waktu Reset Percobaan Login (detik)</label>
                                        <input type="number" class="form-control" wire:model="systemForm.login_decay_seconds" min="30" max="3600">
                                        <small class="text-muted">Waktu (dalam detik) sebelum percobaan login direset.</small>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Tab Notifikasi -->
                        <div class="tab-pane {{ $tab === 'notifikasi' ? 'active show' : '' }}" id="notifikasi" role="tabpanel">
                            <div class="form-section">
                                <div class="d-flex flex-wrap align-items-start justify-content-between gap-3 mb-3">
                                    <div>
                                        <h5 class="mb-1">Pengaturan WhatsApp</h5>
                                        <small class="text-muted">Atur provider WhatsApp tanpa mengikat modul lain ke implementasi provider tertentu.</small>
                                    </div>
                                    <button type="button" class="btn btn-outline-primary" wire:click="checkWhatsappConfiguration">
                                        <i class="fas fa-plug me-2"></i> Cek Konfigurasi
                                    </button>
                                </div>

                                <div class="alert alert-{{ ($notificationHealth['status'] ?? null) === 'ready' ? 'success' : (($notificationHealth['status'] ?? null) === 'disabled' ? 'secondary' : 'warning') }} mb-4">
                                    <div class="d-flex">
                                        <div class="me-3">
                                            <i class="fas fa-circle-info"></i>
                                        </div>
                                        <div>
                                            <strong>Status: {{ str_replace('_', ' ', $notificationHealth['status'] ?? 'unknown') }}</strong>
                                            <div>{{ $notificationHealth['message'] ?? 'Status konfigurasi belum tersedia.' }}</div>
                                            @if (! empty($notificationHealth['issues']))
                                                <ul class="mb-0 mt-2">
                                                    @foreach ($notificationHealth['issues'] as $issue)
                                                        <li>{{ $issue }}</li>
                                                    @endforeach
                                                </ul>
                                            @endif
                                        </div>
                                    </div>
                                </div>

                                <div class="row">
                                    <div class="col-md-6 mb-3">
                                        <div class="form-check form-switch">
                                            <input class="form-check-input" type="checkbox" wire:model.live="notificationForm.whatsapp_enabled">
                                            <label class="form-check-label">Aktifkan WhatsApp</label>
                                        </div>
                                        <small class="text-muted">Jika nonaktif, modul tetap bisa memakai kanal fallback yang dipilih.</small>
                                    </div>
                                    <div class="col-md-6 mb-3">
                                        <label class="form-label">Provider Aktif</label>
                                        <select class="form-select" wire:model.live="notificationForm.whatsapp_provider">
                                            <option value="official_cloud_api">Official - Meta Cloud API</option>
                                            <option value="unofficial_web_session">Unofficial - Web Session Sidecar</option>
                                        </select>
                                        <small class="text-muted">Official direkomendasikan untuk production; unofficial cocok untuk kebutuhan internal atau uji coba terbatas.</small>
                                    </div>
                                    <div class="col-md-4 mb-3">
                                        <label class="form-label">Fallback</label>
                                        <select class="form-select" wire:model="notificationForm.fallback_channel">
                                            <option value="in_app">Notifikasi aplikasi</option>
                                            <option value="email">Email</option>
                                            <option value="none">Tanpa fallback</option>
                                        </select>
                                    </div>
                                    <div class="col-md-4 mb-3">
                                        <label class="form-label">Retry</label>
                                        <input type="number" class="form-control" wire:model="notificationForm.retry_attempts" min="0" max="5">
                                    </div>
                                    <div class="col-md-4 mb-3">
                                        <label class="form-label">Timeout (detik)</label>
                                        <input type="number" class="form-control" wire:model="notificationForm.timeout_seconds" min="5" max="120">
                                    </div>
                                </div>

                                @if (($notificationForm['whatsapp_provider'] ?? 'official_cloud_api') === 'official_cloud_api')
                                    <hr>
                                    <h6 class="mb-3">Meta Cloud API</h6>
                                    <div class="row">
                                        <div class="col-md-12 mb-3">
                                            <label class="form-label">Access Token</label>
                                            <input type="password" class="form-control" wire:model="notificationForm.official_config.access_token" autocomplete="new-password">
                                        </div>
                                        <div class="col-md-4 mb-3">
                                            <label class="form-label">Phone Number ID</label>
                                            <input type="text" class="form-control" wire:model="notificationForm.official_config.phone_number_id">
                                        </div>
                                        <div class="col-md-4 mb-3">
                                            <label class="form-label">Business Account ID</label>
                                            <input type="text" class="form-control" wire:model="notificationForm.official_config.business_account_id">
                                        </div>
                                        <div class="col-md-4 mb-3">
                                            <label class="form-label">Verify Token</label>
                                            <input type="password" class="form-control" wire:model="notificationForm.official_config.verify_token" autocomplete="new-password">
                                        </div>
                                        <div class="col-md-12 mb-3">
                                            <label class="form-label">App Secret</label>
                                            <input type="password" class="form-control" wire:model="notificationForm.official_config.app_secret" autocomplete="new-password">
                                        </div>
                                    </div>
                                @else
                                    <hr>
                                    <h6 class="mb-3">Web Session Sidecar</h6>
                                    <div class="alert alert-warning">
                                        Provider unofficial memakai sidecar bawaan package. Buka <a href="{{ url('/whatsapp/sessions') }}" target="_blank" class="alert-link">/whatsapp/sessions</a> untuk start session dan scan QR. Sidecar URL hanya diisi kalau memakai service eksternal.
                                    </div>
                                    <div class="alert alert-{{ $sidecarReachable ? 'success' : 'secondary' }}">
                                        <div class="d-flex flex-wrap align-items-center justify-content-between gap-2">
                                            <div>
                                                <strong>Status Sidecar:</strong> {{ $sidecarStatusMessage }}
                                            </div>
                                            <div class="d-flex flex-wrap gap-2">
                                                <button type="button" class="btn btn-sm btn-outline-primary" wire:click="refreshSidecarStatus(true)" wire:loading.attr="disabled" wire:target="refreshSidecarStatus">
                                                    <span wire:loading.remove wire:target="refreshSidecarStatus">
                                                        <i class="fas fa-rotate me-1"></i> Refresh
                                                    </span>
                                                    <span wire:loading wire:target="refreshSidecarStatus">
                                                        <i class="fas fa-spinner fa-spin me-1"></i> Cek...
                                                    </span>
                                                </button>
                                                @if ($sidecarReachable)
                                                    <button type="button" class="btn btn-sm btn-outline-danger" wire:click="stopBundledWhatsappSidecar" wire:loading.attr="disabled" wire:target="stopBundledWhatsappSidecar">
                                                        <span wire:loading.remove wire:target="stopBundledWhatsappSidecar">
                                                            <i class="fas fa-stop me-1"></i> Stop Sidecar
                                                        </span>
                                                        <span wire:loading wire:target="stopBundledWhatsappSidecar">
                                                            <i class="fas fa-spinner fa-spin me-1"></i> Menghentikan...
                                                        </span>
                                                    </button>
                                                @else
                                                    <button type="button" class="btn btn-sm btn-success" wire:click="startBundledWhatsappSidecar" wire:loading.attr="disabled" wire:target="startBundledWhatsappSidecar">
                                                        <span wire:loading.remove wire:target="startBundledWhatsappSidecar">
                                                            <i class="fas fa-play me-1"></i> Start Sidecar
                                                        </span>
                                                        <span wire:loading wire:target="startBundledWhatsappSidecar">
                                                            <i class="fas fa-spinner fa-spin me-1"></i> Menyalakan...
                                                        </span>
                                                    </button>
                                                @endif
                                            </div>
                                        </div>
                                    </div>
                                    <div class="row">
                                        <div class="col-md-6 mb-3">
                                            <label class="form-label">Sidecar URL Override</label>
                                            <input type="url" class="form-control" wire:model="notificationForm.unofficial_config.sidecar_url" placeholder="Kosongkan untuk sidecar bawaan package">
                                            <small class="text-muted">Default bawaan package: http://127.0.0.1:3000.</small>
                                        </div>
                                        <div class="col-md-6 mb-3">
                                            <label class="form-label">Session Name</label>
                                            <input type="text" class="form-control" wire:model="notificationForm.unofficial_config.session_name" placeholder="main">
                                            @if ($storedSidecarSessions !== [])
                                                <div class="d-flex flex-wrap align-items-center gap-2 mt-2">
                                                    <small class="text-muted">Session tersimpan:</small>
                                                    @foreach ($storedSidecarSessions as $storedSession)
                                                        <button type="button" class="btn btn-xs btn-outline-primary" wire:click="useStoredWhatsappSession(@js($storedSession))">
                                                            {{ $storedSession }}
                                                        </button>
                                                    @endforeach
                                                </div>
                                            @endif
                                        </div>
                                        <div class="col-md-12 mb-3">
                                            <label class="form-label">Shared Token</label>
                                            <input type="password" class="form-control" wire:model="notificationForm.unofficial_config.shared_token" autocomplete="new-password">
                                            <small class="text-muted">Opsional. Jika kosong, sidecar bawaan memakai token internal dari APP_KEY.</small>
                                        </div>
                                        <div class="col-md-12 mb-3">
                                            <div class="d-flex flex-wrap gap-2">
                                                <a href="{{ url('/whatsapp/sessions') }}" target="_blank" class="btn btn-outline-success">
                                                    <i class="fab fa-whatsapp me-2"></i> Buka QR Session
                                                </a>
                                                <a href="{{ url('/whatsapp') }}" target="_blank" class="btn btn-outline-secondary">
                                                    <i class="fas fa-gauge me-2"></i> Dashboard WhatsApp
                                                </a>
                                            </div>
                                        </div>
                                    </div>
                                @endif

                                <hr>
                                <h6 class="mb-3">Test Pengiriman</h6>
                                <div class="row align-items-end">
                                    <div class="col-md-8 mb-3">
                                        <label class="form-label">Nomor Tujuan Test</label>
                                        <input type="text" class="form-control" wire:model="testWhatsappRecipient" placeholder="Contoh: 081234567890">
                                        @error('testWhatsappRecipient') <span class="text-danger">{{ $message }}</span> @enderror
                                    </div>
                                    <div class="col-md-4 mb-3">
                                        <button type="button" class="btn btn-success w-100" wire:click="sendTestWhatsapp">
                                            <i class="fab fa-whatsapp me-2"></i> Kirim Test
                                        </button>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="d-flex justify-content-end mt-3">
                        <button type="submit" class="btn btn-primary">
                            <i class="fas fa-save me-2"></i> Simpan Pengaturan
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>
