<?php

use App\Models\Organization\EdomQuestion;
use Livewire\Component;

new class extends Component
{
    public function stats(): array
    {
        return [
            'total' => EdomQuestion::count(),
            'active' => EdomQuestion::where('is_active', true)->count(),
            'scale' => EdomQuestion::where('answer_type', 'scale')->count(),
            'text' => EdomQuestion::where('answer_type', 'text')->count(),
        ];
    }

    public function render()
    {
        return $this->view()->layout('layouts.app', ['menus' => 'Kepegawaian', 'pages' => 'Pertanyaan EDOM']);
    }
};
?>

<div>
    <x-alert />

    <x-admin.organization.header
        title="Daftar Pertanyaan EDOM"
        description="Kelola instrumen kuesioner, kategori penilaian, dan bobot pertanyaan untuk evaluasi kinerja pengajaran dosen oleh mahasiswa."
        icon="circle-question"
    >
        @activecan('edom-question.create')
            <a href="{{ route('admin.organization.edom-questions.create') }}" class="btn btn-sm btn-light text-success fw-semibold d-inline-flex align-items-center gap-2 shadow-sm rounded-pill px-3 py-2">
                <i class="fa fa-plus-circle"></i> <span>Tambah Pertanyaan Baru</span>
            </a>
        @endactivecan

        <x-slot:stats>
            <div class="d-flex flex-wrap gap-2 gap-lg-3">
                <div class="bg-white bg-opacity-10 rounded-3 px-3 py-2 d-flex align-items-center gap-2">
                    <i class="fa fa-list-check fs-6"></i>
                    <div>
                        <div class="small text-white text-opacity-75">Total Pertanyaan</div>
                        <div class="fw-bold">{{ number_format($this->stats()['total']) }}</div>
                    </div>
                </div>
                <div class="bg-white bg-opacity-10 rounded-3 px-3 py-2 d-flex align-items-center gap-2">
                    <i class="fa fa-circle-check fs-6"></i>
                    <div>
                        <div class="small text-white text-opacity-75">Pertanyaan Aktif</div>
                        <div class="fw-bold">{{ number_format($this->stats()['active']) }}</div>
                    </div>
                </div>
                <div class="bg-white bg-opacity-10 rounded-3 px-3 py-2 d-flex align-items-center gap-2">
                    <i class="fa fa-star fs-6"></i>
                    <div>
                        <div class="small text-white text-opacity-75">Tipe Skala (Angka)</div>
                        <div class="fw-bold">{{ number_format($this->stats()['scale']) }}</div>
                    </div>
                </div>
                <div class="bg-white bg-opacity-10 rounded-3 px-3 py-2 d-flex align-items-center gap-2">
                    <i class="fa fa-comment-dots fs-6"></i>
                    <div>
                        <div class="small text-white text-opacity-75">Tipe Teks (Uraian)</div>
                        <div class="fw-bold">{{ number_format($this->stats()['text']) }}</div>
                    </div>
                </div>
            </div>
        </x-slot:stats>
    </x-admin.organization.header>

    <div class="card border-0 shadow-sm rounded-4 overflow-hidden mb-4">
        <div class="card-header bg-white border-bottom p-3 p-md-4 d-flex flex-column flex-md-row gap-2 justify-content-between align-items-md-center">
            <div class="d-flex align-items-center gap-3">
                <div class="bg-primary bg-opacity-10 text-primary rounded-3 d-flex align-items-center justify-content-center" style="width: 40px; height: 40px;">
                    <i class="fa fa-table fs-5"></i>
                </div>
                <div>
                    <h4 class="card-title fw-bold mb-0 text-dark">Bank Pertanyaan Evaluasi</h4>
                    <span class="text-muted small">Gunakan filter tabel untuk mencari pertanyaan berdasarkan kategori, tipe jawaban, atau status keaktifan.</span>
                </div>
            </div>
        </div>
        <div class="card-body p-3 p-md-4">
            <livewire:organization.edom-question-table />
        </div>
    </div>
</div>
