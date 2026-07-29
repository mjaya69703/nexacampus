<?php

use App\Models\Admission\AdmissionApplication;
use App\Models\Admission\AdmissionPeriod;
use App\Models\Academic\StudyProgram;
use Livewire\Component;

new class extends Component
{
    public string $applicationNumber = '';

    public string $email = '';

    public array $stats = [];

    public function mount(): void
    {
        $this->stats = [
            'open_intakes' => AdmissionPeriod::query()
                ->where('is_active', true)
                ->where('is_published', true)
                ->whereDate('opens_at', '<=', now())
                ->whereDate('closes_at', '>=', now())
                ->count(),
            'study_programs' => StudyProgram::query()
                ->where('is_active', true)
                ->count(),
            'submitted' => AdmissionApplication::query()
                ->where('status', 'submitted')
                ->count(),
            'under_review' => AdmissionApplication::query()
                ->where('status', 'under_review')
                ->count(),
        ];
    }

    public function checkStatus(): void
    {
        $this->validate([
            'applicationNumber' => 'required|string',
            'email' => 'required|email',
        ]);

        $application = AdmissionApplication::query()
            ->where('application_number', $this->applicationNumber)
            ->where('email', $this->email)
            ->first();

        if (! $application) {
            $this->addError('applicationNumber', 'Nomor pendaftaran atau email tidak ditemukan di dalam sistem.');

            return;
        }

        $this->redirectRoute('root.admission.portal', [
            'applicationNumber' => $application->application_number,
            'token' => $application->access_token,
        ]);
    }

    public function render()
    {
        return $this->view()->layout('layouts.home', [
            'menus' => 'Admission',
            'pages' => 'Check Status',
        ]);
    }
};
?>

