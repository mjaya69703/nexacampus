<?php

use App\Enums\FaqType;
use App\Models\Publication\Faq;
use Livewire\Component;

new class extends Component
{
    public string $search = '';
    public ?string $selectedCategory = null;

    public function filterCategory(?string $category): void
    {
        $this->selectedCategory = $this->selectedCategory === $category ? null : $category;
    }

    public function render()
    {
        $categoryConfig = [
            'Pendaftaran' => ['icon' => 'fa-file-signature', 'color' => '#3b82f6'],
            'Biaya'       => ['icon' => 'fa-coins',          'color' => '#10b981'],
            'Seleksi'     => ['icon' => 'fa-magnifying-glass','color' => '#8b5cf6'],
            'Dokumen'     => ['icon' => 'fa-folder-open',    'color' => '#f59e0b'],
        ];

        $query = Faq::where('is_active', true)
            ->where('type', FaqType::ADMISSION->value);

        if ($this->selectedCategory) {
            $query->where('category', $this->selectedCategory);
        }

        if (trim($this->search) !== '') {
            $term = '%' . trim($this->search) . '%';
            $query->where(function ($q) use ($term) {
                $q->where('question', 'like', $term)
                  ->orWhere('answer', 'like', $term)
                  ->orWhere('category', 'like', $term);
            });
        }

        $dbFaqs = $query->orderBy('sort_order')->orderBy('id')->get();

        if ($dbFaqs->isNotEmpty() || trim($this->search) !== '' || $this->selectedCategory) {
            $faqs = $dbFaqs->map(function ($item) use ($categoryConfig) {
                $config = $categoryConfig[$item->category] ?? ['icon' => 'fa-circle-question', 'color' => '#6b7280'];

                return [
                    'id' => $item->id,
                    'cat' => $item->category,
                    'cat_icon' => $config['icon'],
                    'cat_color' => $config['color'],
                    'question' => $item->question,
                    'answer' => $item->answer,
                ];
            })->toArray();
        } else {
            $faqs = [
                ['id' => 1, 'cat' => 'Pendaftaran', 'cat_icon' => 'fa-file-signature', 'cat_color' => '#3b82f6',
                 'question' => 'Bagaimana cara melakukan pendaftaran secara online?',
                 'answer'   => 'Akses menu <strong>Daftar Sekarang</strong> di bagian atas halaman ini. Isi formulir biodata awal, lalu sistem akan mengirimkan <strong>Nomor Pendaftaran</strong> dan password sementara ke email yang Anda daftarkan. Gunakan kredensial tersebut untuk masuk ke Portal Pendaftar dan melengkapi sisa persyaratan secara bertahap.'],
                ['id' => 2, 'cat' => 'Pendaftaran', 'cat_icon' => 'fa-file-signature', 'cat_color' => '#3b82f6',
                 'question' => 'Apakah saya bisa mendaftar lebih dari satu program studi?',
                 'answer'   => 'Ya, setiap calon mahasiswa diperbolehkan memilih <strong>maksimal 3 (tiga) pilihan program studi</strong> sesuai skala prioritas. Proses seleksi dimulai dari pilihan pertama, dan apabila tidak lolos akan dipertimbangkan ke pilihan berikutnya sesuai ketersediaan kuota.'],
                ['id' => 3, 'cat' => 'Biaya',       'cat_icon' => 'fa-coins',          'cat_color' => '#10b981',
                 'question' => 'Berapa biaya pendaftaran PMB NexaCampus?',
                 'answer'   => 'Biaya pendaftaran adalah sebesar <strong>Rp 250.000,-</strong> untuk semua jalur masuk. Pembayaran dapat dilakukan melalui Virtual Account Bank yang bekerja sama dengan kampus. Biaya pendaftaran <strong>tidak dapat dikembalikan</strong> setelah berhasil dibayar, namun Anda bisa langsung mengakses portal pendaftaran.'],
                ['id' => 4, 'cat' => 'Seleksi',     'cat_icon' => 'fa-magnifying-glass','cat_color' => '#8b5cf6',
                 'question' => 'Kapan pengumuman hasil seleksi diumumkan?',
                 'answer'   => 'Pengumuman hasil seleksi diumumkan <strong>7 hari kerja</strong> setelah masa pendaftaran gelombang tersebut ditutup, atau <strong>3 hari kerja</strong> pasca pelaksanaan tes mandiri (CBT) di kampus. Anda dapat memantau status pendaftaran secara real-time melalui menu <strong>Cek Status Pendaftaran</strong>.'],
                ['id' => 5, 'cat' => 'Dokumen',     'cat_icon' => 'fa-folder-open',    'cat_color' => '#f59e0b',
                 'question' => 'Dokumen apa saja yang wajib disiapkan?',
                 'answer'   => 'Dokumen umum yang wajib disiapkan oleh semua jalur antara lain: <ul class="mt-2 ps-3"><li>Scan Ijazah/SKL yang dilegalisir</li><li>Scan Nilai Rapor Semester 1–5</li><li>Pas foto berwarna terbaru (3×4, latar merah/biru)</li><li>Fotokopi KTP/Kartu Pelajar</li><li>Scan Kartu Keluarga</li></ul>Persyaratan tambahan bergantung pada jalur masuk yang Anda pilih.'],
                ['id' => 6, 'cat' => 'Seleksi',     'cat_icon' => 'fa-magnifying-glass','cat_color' => '#8b5cf6',
                 'question' => 'Apakah ada tes tulis / tes masuk?',
                 'answer'   => 'Tergantung jalur masuk yang Anda pilih. Jalur <strong>Prestasi</strong> umumnya tidak memerlukan tes tulis karena seleksi berbasis nilai rapor dan sertifikat prestasi. Jalur <strong>Ujian Tulis</strong> mengharuskan Anda mengikuti Computer Based Test (CBT) di kampus pada jadwal yang ditentukan.'],
                ['id' => 7, 'cat' => 'Biaya',       'cat_icon' => 'fa-coins',          'cat_color' => '#10b981',
                 'question' => 'Apakah ada beasiswa atau keringanan biaya?',
                 'answer'   => 'Ya! Tersedia beberapa skema bantuan biaya pendidikan, di antaranya: <ul class="mt-2 ps-3"><li><strong>KIP Kuliah</strong> — pembebasan biaya penuh dari pemerintah</li><li><strong>Beasiswa Prestasi</strong> — untuk mahasiswa berprestasi akademik/non-akademik</li><li><strong>Cicilan</strong> — program cicilan pembayaran UKT untuk kesulitan ekonomi</li></ul>'],
                ['id' => 8, 'cat' => 'Pendaftaran', 'cat_icon' => 'fa-file-signature', 'cat_color' => '#3b82f6',
                 'question' => 'Berapa lama proses verifikasi berkas berlangsung?',
                 'answer'   => 'Proses verifikasi berkas umumnya berlangsung <strong>3–5 hari kerja</strong> setelah semua dokumen diterima secara lengkap. Anda akan mendapatkan notifikasi email jika terdapat kekurangan berkas atau jika verifikasi telah selesai.'],
            ];
        }

        $allAdmissionCategories = Faq::where('is_active', true)
            ->where('type', FaqType::ADMISSION->value)
            ->distinct()
            ->pluck('category')
            ->toArray();

        if (empty($allAdmissionCategories)) {
            $allAdmissionCategories = ['Pendaftaran', 'Biaya', 'Seleksi', 'Dokumen'];
        }

        return $this->view([
            'faqs' => $faqs,
            'allCategories' => $allAdmissionCategories,
        ])->layout('layouts.home', [
            'menus' => 'Admission',
            'pages' => 'FAQ Penerimaan',
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
                                <span>Pusat Bantuan Pendaftaran</span>
                            </div>
                            <h1 class="admission-title mb-3">Pertanyaan yang<br><span style="opacity:.8">Sering Ditanyakan</span></h1>
                            <p class="admission-subtitle mb-4">
                                Temukan jawaban atas semua pertanyaan seputar proses penerimaan mahasiswa baru. Tidak menemukan jawaban? Hubungi tim kami langsung.
                            </p>

                            {{-- Search Input Bar --}}
                            <div class="position-relative mb-4" style="max-width: 500px;">
                                <i class="fas fa-search position-absolute top-50 start-0 translate-middle-y ms-3 text-white-50"></i>
                                <input type="text"
                                    class="form-control ps-5 pe-4 rounded-pill shadow-sm border-0 bg-white bg-opacity-20 text-white placeholder-white-50"
                                    placeholder="Cari pertanyaan PMB..."
                                    wire:model.live.debounce.300ms="search">
                                @if($search)
                                    <button class="btn btn-sm btn-link text-white-50 position-absolute top-50 end-0 translate-middle-y me-2" wire:click="$set('search', '')">
                                        <i class="fas fa-times-circle"></i>
                                    </button>
                                @endif
                            </div>

                            <div class="d-flex flex-wrap gap-3">
                                <a href="{{ route('root.admission.apply') }}" class="btn btn-light px-4 fw-bold shadow-sm d-flex align-items-center gap-2">
                                    <i class="fas fa-paper-plane text-primary"></i> Daftar Sekarang
                                </a>
                                <a href="{{ route('root.admission.status') }}" class="btn btn-outline-light px-4 fw-bold d-flex align-items-center gap-2">
                                    <i class="fas fa-magnifying-glass"></i> Cek Status
                                </a>
                            </div>
                        </div>
                        <div class="col-lg-5">
                            <div class="admission-hero-panel shadow">
                                <div class="d-flex justify-content-between align-items-start mb-3 pb-2 border-bottom border-light border-opacity-10">
                                    <div>
                                        <div class="text-white-50 small fw-bold text-uppercase">Total FAQ PMB</div>
                                        <div class="h3 text-white mb-0 fw-bolder">{{ count($faqs) }} Pertanyaan</div>
                                    </div>
                                    <span class="badge bg-white text-primary px-3 py-2 rounded-pill fw-bold shadow-sm">Diperbarui</span>
                                </div>
                                <div class="row g-3">
                                    @php $cats = collect($faqs)->unique('cat')->pluck('cat')->values(); @endphp
                                    <div class="col-6">
                                        <div class="hero-mini-stat text-center">
                                            <span class="text-white">{{ $cats->count() }}</span>
                                            <small class="text-white-50">Kategori</small>
                                        </div>
                                    </div>
                                    <div class="col-6">
                                        <div class="hero-mini-stat text-center">
                                            <span class="text-white" style="font-size:1.3rem;">24/7</span>
                                            <small class="text-white-50">Akses Info</small>
                                        </div>
                                    </div>
                                </div>
                                <div class="mt-3 pt-2 border-top border-light border-opacity-10">
                                    <div class="d-flex align-items-center gap-2">
                                        <i class="fas fa-headset text-white-50"></i>
                                        <small class="text-white-50">Butuh bantuan lebih? Tim kami siap membantu.</small>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                {{-- Category quick-nav --}}
                @php
                    $groupedFaqs = collect($faqs)->groupBy('cat');
                @endphp
                <div class="d-flex flex-wrap align-items-center gap-2 mb-4">
                    <small class="fw-bold text-muted me-2"><i class="fas fa-filter me-1"></i>Filter Kategori:</small>
                    @foreach($allCategories as $catName)
                        @php
                            $catFaqCount = collect($faqs)->where('cat', $catName)->count();
                        @endphp
                        <button type="button"
                            class="btn btn-sm rounded-pill px-3 fw-semibold {{ $selectedCategory === $catName ? 'btn-primary' : 'btn-outline-primary' }}"
                            wire:click="filterCategory('{{ $catName }}')">
                            {{ $catName }}
                            @if($catFaqCount > 0)
                                <span class="badge rounded-pill ms-1 {{ $selectedCategory === $catName ? 'bg-white text-primary' : 'bg-primary text-white' }}">{{ $catFaqCount }}</span>
                            @endif
                        </button>
                    @endforeach
                    @if($selectedCategory || $search)
                        <button type="button" class="btn btn-sm btn-link text-danger text-decoration-none" wire:click="$set('selectedCategory', null); $set('search', '');">
                            <i class="fas fa-times me-1"></i>Reset Filter
                        </button>
                    @endif
                </div>

                {{-- FAQ by category --}}
                @if(empty($faqs))
                    <div class="card border-0 rounded-4 shadow-sm text-center p-5 my-4">
                        <div class="py-4">
                            <i class="fas fa-circle-question fs-1 text-muted opacity-50 mb-3 d-block"></i>
                            <h4 class="fw-bold text-dark">Pertanyaan Tidak Ditemukan</h4>
                            <p class="text-muted">Tidak ada FAQ PMB yang sesuai dengan kriteria pencarian Anda.</p>
                            <button class="btn btn-primary rounded-pill px-4" wire:click="$set('search', ''); $set('selectedCategory', null);">
                                Reset Pencarian
                            </button>
                        </div>
                    </div>
                @else
                    <div class="row g-4">
                        @foreach($groupedFaqs as $catName => $items)
                            @php $item = $items->first(); @endphp
                            <div class="col-12" id="cat-{{ Str::slug($catName) }}">
                                <div class="d-flex align-items-center gap-3 mb-3">
                                    <div style="width:38px;height:38px;border-radius:12px;background:{{ $item['cat_color'] ?? '#3b82f6' }};display:flex;align-items:center;justify-content:center;color:#fff;font-size:.95rem;flex-shrink:0;">
                                        <i class="fas {{ $item['cat_icon'] ?? 'fa-circle-question' }}"></i>
                                    </div>
                                    <h3 class="fw-bolder text-body mb-0" style="font-size:1.05rem;">{{ $catName }}</h3>
                                    <div class="flex-fill border-bottom border-2 ms-1 opacity-25"></div>
                                </div>

                                <div class="accordion" id="faqCat{{ Str::studly($catName) }}">
                                    @foreach($items as $idx => $faq)
                                        <div class="admission-card border-0 rounded-3 shadow-sm mb-2 overflow-hidden">
                                            <div class="accordion-item bg-transparent border-0">
                                                <h4 class="accordion-header m-0">
                                                    <button class="accordion-button bg-transparent fw-semibold text-body {{ $idx !== 0 ? 'collapsed' : '' }} shadow-none py-3 px-4" type="button"
                                                        data-bs-toggle="collapse" data-bs-target="#faqItem{{ $faq['id'] }}"
                                                        aria-expanded="{{ $idx === 0 ? 'true' : 'false' }}" aria-controls="faqItem{{ $faq['id'] }}"
                                                        style="font-size:.92rem;">
                                                        <i class="fas fa-circle-question me-2 text-muted" style="font-size:.8rem;"></i>
                                                        {{ $faq['question'] }}
                                                    </button>
                                                </h4>
                                                <div id="faqItem{{ $faq['id'] }}" class="accordion-collapse collapse {{ $idx === 0 ? 'show' : '' }}" data-bs-parent="#faqCat{{ Str::studly($catName) }}">
                                                    <div class="accordion-body pt-0 pb-4 px-4 text-muted" style="font-size:.88rem;line-height:1.7;border-top:1px solid var(--tblr-border-color);">
                                                        <div class="pt-3">{!! $faq['answer'] !!}</div>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    @endforeach
                                </div>
                            </div>
                        @endforeach
                    </div>
                @endif

                {{-- Still need help --}}
                <div class="rounded-4 p-5 text-center mt-5" style="background:linear-gradient(135deg,#1e293b,#0f172a);">
                    <div style="width:60px;height:60px;border-radius:20px;background:linear-gradient(135deg,#3b82f6,#1d4ed8);margin:0 auto 1.25rem;display:flex;align-items:center;justify-content:center;font-size:1.5rem;color:#fff;box-shadow:0 8px 24px rgba(59,130,246,.4);">
                        <i class="fas fa-headset"></i>
                    </div>
                    <h3 class="fw-bolder text-white mb-2">Masih Ada Pertanyaan?</h3>
                    <p class="text-white-50 mb-4">Tim penerimaan kami siap membantu Anda melalui berbagai saluran komunikasi yang tersedia.</p>
                    <a href="{{ route('root.admission.apply') }}" class="btn btn-primary btn-lg px-5 fw-bold shadow">
                        <i class="fas fa-paper-plane me-2"></i>Mulai Pendaftaran
                    </a>
                </div>

            </div>
        </div>
    </div>
</div>