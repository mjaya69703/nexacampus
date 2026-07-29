<?php

use Livewire\Component;
use App\Models\Admission\AdmissionPeriod;
use App\Models\Academic\StudyProgram;

new class extends Component
{
    public array $periods = [];
    public array $generalRequirements = [];
    public int $openPeriods = 0;
    public int $totalPrograms = 0;

    public function mount(): void
    {
        $this->totalPrograms = StudyProgram::where('is_active', true)->count();
        $this->openPeriods   = AdmissionPeriod::where('is_active', true)->where('is_published', true)->count();

        // General requirements shown regardless of DB data
        $this->generalRequirements = [
            ['icon' => 'fa-id-card',      'color' => '#3b82f6', 'bg' => 'rgba(59,130,246,.1)',  'label' => 'Kartu Identitas',          'desc' => 'KTP / Kartu Pelajar / Akta Kelahiran yang masih berlaku.'],
            ['icon' => 'fa-graduation-cap','color' => '#8b5cf6','bg' => 'rgba(139,92,246,.1)',  'label' => 'Ijazah / SKL',             'desc' => 'Scan Ijazah asli atau Surat Keterangan Lulus (SKL) dari sekolah.'],
            ['icon' => 'fa-book-open',    'color' => '#10b981', 'bg' => 'rgba(16,185,129,.1)',  'label' => 'Nilai Rapor',              'desc' => 'Scan nilai rapor semester 1 hingga 5 yang dilegalisir.'],
            ['icon' => 'fa-camera',       'color' => '#f59e0b', 'bg' => 'rgba(245,158,11,.1)',  'label' => 'Pas Foto Terbaru',         'desc' => 'Pas foto berwarna latar merah/biru ukuran 3×4.'],
            ['icon' => 'fa-home',         'color' => '#ef4444', 'bg' => 'rgba(239,68,68,.1)',   'label' => 'Kartu Keluarga',           'desc' => 'Scan kartu keluarga (KK) yang masih berlaku.'],
            ['icon' => 'fa-phone',        'color' => '#06b6d4', 'bg' => 'rgba(6,182,212,.1)',   'label' => 'Nomor Aktif & Email',      'desc' => 'Nomor HP dan email aktif yang dapat dihubungi.'],
        ];

        // DB-driven periods
        $this->periods = AdmissionPeriod::with(['documentRequirements' => fn($q) => $q->orderBy('sort_order')])
            ->where('is_published', true)
            ->get()
            ->map(fn ($p) => [
                'id'          => $p->id,
                'name'        => $p->name,
                'code'        => $p->code,
                'description' => $p->description,
                'opens_at'    => $p->opens_at?->format('d M Y'),
                'closes_at'   => $p->closes_at?->format('d M Y'),
                'is_active'   => $p->is_active,
                'requirements' => $p->documentRequirements->map(fn($r) => [
                    'label'       => $r->label,
                    'is_required' => $r->is_required,
                ])->toArray(),
            ])
            ->toArray();
    }

    public function render()
    {
        return $this->view()->layout('layouts.home', [
            'menus' => 'Admission',
            'pages' => 'Jalur Masuk & Syarat',
        ]);
    }
};
?>

