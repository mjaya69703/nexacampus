<?php

use App\Models\Organization\ApprovalRequest;
use Livewire\Component;

new class extends Component
{
    public function stats(): array
    {
        return [
            'total' => ApprovalRequest::count(),
            'in_progress' => ApprovalRequest::where('status', 'in_progress')->count(),
            'approved' => ApprovalRequest::where('status', 'approved')->count(),
            'rejected' => ApprovalRequest::where('status', 'rejected')->count(),
        ];
    }

    public function render()
    {
        return $this->view()->layout('layouts.app', [
            'menus' => 'Kepegawaian',
            'pages' => 'Approval',
        ]);
    }
};
?>

<div>
    <x-alert />

    <x-admin.organization.header
        title="Daftar Permohonan Approval"
        description="Monitor, tinjau, dan proses seluruh alur permohonan approval yang berjalan di dalam sistem kepegawaian secara terpusat."
        icon="clipboard-check"
    >
        @activecan('approval-request.create')
            <a href="{{ route('admin.organization.approval-requests.create') }}" class="btn btn-sm btn-light text-success fw-semibold d-inline-flex align-items-center gap-2 shadow-sm rounded-pill px-3 py-2">
                <i class="fa fa-plus-circle"></i> <span>Buat Permohonan Baru</span>
            </a>
        @endactivecan

        <x-slot:stats>
            <div class="d-flex flex-wrap gap-2 gap-lg-3">
                <div class="bg-white bg-opacity-10 rounded-3 px-3 py-2 d-flex align-items-center gap-2">
                    <i class="fa fa-clipboard-list fs-6"></i>
                    <div>
                        <div class="small text-white text-opacity-75">Total Pengajuan</div>
                        <div class="fw-bold">{{ number_format($this->stats()['total']) }}</div>
                    </div>
                </div>
                <div class="bg-white bg-opacity-10 rounded-3 px-3 py-2 d-flex align-items-center gap-2">
                    <i class="fa fa-clock fs-6"></i>
                    <div>
                        <div class="small text-white text-opacity-75">In Progress</div>
                        <div class="fw-bold">{{ number_format($this->stats()['in_progress']) }}</div>
                    </div>
                </div>
                <div class="bg-white bg-opacity-10 rounded-3 px-3 py-2 d-flex align-items-center gap-2">
                    <i class="fa fa-circle-check fs-6"></i>
                    <div>
                        <div class="small text-white text-opacity-75">Disetujui (Approved)</div>
                        <div class="fw-bold">{{ number_format($this->stats()['approved']) }}</div>
                    </div>
                </div>
                <div class="bg-white bg-opacity-10 rounded-3 px-3 py-2 d-flex align-items-center gap-2">
                    <i class="fa fa-circle-xmark fs-6"></i>
                    <div>
                        <div class="small text-white text-opacity-75">Ditolak (Rejected)</div>
                        <div class="fw-bold">{{ number_format($this->stats()['rejected']) }}</div>
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
                    <h4 class="card-title fw-bold mb-0 text-dark">Data Permohonan</h4>
                    <span class="text-muted small">Gunakan filter tabel di bawah untuk menyeleksi status permohonan, referensi, atau nama pengaju.</span>
                </div>
            </div>
        </div>
        <div class="card-body p-3 p-md-4">
            <livewire:organization.approval-request-table />
        </div>
    </div>
</div>
