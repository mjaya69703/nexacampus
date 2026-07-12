<?php

use App\Models\Organization\ApprovalTemplate;
use App\Models\Organization\ApprovalTemplateStep;
use Livewire\Component;

new class extends Component
{
    public function stats(): array
    {
        return [
            'total' => ApprovalTemplate::count(),
            'active' => ApprovalTemplate::where('is_active', true)->count(),
            'modules' => ApprovalTemplate::distinct('module')->count('module'),
            'steps' => ApprovalTemplateStep::count(),
        ];
    }

    public function render()
    {
        return $this->view()->layout('layouts.app', [
            'menus' => 'Kepegawaian',
            'pages' => 'Template Approval',
        ]);
    }
};
?>

<div>
    <x-alert />

    <x-admin.organization.header
        title="Daftar Template Approval"
        description="Atur dan konfigurasi alur persetujuan bertingkat (multi-step approval workflow) untuk seluruh modul kepegawaian dan akademik secara terstruktur."
        icon="diagram-project"
    >
        @activecan('approval-template.create')
            <a href="{{ route('admin.organization.approval-templates.create') }}" class="btn btn-sm btn-light text-success fw-semibold d-inline-flex align-items-center gap-2 shadow-sm rounded-pill px-3 py-2">
                <i class="fa fa-plus-circle"></i> <span>Tambah Template Baru</span>
            </a>
        @endactivecan

        <x-slot:stats>
            <div class="d-flex flex-wrap gap-2 gap-lg-3">
                <div class="bg-white bg-opacity-10 rounded-3 px-3 py-2 d-flex align-items-center gap-2">
                    <i class="fa fa-diagram-project fs-6"></i>
                    <div>
                        <div class="small text-white text-opacity-75">Total Template</div>
                        <div class="fw-bold">{{ number_format($this->stats()['total']) }}</div>
                    </div>
                </div>
                <div class="bg-white bg-opacity-10 rounded-3 px-3 py-2 d-flex align-items-center gap-2">
                    <i class="fa fa-circle-check fs-6"></i>
                    <div>
                        <div class="small text-white text-opacity-75">Template Aktif</div>
                        <div class="fw-bold">{{ number_format($this->stats()['active']) }}</div>
                    </div>
                </div>
                <div class="bg-white bg-opacity-10 rounded-3 px-3 py-2 d-flex align-items-center gap-2">
                    <i class="fa fa-layer-group fs-6"></i>
                    <div>
                        <div class="small text-white text-opacity-75">Modul Terhubung</div>
                        <div class="fw-bold">{{ number_format($this->stats()['modules']) }}</div>
                    </div>
                </div>
                <div class="bg-white bg-opacity-10 rounded-3 px-3 py-2 d-flex align-items-center gap-2">
                    <i class="fa fa-stairs fs-6"></i>
                    <div>
                        <div class="small text-white text-opacity-75">Total Tahapan (Steps)</div>
                        <div class="fw-bold">{{ number_format($this->stats()['steps']) }}</div>
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
                    <h4 class="card-title fw-bold mb-0 text-dark">Konfigurasi Alur Persetujuan</h4>
                    <span class="text-muted small">Gunakan filter tabel untuk mencari template berdasarkan modul, nama, atau status keaktifan.</span>
                </div>
            </div>
        </div>
        <div class="card-body p-3 p-md-4">
            <livewire:organization.approval-template-table />
        </div>
    </div>
</div>