<div class="admission-public">
    <div class="container-xl py-4 py-lg-5">
        <div class="row justify-content-center">
            <div class="col-12">

                {{-- Hero --}}
                <div class="admission-hero mb-5">
                    <div class="row align-items-center g-4">
                        <div class="col-lg-7">
                            <div class="admission-kicker d-flex align-items-center gap-2 mb-2">
                                <span class="badge-pulse"></span>
                                <span>Penerimaan Mahasiswa Baru</span>
                            </div>
                            <h1 class="admission-title mb-3">Jalur Masuk &<br><span style="opacity:.8">Syarat Pendaftaran</span></h1>
                            <p class="admission-subtitle mb-4">
                                Pilih jalur masuk yang paling sesuai dengan profil dan pencapaian Anda. Siapkan berkas persyaratan dan mulai perjalanan akademik terbaik Anda.
                            </p>
                            <div class="d-flex flex-wrap gap-3">
                                <a href="{{ route('root.admission.apply') }}" class="btn btn-light px-4 fw-bold shadow-sm d-flex align-items-center gap-2">
                                    <i class="fas fa-paper-plane text-primary"></i> Daftar Sekarang
                                </a>
                                <a href="{{ route('root.admission.tuition') }}" class="btn btn-outline-light px-4 fw-bold d-flex align-items-center gap-2">
                                    <i class="fas fa-coins"></i> Lihat Biaya Pendidikan
                                </a>
                            </div>
                        </div>
                        <div class="col-lg-5">
                            <div class="admission-hero-panel shadow">
                                <div class="d-flex justify-content-between align-items-start mb-3 pb-2 border-bottom border-light border-opacity-10">
                                    <div>
                                        <div class="text-white-50 small fw-bold text-uppercase">Status Penerimaan</div>
                                        <div class="h3 text-white mb-0 fw-bolder">{{ $openPeriods > 0 ? 'Sedang Dibuka' : 'Belum Dibuka' }}</div>
                                    </div>
                                    <span class="badge {{ $openPeriods > 0 ? 'bg-success' : 'bg-secondary' }} px-3 py-2 rounded-pill fw-bold shadow-sm">
                                        {{ $openPeriods > 0 ? 'Buka' : 'Tutup' }}
                                    </span>
                                </div>
                                <div class="row g-3">
                                    <div class="col-6">
                                        <div class="hero-mini-stat text-center">
                                            <span class="text-white">{{ $openPeriods }}</span>
                                            <small class="text-white-50">Gelombang Aktif</small>
                                        </div>
                                    </div>
                                    <div class="col-6">
                                        <div class="hero-mini-stat text-center">
                                            <span class="text-white">{{ $totalPrograms }}</span>
                                            <small class="text-white-50">Program Studi</small>
                                        </div>
                                    </div>
                                </div>
                                <div class="mt-3 pt-2 border-top border-light border-opacity-10">
                                    <div class="d-flex align-items-center gap-2">
                                        <i class="fas fa-clock text-white-50"></i>
                                        <small class="text-white-50">Pendaftaran online 24 jam setiap harinya</small>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                {{-- How to Apply Steps --}}
                <div class="mb-5">
                    <div class="text-center mb-4">
                        <span class="badge bg-primary-lt text-primary fw-bold px-3 py-2 mb-2">Alur Pendaftaran</span>
                        <h2 class="fw-bolder text-body mb-1" style="font-size:1.5rem;">Cara Mendaftar dalam 4 Langkah Mudah</h2>
                        <p class="text-muted">Proses pendaftaran online yang cepat, mudah, dan bisa dilakukan dari mana saja.</p>
                    </div>
                    <div class="row g-3">
                        @foreach([
                            ['step'=>'01','icon'=>'fa-user-plus','title'=>'Isi Data Diri','desc'=>'Lengkapi formulir biodata, pilih program studi, dan masukkan data pendidikan terakhir Anda.','color'=>'#3b82f6'],
                            ['step'=>'02','icon'=>'fa-file-arrow-up','title'=>'Upload Berkas','desc'=>'Unggah dokumen persyaratan sesuai jalur masuk yang Anda pilih dalam format yang ditentukan.','color'=>'#8b5cf6'],
                            ['step'=>'03','icon'=>'fa-paper-plane','title'=>'Submit Formulir','desc'=>'Periksa kembali seluruh isian, lalu kirim formulir pendaftaran. Nomor registrasi dikirim via email.','color'=>'#10b981'],
                            ['step'=>'04','icon'=>'fa-bell','title'=>'Pantau Status','desc'=>'Gunakan nomor registrasi & email untuk memantau status seleksi secara real-time di portal kami.','color'=>'#f59e0b'],
                        ] as $s)
                        <div class="col-lg-3 col-sm-6">
                            <div class="admission-card p-4 rounded-4 border-0 shadow-sm h-100 text-center" style="transition:transform .2s ease;" onmouseover="this.style.transform='translateY(-4px)'" onmouseout="this.style.transform='none'">
                                <div style="width:56px;height:56px;border-radius:18px;background:{{ $s['color'] }};margin:0 auto 1rem;display:flex;align-items:center;justify-content:center;font-size:1.3rem;color:#fff;box-shadow:0 8px 20px {{ $s['color'] }}40;">
                                    <i class="fas {{ $s['icon'] }}"></i>
                                </div>
                                <div class="fw-black text-muted mb-1" style="font-size:.7rem;letter-spacing:.12em;">LANGKAH {{ $s['step'] }}</div>
                                <div class="fw-bolder text-body mb-2">{{ $s['title'] }}</div>
                                <div class="text-muted" style="font-size:.85rem;">{{ $s['desc'] }}</div>
                            </div>
                        </div>
                        @endforeach
                    </div>
                </div>

                {{-- General Requirements --}}
                <div class="mb-5">
                    <div class="d-flex align-items-center gap-3 mb-4">
                        <div class="step-badge"><i class="fas fa-file-check"></i></div>
                        <div>
                            <h3 class="fw-bolder text-body mb-0" style="font-size:1.2rem;">Persyaratan Umum Semua Jalur</h3>
                            <div class="text-muted" style="font-size:.85rem;">Dokumen yang wajib disiapkan oleh seluruh calon mahasiswa</div>
                        </div>
                    </div>
                    <div class="row g-3">
                        @foreach($generalRequirements as $req)
                        <div class="col-lg-4 col-sm-6">
                            <div class="admission-card p-3 rounded-3 border-0 shadow-sm d-flex align-items-start gap-3">
                                <div style="width:44px;height:44px;border-radius:12px;background:{{ $req['bg'] }};color:{{ $req['color'] }};display:flex;align-items:center;justify-content:center;font-size:1.1rem;flex-shrink:0;">
                                    <i class="fas {{ $req['icon'] }}"></i>
                                </div>
                                <div>
                                    <div class="fw-bold text-body" style="font-size:.9rem;">{{ $req['label'] }}</div>
                                    <div class="text-muted" style="font-size:.8rem;">{{ $req['desc'] }}</div>
                                </div>
                            </div>
                        </div>
                        @endforeach
                    </div>
                </div>

                {{-- DB-driven Periods --}}
                @if(count($periods) > 0)
                    <div class="mb-4">
                        <div class="d-flex align-items-center gap-3 mb-4">
                            <div class="step-badge" style="background:linear-gradient(135deg,#8b5cf6,#7c3aed)"><i class="fas fa-road"></i></div>
                            <div>
                                <h3 class="fw-bolder text-body mb-0" style="font-size:1.2rem;">Gelombang & Persyaratan Khusus</h3>
                                <div class="text-muted" style="font-size:.85rem;">Dokumen tambahan sesuai gelombang penerimaan yang dipilih</div>
                            </div>
                        </div>
                        <div class="row g-4">
                            @php
                                $periodColors = ['#3b82f6','#8b5cf6','#10b981','#f59e0b','#ef4444'];
                            @endphp
                            @foreach($periods as $pi => $period)
                            <div class="col-lg-4">
                                <div class="admission-card border-0 rounded-4 shadow-sm overflow-hidden h-100">
                                    <div class="p-4 border-bottom" style="background:{{ $periodColors[$pi % count($periodColors)] }}18;">
                                        <div class="d-flex align-items-center justify-content-between mb-2">
                                            <span class="badge fw-bold" style="background:{{ $periodColors[$pi % count($periodColors)] }};color:#fff;">{{ $period['code'] }}</span>
                                            @if($period['is_active'])
                                                <span class="badge bg-success-lt text-success">Aktif</span>
                                            @endif
                                        </div>
                                        <h4 class="fw-bolder text-body mb-1" style="font-size:1rem;">{{ $period['name'] }}</h4>
                                        @if($period['opens_at'])
                                            <div class="text-muted" style="font-size:.78rem;"><i class="fas fa-calendar-days me-1"></i>{{ $period['opens_at'] }} – {{ $period['closes_at'] }}</div>
                                        @endif
                                    </div>
                                    <div class="p-4">
                                        @if($period['description'])
                                            <p class="text-muted mb-3" style="font-size:.85rem;">{{ $period['description'] }}</p>
                                        @endif
                                        @if(count($period['requirements']) > 0)
                                            <div class="fw-bold text-body mb-2" style="font-size:.82rem;text-transform:uppercase;letter-spacing:.06em;">Dokumen Tambahan:</div>
                                            <div class="d-flex flex-column gap-2">
                                                @foreach($period['requirements'] as $req)
                                                    <div class="d-flex align-items-center gap-2">
                                                        <div style="width:20px;height:20px;border-radius:6px;background:{{ $req['is_required'] ? '#dcfce7' : '#fff7ed' }};color:{{ $req['is_required'] ? '#16a34a' : '#ea580c' }};display:flex;align-items:center;justify-content:center;font-size:.65rem;flex-shrink:0;">
                                                            <i class="fas {{ $req['is_required'] ? 'fa-check' : 'fa-asterisk' }}"></i>
                                                        </div>
                                                        <span class="text-body" style="font-size:.85rem;">{{ $req['label'] }}</span>
                                                    </div>
                                                @endforeach
                                            </div>
                                        @else
                                            <div class="text-muted fst-italic" style="font-size:.83rem;">Tidak ada persyaratan dokumen tambahan di luar berkas umum.</div>
                                        @endif
                                    </div>
                                </div>
                            </div>
                            @endforeach
                        </div>
                    </div>
                @endif

                {{-- CTA --}}
                <div class="rounded-4 p-5 text-center" style="background:linear-gradient(135deg,#1e293b,#0f172a);">
                    <h3 class="fw-bolder text-white mb-2">Siap untuk Mendaftar?</h3>
                    <p class="text-white-50 mb-4">Jangan tunda lagi. Daftarkan diri sekarang dan raih peluang akademik terbaik Anda.</p>
                    <div class="d-flex justify-content-center gap-3 flex-wrap">
                        <a href="{{ route('root.admission.apply') }}" class="btn btn-primary btn-lg px-5 fw-bold shadow">
                            <i class="fas fa-paper-plane me-2"></i>Daftar Sekarang
                        </a>
                        <a href="{{ route('root.admission.faq') }}" class="btn btn-outline-light btn-lg px-4 fw-bold">
                            <i class="fas fa-circle-question me-2"></i>FAQ
                        </a>
                    </div>
                </div>

            </div>
        </div>
    </div>
</div>