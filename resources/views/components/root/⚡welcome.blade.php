<?php

use App\Enums\AnnouncementTargetType;
use App\Models\Academic\StudyProgram;
use App\Models\Admission\AdmissionPeriod;
use App\Models\Publication\Announcement;
use Livewire\Component;

new class extends Component
{
    public array $latestAnnouncements = [];
    public int $totalStudyPrograms = 0;
    public int $activeAdmissionPeriods = 0;
    public string $activeRoleTab = 'student';

    public function mount(): void
    {
        $this->totalStudyPrograms = StudyProgram::where('is_active', true)->count();
        $this->activeAdmissionPeriods = AdmissionPeriod::where('is_active', true)->where('is_published', true)->count();

        $announcements = Announcement::published()
            ->where('target_type', AnnouncementTargetType::GLOBAL)
            ->orderByDesc('is_pinned')
            ->orderByDesc('published_at')
            ->limit(3)
            ->get();

        $this->latestAnnouncements = $announcements->map(fn ($a) => [
            'id' => $a->id,
            'title' => $a->title,
            'excerpt' => \Illuminate\Support\Str::limit(strip_tags($a->content), 140),
            'priority' => $a->priority?->value ?? 'normal',
            'published_at' => $a->published_at?->format('d M Y') ?? '-',
            'is_pinned' => (bool) $a->is_pinned,
            'has_attachment' => filled($a->attachment_path),
        ])->toArray();
    }

    public function setRoleTab(string $role): void
    {
        $this->activeRoleTab = $role;
    }

    public function render()
    {
        $user = auth()->user();
        $loginUrl = route('auth.signin-index');
        $admissionUrl = route('root.admission.apply');
        $statusUrl = route('root.admission.status');

        $dashboardRoute = $user ? $user->prefix.'dashboard.index' : null;
        $dashboardUrl = $dashboardRoute && \Illuminate\Support\Facades\Route::has($dashboardRoute)
            ? route($dashboardRoute)
            : (Route::has('auth.select-role') ? route('auth.select-role') : $loginUrl);

        return $this->view([
            'user' => $user,
            'loginUrl' => $loginUrl,
            'admissionUrl' => $admissionUrl,
            'statusUrl' => $statusUrl,
            'dashboardUrl' => $dashboardUrl,
        ])->layout('layouts.home', [
            'menus' => 'Beranda',
            'pages' => 'SIAKAD & LMS Terpadu',
        ]);
    }
};
?>

