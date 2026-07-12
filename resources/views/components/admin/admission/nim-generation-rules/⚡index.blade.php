<?php

use Livewire\Component;
use App\Models\Admission\NimGenerationRule;
use App\Models\Admission\NimSequenceCounter;

new class extends Component
{
    public function render()
    {
        $totalRules = NimGenerationRule::count();
        $activeRules = NimGenerationRule::where('is_active', true)->count();
        $totalGenerated = NimSequenceCounter::sum('last_number');

        return $this->view([
            'totalRules' => $totalRules,
            'activeRules' => $activeRules,
            'totalGenerated' => $totalGenerated,
        ])->layout('layouts.app', [
            'menus' => 'Admission',
            'pages' => 'Aturan & Pola Pembuatan NIM',
        ]);
    }
};
?>

<div class="w-full" style="width: 100% !important">
    <x-alert />

    <x-admin.admission.header
        title="Aturan & Pola Pembuatan NIM"
        description="Konfigurasi format nomor induk mahasiswa (NIM) otomatis dengan placeholder dinamis seperti tahun, kode fakultas, kode prodi, dan nomor urut."
        icon="cogs"
    >
        @activecan('nim-generation-rule.create')
            <a href="{{ route('admin.admission.nim-generation-rules.create') }}" class="btn btn-light rounded-pill px-4 py-2 text-primary fw-bold shadow-sm d-inline-flex align-items-center gap-2 border-0">
                <i class="fas fa-plus-circle"></i> Tambah Aturan Baru
            </a>
        @endactivecan

        <x-slot:stats>
            <div class="d-flex align-items-center gap-2 px-3 py-2 rounded-3" style="background: rgba(255, 255, 255, 0.12); backdrop-filter: blur(4px); border: 1px solid rgba(255, 255, 255, 0.2);">
                <i class="fas fa-sliders-h text-warning fs-5"></i>
                <div>
                    <div class="text-white text-opacity-75 fs-7 mb-0">Total Aturan</div>
                    <div class="fw-bold fs-5 mb-0">{{ number_format($totalRules) }} <small class="fs-7 fw-normal">Pola</small></div>
                </div>
            </div>
            <div class="d-flex align-items-center gap-2 px-3 py-2 rounded-3" style="background: rgba(255, 255, 255, 0.12); backdrop-filter: blur(4px); border: 1px solid rgba(255, 255, 255, 0.2);">
                <i class="fas fa-check-circle text-success fs-5"></i>
                <div>
                    <div class="text-white text-opacity-75 fs-7 mb-0">Aturan Aktif</div>
                    <div class="fw-bold fs-5 mb-0">{{ number_format($activeRules) }} <small class="fs-7 fw-normal">Format Utama</small></div>
                </div>
            </div>
            <div class="d-flex align-items-center gap-2 px-3 py-2 rounded-3" style="background: rgba(255, 255, 255, 0.12); backdrop-filter: blur(4px); border: 1px solid rgba(255, 255, 255, 0.2);">
                <i class="fas fa-sort-numeric-up text-info fs-5"></i>
                <div>
                    <div class="text-white text-opacity-75 fs-7 mb-0">NIM Tergenerate</div>
                    <div class="fw-bold fs-5 mb-0">{{ number_format((int) $totalGenerated) }} <small class="fs-7 fw-normal">Mahasiswa</small></div>
                </div>
            </div>
        </x-slot:stats>
    </x-admin.admission.header>

    <div class="card border-0 shadow-sm rounded-4 overflow-hidden mb-4">
        <div class="card-header bg-white border-bottom p-4 d-flex justify-content-between align-items-center flex-wrap gap-2">
            <div>
                <h4 class="fw-bold mb-1 text-dark d-flex align-items-center gap-2">
                    <i class="fas fa-list-ol text-primary"></i> Daftar Aturan & Pola NIM
                </h4>
                <p class="text-muted fs-7 mb-0">Aktifkan atau nonaktifkan aturan generator NIM serta periksa counter urutan per ruang lingkup (Scope).</p>
            </div>
        </div>
        <div class="card-body p-4">
            <livewire:admission.nim-generation-rule-table />
        </div>
    </div>
</div>