<div class="admission-public">
    <div class="container-xl py-3 py-lg-4">
        <div class="row justify-content-center">
            <div class="col-12">
                {{-- Hero Banner --}}
                <div class="admission-hero mb-3">
                    <div class="row align-items-center g-3">
                        <div class="col-lg-8">
                            <div class="admission-kicker d-flex align-items-center gap-2 mb-2">
                                <span class="badge-pulse"></span>
                                <span>Status & Pelacakan Seleksi</span>
                            </div>
                            <h1 class="admission-title mb-2">Pantau Progres Pendaftaran & Jadwal Anda.</h1>
                            <p class="admission-subtitle mb-3">
                                Masukkan nomor pendaftaran dan email untuk membuka portal pendaftar.
                            </p>
                            <div class="d-flex flex-wrap gap-2">
                                <a href="{{ route('root.admission.apply') }}" class="btn btn-light fw-bold shadow-sm d-flex align-items-center gap-2">
                                    <i class="fas fa-paper-plane text-primary"></i> Daftar Gelombang Baru
                                </a>
                                <a href="{{ route('auth.signin-index') }}" class="btn btn-outline-light fw-bold d-flex align-items-center gap-2">
                                    <i class="fas fa-right-to-bracket"></i> Login Portal Utama
                                </a>
                            </div>
                        </div>
                        <div class="col-lg-4">
                            <div class="admission-hero-panel shadow">
                                <div class="d-flex justify-content-between align-items-center mb-2 pb-2 border-bottom border-light border-opacity-10">
                                    <span class="text-white-50 small fw-bold text-uppercase tracking-wider">Metode Autentikasi</span>
                                    <span class="badge bg-white text-primary px-2 py-1 rounded-pill fw-bold" style="font-size: 0.75rem;">Akses Cepat</span>
                                </div>
                                <div class="d-flex align-items-center gap-2 mb-3">
                                    <i class="fas fa-shield-check text-success"></i>
                                    <span class="text-white fw-semibold small">No. Pendaftaran + Email</span>
                                </div>
                                <div class="row g-2">
                                    <div class="col-6">
                                        <div class="hero-mini-stat text-center">
                                            <span class="text-white">{{ $stats['open_intakes'] }}</span>
                                            <small class="text-white-50" style="font-size: 0.7rem;">Gelombang Dibuka</small>
                                        </div>
                                    </div>
                                    <div class="col-6">
                                        <div class="hero-mini-stat text-center">
                                            <span class="text-warning">{{ $stats['study_programs'] }}</span>
                                            <small class="text-white-50" style="font-size: 0.7rem;">Program Pilihan</small>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                {{-- Stats Row --}}
                <div class="row g-3 mb-4">
                    <div class="col-md-3 col-6">
                        <div class="admission-stat d-flex align-items-center gap-3 shadow-sm border-0">
                            <div class="stat-icon-wrap bg-primary bg-opacity-10 text-primary">
                                <i class="fas fa-calendar-check"></i>
                            </div>
                            <div>
                                <span class="stat-num">{{ $stats['open_intakes'] }}</span>
                                <small class="text-muted fw-semibold">Gelombang Aktif</small>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3 col-6">
                        <div class="admission-stat d-flex align-items-center gap-3 shadow-sm border-0">
                            <div class="stat-icon-wrap bg-success bg-opacity-10 text-success">
                                <i class="fas fa-graduation-cap"></i>
                            </div>
                            <div>
                                <span class="stat-num">{{ $stats['study_programs'] }}</span>
                                <small class="text-muted fw-semibold">Program Studi</small>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3 col-6">
                        <div class="admission-stat d-flex align-items-center gap-3 shadow-sm border-0">
                            <div class="stat-icon-wrap bg-info bg-opacity-10 text-info">
                                <i class="fas fa-inbox"></i>
                            </div>
                            <div>
                                <span class="stat-num">{{ $stats['submitted'] }}</span>
                                <small class="text-muted fw-semibold">Total Pendaftar</small>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3 col-6">
                        <div class="admission-stat d-flex align-items-center gap-3 shadow-sm border-0">
                            <div class="stat-icon-wrap bg-warning bg-opacity-10 text-warning">
                                <i class="fas fa-user-clock"></i>
                            </div>
                            <div>
                                <span class="stat-num">{{ $stats['under_review'] }}</span>
                                <small class="text-muted fw-semibold">Sedang Diperiksa</small>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="row g-3 align-items-start">
                    <div class="col-lg-8">
                        <div class="admission-card shadow-sm border-0 rounded-3 overflow-hidden">
                            <div class="admission-card-header d-flex align-items-center justify-content-between p-3 bg-surface border-bottom">
                                <div class="d-flex align-items-center gap-2">
                                    <div class="step-badge"><i class="fas fa-magnifying-glass"></i></div>
                                    <div>
                                        <h4 class="mb-0 fw-bolder fs-6">Lacak Status & Masuk Portal</h4>
                                        <small class="text-muted" style="font-size: 0.8rem;">Gunakan kredensial saat submit pendaftaran</small>
                                    </div>
                                </div>
                                <span class="badge bg-primary bg-opacity-10 text-primary px-2 py-1 rounded-pill fw-bold" style="font-size: 0.75rem;">Aman & Terenkripsi</span>
                            </div>
                            <div class="admission-card-body p-3 p-lg-4">
                                <div class="alert alert-light border rounded-3 p-2 mb-3 d-flex align-items-center gap-2 shadow-sm">
                                    <i class="fas fa-circle-info text-primary flex-shrink-0"></i>
                                    <div style="font-size: 0.8rem;">
                                        Nomor pendaftaran (misal: <code class="fw-bold text-primary">ADM2026W1-0001</code>) dikirimkan melalui email.
                                    </div>
                                </div>

                                <div class="mb-3">
                                    <label class="form-label fw-bold text-uppercase" style="font-size: 0.75rem;">Nomor Pendaftaran <span class="text-danger">*</span></label>
                                    <div class="input-group shadow-sm">
                                        <span class="input-group-text bg-light border-end-0 text-muted"><i class="fas fa-id-card"></i></span>
                                        <input type="text" class="form-control modern-input border-start-0" placeholder="Contoh: ADM2026W1-0001" wire:model.defer="applicationNumber">
                                    </div>
                                    @error('applicationNumber') <span class="text-danger mt-1 d-block" style="font-size: 0.8rem;"><i class="fas fa-exclamation-circle me-1"></i>{{ $message }}</span> @enderror
                                </div>

                                <div class="mb-3">
                                    <label class="form-label fw-bold text-uppercase" style="font-size: 0.75rem;">Alamat Email Terdaftar <span class="text-danger">*</span></label>
                                    <div class="input-group shadow-sm">
                                        <span class="input-group-text bg-light border-end-0 text-muted"><i class="fas fa-envelope"></i></span>
                                        <input type="email" class="form-control modern-input border-start-0" placeholder="nama@email.com" wire:model.defer="email">
                                    </div>
                                    @error('email') <span class="text-danger mt-1 d-block" style="font-size: 0.8rem;"><i class="fas fa-exclamation-circle me-1"></i>{{ $message }}</span> @enderror
                                </div>

                                <button class="btn btn-primary w-100 py-2 rounded-pill fw-bold shadow-sm d-flex align-items-center justify-content-center gap-2 mt-2 transition-transform" wire:click="checkStatus" wire:loading.attr="disabled">
                                    <i class="fas fa-right-to-bracket" wire:loading.remove></i>
                                    <span wire:loading.remove>Periksa Status & Buka Portal</span>
                                    <span wire:loading><i class="fas fa-spinner fa-spin me-2"></i>Mencari Data...</span>
                                </button>
                            </div>
                        </div>
                    </div>

                    <div class="col-lg-4">
                        <div class="admission-card mb-3 shadow-sm border-0 rounded-3 overflow-hidden">
                            <div class="admission-card-header p-3 bg-surface border-bottom">
                                <h4 class="mb-0 fw-bolder fs-6 d-flex align-items-center gap-2">
                                    <i class="fas fa-bolt text-warning"></i> Menu Cepat
                                </h4>
                            </div>
                            <div class="admission-link-list">
                                <a href="{{ route('root.admission.apply') }}" class="shortcut-link border-bottom py-2 px-3">
                                    <div class="shortcut-icon"><i class="fas fa-paper-plane"></i></div>
                                    <div>
                                        <div class="fw-bold text-body" style="font-size: 0.9rem;">Daftar Gelombang Baru</div>
                                        <div class="text-muted" style="font-size: 0.8rem;">Isi formulir pendaftaran mahasiswa baru</div>
                                    </div>
                                </a>
                                <a href="{{ route('auth.signin-index') }}" class="shortcut-link py-2 px-3">
                                    <div class="shortcut-icon"><i class="fas fa-user-shield"></i></div>
                                    <div>
                                        <div class="fw-bold text-body" style="font-size: 0.9rem;">Login NexaCampus</div>
                                        <div class="text-muted" style="font-size: 0.8rem;">Akses portal mahasiswa aktif / dosen</div>
                                    </div>
                                </a>
                            </div>
                        </div>

                        <div class="admission-card shadow-sm border-0 rounded-3 overflow-hidden">
                            <div class="admission-card-header p-3 bg-surface border-bottom">
                                <h4 class="mb-0 fw-bolder fs-6 d-flex align-items-center gap-2">
                                    <i class="fas fa-list-check text-primary"></i> Persiapan Sebelum Masuk
                                </h4>
                            </div>
                            <div class="admission-card-body p-3">
                                <div class="check-item mb-2">
                                    <div class="check-icon bg-success bg-opacity-10 text-success"><i class="fas fa-check"></i></div>
                                    <div>
                                        <div class="fw-semibold text-body" style="font-size: 0.85rem;">Siapkan Nomor Pendaftaran</div>
                                        <div class="text-muted" style="font-size: 0.75rem;">Tercantum pada tanda terima atau email</div>
                                    </div>
                                </div>
                                <div class="check-item mb-2">
                                    <div class="check-icon bg-success bg-opacity-10 text-success"><i class="fas fa-check"></i></div>
                                    <div>
                                        <div class="fw-semibold text-body" style="font-size: 0.85rem;">Gunakan Email yang Sama</div>
                                        <div class="text-muted" style="font-size: 0.75rem;">Penulisan huruf kecil/besar harus sesuai</div>
                                    </div>
                                </div>
                                <div class="check-item">
                                    <div class="check-icon bg-success bg-opacity-10 text-success"><i class="fas fa-check"></i></div>
                                    <div>
                                        <div class="fw-semibold text-body" style="font-size: 0.85rem;">Re-upload Dokumen</div>
                                        <div class="text-muted" style="font-size: 0.75rem;">Di dalam portal Anda dapat mengunggah ulang berkas</div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>


