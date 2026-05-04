<?php

use Livewire\Component;
use Livewire\WithFileUploads;
use App\Models\Settings\Campus;
use App\Models\Settings\System;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Cache;

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
    
    public function mount()
    {

        $this->campus = Campus::first() ?? new Campus();
        $this->system = System::first() ?? new System();

        $this->campusForm = $this->campus->toArray();
        $this->systemForm = $this->system->toArray();
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