<div class="admission-public">
    <div class="container-xl py-4 py-lg-5">
        <div class="row justify-content-center">
            <div class="col-12">

                {{-- Hero Banner --}}
                <div class="admission-hero mb-5 position-relative overflow-hidden">
                    <div class="row align-items-center g-4 position-relative" style="z-index: 2;">
                        <div class="col-lg-7">
                            <div class="d-inline-flex align-items-center gap-2 mb-3 px-3 py-1.5 rounded-pill shadow-sm" style="background: rgba(0, 0, 0, 0.35); border: 1px solid rgba(255, 255, 255, 0.25); color: #ffffff !important;">
                                <span class="badge-pulse"></span>
                                <span class="fw-bold small" style="color: #ffffff !important;">SIAKAD &amp; LMS Terintegrasi Gen-3</span>
                            </div>
                            <h1 class="admission-title mb-3 display-4 fw-black text-white" style="letter-spacing: -.03em; line-height: 1.1;">
                                Transformasi Digital Kampus<br>
                                <span class="text-warning">Tanpa Batas</span>
                            </h1>
                            <p class="admission-subtitle mb-4 text-white-50 lead" style="max-width: 580px; font-size: 1.1rem; line-height: 1.6;">
                                Satu ekosistem pintar terpadu untuk mengelola Penerimaan Mahasiswa Baru (PMB), perkuliahan online, presensi QR, LMS, KRS, hingga keuangan kampus secara *real-time*.
                            </p>

                            <div class="d-flex flex-wrap gap-3 mb-4">
                                @auth
                                    <a href="{{ $dashboardUrl }}" class="btn btn-warning btn-lg px-4 fw-bold rounded-pill shadow-lg d-inline-flex align-items-center gap-2">
                                        <i class="fas fa-chart-line"></i>
                                        <span>Masuk Dashboard ({{ strtoupper(auth()->user()->roles->first()?->name ?? 'User') }})</span>
                                    </a>
                                @else
                                    <a href="{{ $loginUrl }}" class="btn btn-warning btn-lg px-4 fw-bold rounded-pill shadow-lg d-inline-flex align-items-center gap-2">
                                        <i class="fas fa-right-to-bracket"></i>
                                        <span>Portal Login SSO</span>
                                    </a>
                                @endauth

                                <a href="{{ $admissionUrl }}" class="btn btn-outline-light btn-lg px-4 fw-bold rounded-pill d-inline-flex align-items-center gap-2">
                                    <i class="fas fa-paper-plane text-warning"></i>
                                    <span>Pendaftaran PMB Online</span>
                                </a>
                            </div>

                            <div class="d-flex flex-wrap align-items-center gap-4 pt-3 border-top border-white border-opacity-15">
                                <div class="d-flex align-items-center gap-2 text-white">
                                    <div class="rounded-circle bg-white bg-opacity-15 d-flex align-items-center justify-content-center" style="width: 36px; height: 36px;">
                                        <i class="fas fa-shield-check text-warning"></i>
                                    </div>
                                    <div>
                                        <div class="fw-bold small lh-1">Akreditasi Unggul</div>
                                        <small class="text-white-50" style="font-size: .72rem;">BAN-PT & LAM-INFOKOM</small>
                                    </div>
                                </div>
                                <div class="d-flex align-items-center gap-2 text-white">
                                    <div class="rounded-circle bg-white bg-opacity-15 d-flex align-items-center justify-content-center" style="width: 36px; height: 36px;">
                                        <i class="fas fa-graduation-cap text-info"></i>
                                    </div>
                                    <div>
                                        <div class="fw-bold small lh-1">{{ $totalStudyPrograms > 0 ? $totalStudyPrograms : 12 }} Program Studi</div>
                                        <small class="text-white-50" style="font-size: .72rem;">Diploma, Sarjana & Magister</small>
                                    </div>
                                </div>
                                <div class="d-flex align-items-center gap-2 text-white">
                                    <div class="rounded-circle bg-white bg-opacity-15 d-flex align-items-center justify-content-center" style="width: 36px; height: 36px;">
                                        <i class="fas fa-bullseye text-success"></i>
                                    </div>
                                    <div>
                                        <div class="fw-bold small lh-1">Gelombang PMB</div>
                                        <small class="text-white-50" style="font-size: .72rem;">{{ $activeAdmissionPeriods > 0 ? 'Sedang Dibuka' : 'Jalur Reguler & Prestasi' }}</small>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="col-lg-5">
                            <div class="admission-hero-panel shadow-lg p-4 rounded-4 border border-white border-opacity-10 backdrop-blur">
                                <div class="d-flex justify-content-between align-items-center mb-3 pb-3 border-bottom border-white border-opacity-15">
                                    <div>
                                        <div class="text-white-50 small fw-bold text-uppercase">Metrik Layanan</div>
                                        <div class="h3 text-white mb-0 fw-black">Performa Sistem</div>
                                    </div>
                                    <span class="badge bg-white text-primary px-3 py-2 rounded-pill fw-bold">Live Portal</span>
                                </div>

                                <div class="row g-3">
                                    <div class="col-6">
                                        <div class="hero-mini-stat text-center p-3 rounded-3 bg-black bg-opacity-20">
                                            <span class="text-warning fw-black d-block mb-1" style="font-size: 1.6rem;">99.9%</span>
                                            <small class="text-white-50">Uptime System</small>
                                        </div>
                                    </div>
                                    <div class="col-6">
                                        <div class="hero-mini-stat text-center p-3 rounded-3 bg-black bg-opacity-20">
                                            <span class="text-info fw-black d-block mb-1" style="font-size: 1.6rem;">24/7</span>
                                            <small class="text-white-50">Akses Portal</small>
                                        </div>
                                    </div>
                                    <div class="col-6">
                                        <div class="hero-mini-stat text-center p-3 rounded-3 bg-black bg-opacity-20">
                                            <span class="text-success fw-black d-block mb-1" style="font-size: 1.6rem;">Instant</span>
                                            <small class="text-white-50">Validasi KRS & Presensi</small>
                                        </div>
                                    </div>
                                    <div class="col-6">
                                        <div class="hero-mini-stat text-center p-3 rounded-3 bg-black bg-opacity-20">
                                            <span class="text-white fw-black d-block mb-1" style="font-size: 1.6rem;">Multi-VA</span>
                                            <small class="text-white-50">Pembayaran Bank</small>
                                        </div>
                                    </div>
                                </div>

                                <div class="mt-4 pt-3 border-top border-white border-opacity-15">
                                    <a href="{{ route('root.admission.status') }}" class="btn btn-outline-light w-100 rounded-pill fw-bold">
                                        <i class="fas fa-magnifying-glass me-2 text-warning"></i>Cek Status Pendaftaran PMB
                                    </a>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                {{-- Quick Access Grid --}}
                <div class="mb-5">
                    <div class="d-flex align-items-center gap-3 mb-4">
                        <div class="step-badge" style="background: linear-gradient(135deg, #3b82f6, #1d4ed8);"><i class="fas fa-compass"></i></div>
                        <div>
                            <h3 class="fw-bolder text-body mb-0" style="font-size:1.25rem;">Akses Cepat & Fitur Publik</h3>
                            <div class="text-muted" style="font-size:.85rem;">Pilih menu di bawah ini untuk mengakses layanan informasi utama kampus.</div>
                        </div>
                    </div>

                    <div class="row g-3">
                        @foreach([
                            ['icon' => 'fa-file-signature', 'color' => '#3b82f6', 'title' => 'Pendaftaran PMB', 'desc' => 'Isi biodata, pilih prodi, dan daftar mahasiswa baru online.', 'route' => 'root.admission.apply', 'btn' => 'Daftar PMB'],
                            ['icon' => 'fa-magnifying-glass', 'color' => '#8b5cf6', 'title' => 'Cek Status PMB', 'desc' => 'Pantau status kelulusan dan tahapan seleksi calon mahasiswa.', 'route' => 'root.admission.status', 'btn' => 'Cek Status'],
                            ['icon' => 'fa-money-bill-wave', 'color' => '#10b981', 'title' => 'Biaya Pendidikan', 'desc' => 'Informasi rincian UKT, SPP, dan skema cicilan pembayaran.', 'route' => 'root.admission.tuition', 'btn' => 'Lihat Biaya'],
                            ['icon' => 'fa-list-check', 'color' => '#f59e0b', 'title' => 'Jalur & Syarat', 'desc' => 'Persyaratan berkas umum dan khusus per gelombang pendaftaran.', 'route' => 'root.admission.requirements', 'btn' => 'Lihat Syarat'],
                            ['icon' => 'fa-circle-question', 'color' => '#06b6d4', 'title' => 'Pusat Bantuan (FAQ)', 'desc' => 'Jawaban pertanyaan umum seputar PMB, Akademik, dan Keuangan.', 'route' => 'root.faq', 'btn' => 'Buka FAQ'],
                            ['icon' => 'fa-envelope-open-text', 'color' => '#ec4899', 'title' => 'Kontak Kampus', 'desc' => 'Alamat lokasi, email, nomor hotline, dan jam operasional kampus.', 'route' => 'root.kontak', 'btn' => 'Hubungi Kami'],
                        ] as $item)
                        <div class="col-lg-4 col-sm-6">
                            <div class="admission-card p-4 rounded-4 border-0 shadow-sm h-100 d-flex flex-column" style="transition: transform .2s ease;" onmouseover="this.style.transform='translateY(-4px)'" onmouseout="this.style.transform='none'">
                                <div class="d-flex align-items-center gap-3 mb-3">
                                    <div style="width: 48px; height: 48px; border-radius: 14px; background: {{ $item['color'] }}18; color: {{ $item['color'] }}; display: flex; align-items: center; justify-content: center; font-size: 1.25rem; flex-shrink: 0;">
                                        <i class="fas {{ $item['icon'] }}"></i>
                                    </div>
                                    <h4 class="fw-bolder text-body mb-0" style="font-size: 1rem;">{{ $item['title'] }}</h4>
                                </div>
                                <p class="text-muted mb-4 flex-fill" style="font-size: .86rem; line-height: 1.6;">{{ $item['desc'] }}</p>
                                <a href="{{ route($item['route']) }}" class="btn btn-outline-primary rounded-pill fw-bold w-100">
                                    {{ $item['btn'] }} <i class="fas fa-arrow-right ms-1"></i>
                                </a>
                            </div>
                        </div>
                        @endforeach
                    </div>
                </div>

                {{-- Interactive Domain Modules Showcase --}}
                <div class="mb-5">
                    <div class="d-flex align-items-center gap-3 mb-4">
                        <div class="step-badge" style="background: linear-gradient(135deg, #10b981, #059669);"><i class="fas fa-layer-group"></i></div>
                        <div>
                            <h3 class="fw-bolder text-body mb-0" style="font-size:1.25rem;">Ekosistem Modul SIAKAD Terpadu</h3>
                            <div class="text-muted" style="font-size:.85rem;">Terintegrasi tanpa jeda dari proses pembelajaran hingga administrasi akademik.</div>
                        </div>
                    </div>

                    <div class="row g-4">
                        <div class="col-lg-4 col-md-6">
                            <div class="admission-card p-4 rounded-4 border-0 shadow-sm h-100">
                                <div class="d-inline-flex align-items-center justify-content-center p-3 rounded-3 mb-3 bg-primary bg-opacity-10 text-primary">
                                    <i class="fas fa-graduation-cap fs-3"></i>
                                </div>
                                <h4 class="fw-bolder text-body mb-2">1. Akademik &amp; KRS Online</h4>
                                <p class="text-muted mb-3" style="font-size: .875rem; line-height: 1.65;">
                                    Pengisian Kartu Rencana Studi (KRS) mandiri, jadwal kuliah otomatis, presensi QR code, transkrip nilai nilai semester, dan kalender akademik terpadu.
                                </p>
                                <div class="d-flex flex-wrap gap-1">
                                    <span class="badge bg-primary-lt text-primary">KRS Online</span>
                                    <span class="badge bg-primary-lt text-primary">Presensi QR</span>
                                    <span class="badge bg-primary-lt text-primary">Transkrip Nilai</span>
                                </div>
                            </div>
                        </div>

                        <div class="col-lg-4 col-md-6">
                            <div class="admission-card p-4 rounded-4 border-0 shadow-sm h-100">
                                <div class="d-inline-flex align-items-center justify-content-center p-3 rounded-3 mb-3 bg-info bg-opacity-10 text-info">
                                    <i class="fas fa-laptop-code fs-3"></i>
                                </div>
                                <h4 class="fw-bolder text-body mb-2">2. Learning Management (LMS)</h4>
                                <p class="text-muted mb-3" style="font-size: .875rem; line-height: 1.65;">
                                    Portal mengajar dosen dan belajar mahasiswa: pengunggahan modul/materi, tugas &amp; pengumpulan berkas, diskusi online, dan grade book transparan.
                                </p>
                                <div class="d-flex flex-wrap gap-1">
                                    <span class="badge bg-info-lt text-info">Modul Kuliah</span>
                                    <span class="badge bg-info-lt text-info">Tugas & Kuis</span>
                                    <span class="badge bg-info-lt text-info">Forum Diskusi</span>
                                </div>
                            </div>
                        </div>

                        <div class="col-lg-4 col-md-6">
                            <div class="admission-card p-4 rounded-4 border-0 shadow-sm h-100">
                                <div class="d-inline-flex align-items-center justify-content-center p-3 rounded-3 mb-3 bg-success bg-opacity-10 text-success">
                                    <i class="fas fa-wallet fs-3"></i>
                                </div>
                                <h4 class="fw-bolder text-body mb-2">3. Keuangan &amp; Tagihan SPP</h4>
                                <p class="text-muted mb-3" style="font-size: .875rem; line-height: 1.65;">
                                    Sistem billing terintegrasi dengan Multi Virtual Account bank, penerbitan kuitansi PDF resmi, pengajuan cicilan UKT, dan aturan *clearance hold*.
                                </p>
                                <div class="d-flex flex-wrap gap-1">
                                    <span class="badge bg-success-lt text-success">Virtual Account</span>
                                    <span class="badge bg-success-lt text-success">Kuitansi PDF</span>
                                    <span class="badge bg-success-lt text-success">Pengajuan Cicilan</span>
                                </div>
                            </div>
                        </div>

                        <div class="col-lg-4 col-md-6">
                            <div class="admission-card p-4 rounded-4 border-0 shadow-sm h-100">
                                <div class="d-inline-flex align-items-center justify-content-center p-3 rounded-3 mb-3 bg-warning bg-opacity-10 text-warning">
                                    <i class="fas fa-user-plus fs-3"></i>
                                </div>
                                <h4 class="fw-bolder text-body mb-2">4. Penerimaan Mahasiswa (PMB)</h4>
                                <p class="text-muted mb-3" style="font-size: .875rem; line-height: 1.65;">
                                    Formulir pendaftaran digital, seleksi berkas &amp; nilai rapor, ujian CBT online, ranking kuota prodi, dan pembentukan NIM otomatis.
                                </p>
                                <div class="d-flex flex-wrap gap-1">
                                    <span class="badge bg-warning-lt text-warning">PMB Online</span>
                                    <span class="badge bg-warning-lt text-warning">Ujian CBT</span>
                                    <span class="badge bg-warning-lt text-warning">Auto Generate NIM</span>
                                </div>
                            </div>
                        </div>

                        <div class="col-lg-4 col-md-6">
                            <div class="admission-card p-4 rounded-4 border-0 shadow-sm h-100">
                                <div class="d-inline-flex align-items-center justify-content-center p-3 rounded-3 mb-3 bg-danger bg-opacity-10 text-danger">
                                    <i class="fas fa-hands-helping fs-3"></i>
                                </div>
                                <h4 class="fw-bolder text-body mb-2">5. Layanan &amp; Yudisium</h4>
                                <p class="text-muted mb-3" style="font-size: .875rem; line-height: 1.65;">
                                    Pengajuan surat keterangan mahasiswa aktif, permohonan cuti studi, transfer prodi, pendaftaran yudisium, hingga tracer study alumni.
                                </p>
                                <div class="d-flex flex-wrap gap-1">
                                    <span class="badge bg-danger-lt text-danger">Surat Digital</span>
                                    <span class="badge bg-danger-lt text-danger">Pengajuan Cuti</span>
                                    <span class="badge bg-danger-lt text-danger">Tracer Study</span>
                                </div>
                            </div>
                        </div>

                        <div class="col-lg-4 col-md-6">
                            <div class="admission-card p-4 rounded-4 border-0 shadow-sm h-100">
                                <div class="d-inline-flex align-items-center justify-content-center p-3 rounded-3 mb-3 bg-purple bg-opacity-10 text-purple" style="color: #8b5cf6;">
                                    <i class="fas fa-newspaper fs-3"></i>
                                </div>
                                <h4 class="fw-bolder text-body mb-2">6. Publikasi &amp; Pengumuman</h4>
                                <p class="text-muted mb-3" style="font-size: .875rem; line-height: 1.65;">
                                    Penyampaian informasi kampus cepat dan tepat sasaran per peran (global, fakultas, prodi, kelas) dengan sistem prioritas dan lampiran.
                                </p>
                                <div class="d-flex flex-wrap gap-1">
                                    <span class="badge bg-secondary-lt text-secondary">Pengumuman Target</span>
                                    <span class="badge bg-secondary-lt text-secondary">Berita Kampus</span>
                                    <span class="badge bg-secondary-lt text-secondary">Galeri & Event</span>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                {{-- Multi-Role Tab Showcase --}}
                <div class="mb-5">
                    <div class="d-flex align-items-center gap-3 mb-4">
                        <div class="step-badge" style="background: linear-gradient(135deg, #8b5cf6, #6d28d9);"><i class="fas fa-users-gear"></i></div>
                        <div>
                            <h3 class="fw-bolder text-body mb-0" style="font-size:1.25rem;">Pengalaman Pengguna Sesuai Peran</h3>
                            <div class="text-muted" style="font-size:.85rem;">Antarmuka yang dirancang khusus untuk masing-masing civitas akademika.</div>
                        </div>
                    </div>

                    <div class="admission-card p-4 rounded-4 border-0 shadow-sm">
                        <div class="d-flex flex-column flex-sm-row gap-2 p-2 rounded-3 bg-light border mb-4">
                            <button type="button" 
                                class="btn rounded-3 fw-bold flex-fill border-0 py-2.5 px-3 d-flex align-items-center justify-content-center gap-2"
                                style="{{ $activeRoleTab === 'student' ? 'background: #206bc4; color: #ffffff !important; box-shadow: 0 4px 12px rgba(32,107,196,0.3);' : 'background: transparent; color: #495057;' }}"
                                wire:click="setRoleTab('student')">
                                <i class="fas fa-user-graduate"></i>
                                <span>Portal Mahasiswa</span>
                            </button>
                            <button type="button" 
                                class="btn rounded-3 fw-bold flex-fill border-0 py-2.5 px-3 d-flex align-items-center justify-content-center gap-2"
                                style="{{ $activeRoleTab === 'lecturer' ? 'background: #206bc4; color: #ffffff !important; box-shadow: 0 4px 12px rgba(32,107,196,0.3);' : 'background: transparent; color: #495057;' }}"
                                wire:click="setRoleTab('lecturer')">
                                <i class="fas fa-chalkboard-user"></i>
                                <span>Portal Dosen</span>
                            </button>
                            <button type="button" 
                                class="btn rounded-3 fw-bold flex-fill border-0 py-2.5 px-3 d-flex align-items-center justify-content-center gap-2"
                                style="{{ $activeRoleTab === 'admin' ? 'background: #206bc4; color: #ffffff !important; box-shadow: 0 4px 12px rgba(32,107,196,0.3);' : 'background: transparent; color: #495057;' }}"
                                wire:click="setRoleTab('admin')">
                                <i class="fas fa-user-shield"></i>
                                <span>Admin &amp; Operator</span>
                            </button>
                        </div>

                        <div class="tab-content">
                            @if($activeRoleTab === 'student')
                                <div class="row align-items-center g-4">
                                    <div class="col-lg-6">
                                        <h4 class="fw-bolder text-body mb-3"><i class="fas fa-graduation-cap text-primary me-2"></i>Kemudahan Studi dalam Satu Genggaman</h4>
                                        <p class="text-muted" style="line-height: 1.7;">
                                            Mahasiswa dapat menyusun rencana studi (KRS) saat masa registrasi dibuka, memantau jadwal kuliah harian, melakukan scan QR presensi di kelas, mengakses bahan ajar LMS, melihat invoice tagihan SPP, hingga mengunduh transkrip nilai secara langsung.
                                        </p>
                                        <ul class="list-unstyled d-flex flex-column gap-2 mb-4 text-secondary" style="font-size: .9rem;">
                                            <li><i class="fas fa-check-circle text-success me-2"></i>KRS Online dengan validasi kuota & dosen pembimbing.</li>
                                            <li><i class="fas fa-check-circle text-success me-2"></i>Presensi QR Code instan tanpa antri kertas.</li>
                                            <li><i class="fas fa-check-circle text-success me-2"></i>Unduh kuitansi pembayaran & transkrip nilai resmi.</li>
                                        </ul>
                                    </div>
                                    <div class="col-lg-6">
                                        <div class="p-4 rounded-4 bg-primary bg-opacity-10 border border-primary border-opacity-20">
                                            <div class="d-flex align-items-center gap-3 mb-3">
                                                <span class="avatar avatar-md bg-primary text-white rounded-circle"><i class="fas fa-id-card"></i></span>
                                                <div>
                                                    <div class="fw-bold text-dark mb-0">Fitur Utama Mahasiswa</div>
                                                    <small class="text-muted">Akses 24 jam via smartphone & PC</small>
                                                </div>
                                            </div>
                                            <div class="row g-2 text-center">
                                                <div class="col-6"><div class="p-2 rounded bg-white shadow-sm fw-semibold text-primary small">KRS & Schedule</div></div>
                                                <div class="col-6"><div class="p-2 rounded bg-white shadow-sm fw-semibold text-primary small">LMS & Assignment</div></div>
                                                <div class="col-6"><div class="p-2 rounded bg-white shadow-sm fw-semibold text-primary small">Tagihan & VA</div></div>
                                                <div class="col-6"><div class="p-2 rounded bg-white shadow-sm fw-semibold text-primary small">Layanan Surat</div></div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            @elseif($activeRoleTab === 'lecturer')
                                <div class="row align-items-center g-4">
                                    <div class="col-lg-6">
                                        <h4 class="fw-bolder text-body mb-3"><i class="fas fa-chalkboard-user text-primary me-2"></i>Ruang Kerja Pengajaran Efisien</h4>
                                        <p class="text-muted" style="line-height: 1.7;">
                                            Dosen dapat mengelola jurnal perkuliahan, membuat QR code presensi mahasiswa per sesi, mengunggah modul materi kuliah, membuka ruang kuis/tugas, menilai lembar kerja (grade book), serta menyetujui KRS mahasiswa bimbingan DPA.
                                        </p>
                                        <ul class="list-unstyled d-flex flex-column gap-2 mb-4 text-secondary" style="font-size: .9rem;">
                                            <li><i class="fas fa-check-circle text-success me-2"></i>Generate QR presensi kelas secara dinamis.</li>
                                            <li><i class="fas fa-check-circle text-success me-2"></i>Grade book penilaian & kalkulasi bobot nilai otomatis.</li>
                                            <li><i class="fas fa-check-circle text-success me-2"></i>Persetujuan KRS & bimbingan akademik mahasiswa.</li>
                                        </ul>
                                    </div>
                                    <div class="col-lg-6">
                                        <div class="p-4 rounded-4 bg-info bg-opacity-10 border border-info border-opacity-20">
                                            <div class="d-flex align-items-center gap-3 mb-3">
                                                <span class="avatar avatar-md bg-info text-white rounded-circle"><i class="fas fa-briefcase"></i></span>
                                                <div>
                                                    <div class="fw-bold text-dark mb-0">Fitur Utama Dosen</div>
                                                    <small class="text-muted">Manajemen perkuliahan terstruktur</small>
                                                </div>
                                            </div>
                                            <div class="row g-2 text-center">
                                                <div class="col-6"><div class="p-2 rounded bg-white shadow-sm fw-semibold text-info small">Jurnal & QR Check-in</div></div>
                                                <div class="col-6"><div class="p-2 rounded bg-white shadow-sm fw-semibold text-info small">Grade Book & Nilai</div></div>
                                                <div class="col-6"><div class="p-2 rounded bg-white shadow-sm fw-semibold text-info small">Bimbingan DPA</div></div>
                                                <div class="col-6"><div class="p-2 rounded bg-white shadow-sm fw-semibold text-info small">Review BKD & EDOM</div></div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            @else
                                <div class="row align-items-center g-4">
                                    <div class="col-lg-6">
                                        <h4 class="fw-bolder text-body mb-3"><i class="fas fa-user-shield text-primary me-2"></i>Kontrol Penuh Master Data &amp; Tata Kelola</h4>
                                        <p class="text-muted" style="line-height: 1.7;">
                                            Administrator dan Operator Kampus memiliki kendali penuh berbasis *Spatie Roles & Permissions* (ActivePermission) untuk mengelola master akademik, pembukaan penawaran kelas, konfigurasi sistem billing tagihan, verifikasi pendaftar PMB, hingga publikasi pengumuman.
                                        </p>
                                        <ul class="list-unstyled d-flex flex-column gap-2 mb-4 text-secondary" style="font-size: .9rem;">
                                            <li><i class="fas fa-check-circle text-success me-2"></i>Resource Registry &amp; PowerGrid 6 Table modern.</li>
                                            <li><i class="fas fa-check-circle text-success me-2"></i>Otomasi pembuatan akun &amp; NIM pendaftar PMB.</li>
                                            <li><i class="fas fa-check-circle text-success me-2"></i>Sistem log aktivitas audit &amp; pengaturan sistem.</li>
                                        </ul>
                                    </div>
                                    <div class="col-lg-6">
                                        <div class="p-4 rounded-4 bg-success bg-opacity-10 border border-success border-opacity-20">
                                            <div class="d-flex align-items-center gap-3 mb-3">
                                                <span class="avatar avatar-md bg-success text-white rounded-circle"><i class="fas fa-cogs"></i></span>
                                                <div>
                                                    <div class="fw-bold text-dark mb-0">Fitur Administrator</div>
                                                    <small class="text-muted">Akses penuh pengelolaan institusi</small>
                                                </div>
                                            </div>
                                            <div class="row g-2 text-center">
                                                <div class="col-6"><div class="p-2 rounded bg-white shadow-sm fw-semibold text-success small">Master Data Akademik</div></div>
                                                <div class="col-6"><div class="p-2 rounded bg-white shadow-sm fw-semibold text-success small">Verifikasi PMB</div></div>
                                                <div class="col-6"><div class="p-2 rounded bg-white shadow-sm fw-semibold text-success small">Setup Tagihan & VA</div></div>
                                                <div class="col-6"><div class="p-2 rounded bg-white shadow-sm fw-semibold text-success small">Permissions & Audit</div></div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            @endif
                        </div>
                    </div>
                </div>

                {{-- Latest Announcements Section --}}
                @if(count($latestAnnouncements) > 0)
                    <div class="mb-5">
                        <div class="d-flex align-items-center justify-content-between mb-4">
                            <div class="d-flex align-items-center gap-3">
                                <div class="step-badge" style="background: linear-gradient(135deg, #ef4444, #b91c1c);"><i class="fas fa-bullhorn"></i></div>
                                <div>
                                    <h3 class="fw-bolder text-body mb-0" style="font-size:1.25rem;">Pengumuman Resmi Terkini</h3>
                                    <div class="text-muted" style="font-size:.85rem;">Informasi penting terbaru untuk seluruh civitas akademika.</div>
                                </div>
                            </div>
                            <a href="{{ route('root.publication.announcements') }}" class="btn btn-outline-primary rounded-pill fw-bold">
                                Lihat Semua Pengumuman <i class="fas fa-arrow-right ms-1"></i>
                            </a>
                        </div>

                        <div class="row g-3">
                            @foreach($latestAnnouncements as $ann)
                                <div class="col-lg-4 col-md-6">
                                    <div class="admission-card p-4 rounded-4 border-0 shadow-sm h-100 d-flex flex-column" style="transition: transform .2s ease;" onmouseover="this.style.transform='translateY(-3px)'" onmouseout="this.style.transform='none'">
                                        <div class="d-flex align-items-center justify-content-between mb-2">
                                            <span class="badge bg-primary-lt text-primary fw-semibold" style="font-size: .72rem;">
                                                <i class="fas fa-calendar-day me-1"></i>{{ $ann['published_at'] }}
                                            </span>
                                            @if($ann['is_pinned'])
                                                <span class="badge bg-danger text-white fw-semibold" style="font-size: .68rem;">
                                                    <i class="fas fa-thumbtack me-1"></i>Disematkan
                                                </span>
                                            @endif
                                        </div>
                                        <h5 class="fw-bolder text-body mb-2" style="font-size: .95rem; line-height: 1.4;">{{ $ann['title'] }}</h5>
                                        <p class="text-muted mb-4 flex-fill" style="font-size: .83rem; line-height: 1.6;">{{ $ann['excerpt'] }}</p>
                                        <a href="{{ route('root.publication.announcements') }}" class="text-primary fw-bold text-decoration-none small">
                                            Baca Selengkapnya <i class="fas fa-chevron-right ms-1" style="font-size: .7rem;"></i>
                                        </a>
                                    </div>
                                </div>
                            @endforeach
                        </div>
                    </div>
                @endif

                {{-- Contact & Support CTA --}}
                <div class="admission-card rounded-4 p-5 text-center mt-5" style="background: linear-gradient(135deg, #1e293b, #0f172a);">
                    <div style="width:60px;height:60px;border-radius:20px;background:linear-gradient(135deg,#3b82f6,#1d4ed8);margin:0 auto 1.25rem;display:flex;align-items:center;justify-content:center;font-size:1.5rem;color:#fff;box-shadow:0 8px 24px rgba(59,130,246,.4);">
                        <i class="fas fa-rocket"></i>
                    </div>
                    <h2 class="fw-bolder text-white mb-2">Siap Melangkah Bersama NexaCampus?</h2>
                    <p class="text-white-50 mb-4" style="max-width: 620px; margin: 0 auto; font-size: .95rem; line-height: 1.6;">
                        Akses sistem informasi akademik modern sekarang juga. Daftarkan diri Anda sebagai calon mahasiswa baru atau masuk ke portal akademis institusi.
                    </p>
                    <div class="d-flex justify-content-center gap-3 flex-wrap">
                        <a href="{{ $admissionUrl }}" class="btn btn-warning btn-lg px-5 fw-bold rounded-pill shadow-lg">
                            <i class="fas fa-paper-plane me-2"></i>Daftar PMB Sekarang
                        </a>
                        <a href="{{ $loginUrl }}" class="btn btn-outline-light btn-lg px-4 fw-bold rounded-pill">
                            <i class="fas fa-right-to-bracket me-2"></i>Login Portal Akademik
                        </a>
                    </div>
                </div>

            </div>
        </div>
    </div>
</div>
