<?php

use App\Models\Publication\Faq;
use Livewire\Component;

new class extends Component
{
    public int $totalFaqs = 0;
    public int $activeCount = 0;
    public int $admissionCount = 0;
    public int $generalCount = 0;
    public int $academicCount = 0;
    public int $financialCount = 0;

    public function mount(): void
    {
        $this->totalFaqs = Faq::count();
        $this->activeCount = Faq::where('is_active', true)->count();
        $this->admissionCount = Faq::where('type', 'admission')->count();
        $this->generalCount = Faq::where('type', 'general')->count();
        $this->academicCount = Faq::where('type', 'academic')->count();
        $this->financialCount = Faq::where('type', 'financial')->count();
    }

    public function render()
    {
        return $this->view()->layout('layouts.app', [
            'menus' => 'Publication',
            'pages' => 'Daftar FAQ',
        ]);
    }
};
?>

<div class="w-full" style="width: 100% !important">
    <x-alert />

    <x-admin.publication.header
        title="Manajemen FAQ (Frequently Asked Questions)"
        description="Kelola seluruh daftar pertanyaan yang sering ditanyakan per tipe (PMB, Akademik, Keuangan, Layanan Mahasiswa, dan Umum) agar informasi tersampaikan dengan jelas."
        icon="circle-question"
    >
        @activecan('faq.create')
            <a href="{{ route('admin.publication.faqs.create') }}" class="btn  btn-light text-success fw-semibold d-inline-flex align-items-center gap-2 shadow-sm rounded-pill px-3 py-2">
                <i class="fa fa-plus-circle"></i>
                <span>Tambah FAQ</span>
            </a>
        @endactivecan

        <x-slot:stats>
            <div class="d-flex flex-wrap gap-2 gap-lg-3">
                <div class="bg-white bg-opacity-10 rounded-3 px-3 py-2 d-flex align-items-center gap-2">
                    <i class="fa fa-list-check fs-6"></i>
                    <div>
                        <div class="small text-white text-opacity-75">Total FAQ</div>
                        <div class="fw-bold">{{ $totalFaqs }}</div>
                    </div>
                </div>
                <div class="bg-white bg-opacity-10 rounded-3 px-3 py-2 d-flex align-items-center gap-2">
                    <i class="fa fa-user-plus fs-6"></i>
                    <div>
                        <div class="small text-white text-opacity-75">PMB / Admission</div>
                        <div class="fw-bold">{{ $admissionCount }}</div>
                    </div>
                </div>
                <div class="bg-white bg-opacity-10 rounded-3 px-3 py-2 d-flex align-items-center gap-2">
                    <i class="fa fa-graduation-cap fs-6"></i>
                    <div>
                        <div class="small text-white text-opacity-75">Akademik</div>
                        <div class="fw-bold">{{ $academicCount }}</div>
                    </div>
                </div>
                <div class="bg-white bg-opacity-10 rounded-3 px-3 py-2 d-flex align-items-center gap-2">
                    <i class="fa fa-wallet fs-6"></i>
                    <div>
                        <div class="small text-white text-opacity-75">Keuangan</div>
                        <div class="fw-bold">{{ $financialCount }}</div>
                    </div>
                </div>
                <div class="bg-white bg-opacity-10 rounded-3 px-3 py-2 d-flex align-items-center gap-2">
                    <i class="fa fa-check-circle fs-6"></i>
                    <div>
                        <div class="small text-white text-opacity-75">Aktif</div>
                        <div class="fw-bold">{{ $activeCount }}</div>
                    </div>
                </div>
            </div>
        </x-slot:stats>
    </x-admin.publication.header>

    <div class="card border-0 shadow-sm rounded-4 overflow-hidden mb-4">
        <div class="card-header bg-white border-bottom p-3 p-md-4 d-flex flex-column flex-md-row gap-2 justify-content-between align-items-md-center">
            <div>
                <h4 class="card-title fw-bold mb-1 text-dark">Ringkasan FAQ</h4>
                <div class="text-muted small">Statistik singkat untuk memantau sebaran pertanyaan per kategori dan tipe modul.</div>
            </div>
            <div class="text-muted small">
                Gunakan filter dan toggle untuk mengelola FAQ secara cepat.
            </div>
        </div>
        <div class="card-body p-3 p-md-4">
            <div class="row g-3">
                <div class="col-6 col-sm-4 col-xl-2">
                    <div class="border rounded-4 p-3 h-100 bg-light bg-opacity-50">
                        <div class="text-muted small mb-1">Total FAQ</div>
                        <div class="d-flex align-items-end justify-content-between">
                            <div class="fs-2 fw-bold text-dark lh-1">{{ $totalFaqs }}</div>
                            <i class="fa fa-list-check fs-4 text-primary opacity-75"></i>
                        </div>
                    </div>
                </div>
                <div class="col-6 col-sm-4 col-xl-2">
                    <div class="border rounded-4 p-3 h-100 bg-light bg-opacity-50">
                        <div class="text-muted small mb-1">PMB / Admission</div>
                        <div class="d-flex align-items-end justify-content-between">
                            <div class="fs-2 fw-bold text-primary lh-1">{{ $admissionCount }}</div>
                            <i class="fa fa-user-plus fs-4 text-primary opacity-75"></i>
                        </div>
                    </div>
                </div>
                <div class="col-6 col-sm-4 col-xl-2">
                    <div class="border rounded-4 p-3 h-100 bg-light bg-opacity-50">
                        <div class="text-muted small mb-1">Akademik</div>
                        <div class="d-flex align-items-end justify-content-between">
                            <div class="fs-2 fw-bold text-info lh-1">{{ $academicCount }}</div>
                            <i class="fa fa-graduation-cap fs-4 text-info opacity-75"></i>
                        </div>
                    </div>
                </div>
                <div class="col-6 col-sm-4 col-xl-2">
                    <div class="border rounded-4 p-3 h-100 bg-light bg-opacity-50">
                        <div class="text-muted small mb-1">Keuangan</div>
                        <div class="d-flex align-items-end justify-content-between">
                            <div class="fs-2 fw-bold text-success lh-1">{{ $financialCount }}</div>
                            <i class="fa fa-wallet fs-4 text-success opacity-75"></i>
                        </div>
                    </div>
                </div>
                <div class="col-6 col-sm-4 col-xl-2">
                    <div class="border rounded-4 p-3 h-100 bg-light bg-opacity-50">
                        <div class="text-muted small mb-1">Umum</div>
                        <div class="d-flex align-items-end justify-content-between">
                            <div class="fs-2 fw-bold text-secondary lh-1">{{ $generalCount }}</div>
                            <i class="fa fa-info-circle fs-4 text-secondary opacity-75"></i>
                        </div>
                    </div>
                </div>
                <div class="col-6 col-sm-4 col-xl-2">
                    <div class="border rounded-4 p-3 h-100 bg-light bg-opacity-50">
                        <div class="text-muted small mb-1">Aktif</div>
                        <div class="d-flex align-items-end justify-content-between">
                            <div class="fs-2 fw-bold text-success lh-1">{{ $activeCount }}</div>
                            <i class="fa fa-check-circle fs-4 text-success opacity-75"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="card border-0 shadow-sm rounded-4 overflow-hidden">
        <div class="card-header bg-white border-bottom p-3 p-md-4 d-flex flex-column flex-md-row gap-2 justify-content-between align-items-md-center">
            <div class="d-flex align-items-center gap-3">
                <div class="bg-warning bg-opacity-10 text-warning rounded-3 d-flex align-items-center justify-content-center" style="width: 40px; height: 40px;">
                    <i class="fa fa-table fs-5"></i>
                </div>
                <div>
                    <h4 class="card-title fw-bold mb-0 text-dark">Tabel Data FAQ</h4>
                    <span class="text-muted small">Tipe, kategori, pertanyaan, urutan, dan status penayangan FAQ.</span>
                </div>
            </div>
        </div>
        <div class="card-body p-3 p-md-4">
            <livewire:publication.faq-table />
        </div>
    </div>
</div>
