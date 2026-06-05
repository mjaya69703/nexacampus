<?php

use App\Models\Organization\LecturerWorkloadPeriod;
use Livewire\Component;

new class extends Component
{
    public LecturerWorkloadPeriod $period;

    public function mount($id): void
    {
        $this->period = LecturerWorkloadPeriod::with(['submissions.owner', 'submissions.items'])->findOrFail($id);
    }

    public function render()
    {
        return $this->view()->layout('layouts.app', ['menus' => 'Kepegawaian', 'pages' => 'Detail Periode BKD']);
    }
};
?>

<div>
    <x-alert />
    <div class="card mb-3">
        <div class="card-header d-flex justify-content-between align-items-center">
            <div>
                <h3 class="card-title mb-0">{{ $period->name }}</h3>
                <small class="text-muted">{{ $period->code }} / {{ $period->starts_at?->format('d M Y') ?? '-' }} - {{ $period->ends_at?->format('d M Y') ?? '-' }}</small>
            </div>
            <a href="{{ route('admin.organization.lecturer-workload-periods.index') }}" class="btn btn-secondary"><i class="fas fa-arrow-left me-2"></i>Kembali</a>
        </div>
    </div>
    <div class="row mb-3">
        <div class="col-md-3"><div class="card"><div class="card-body"><small class="text-muted">Submission</small><h2 class="mb-0">{{ $period->submissions->count() }}</h2></div></div></div>
        <div class="col-md-3"><div class="card"><div class="card-body"><small class="text-muted">Disetujui</small><h2 class="text-success mb-0">{{ $period->submissions->where('status', 'approved')->count() }}</h2></div></div></div>
        <div class="col-md-3"><div class="card"><div class="card-body"><small class="text-muted">Approval</small><h2 class="text-warning mb-0">{{ $period->submissions->where('status', 'in_approval')->count() }}</h2></div></div></div>
        <div class="col-md-3"><div class="card"><div class="card-body"><small class="text-muted">Rata-rata SKS</small><h2 class="text-primary mb-0">{{ number_format($period->submissions->avg('total_sks') ?: 0, 2) }}</h2></div></div></div>
    </div>
    <div class="card">
        <div class="card-header"><h3 class="card-title mb-0">Submission Dosen</h3></div>
        <div class="table-responsive">
            <table class="table card-table">
                <thead><tr><th>Dosen</th><th>Status</th><th>Mengajar</th><th>Jabatan</th><th>Tridharma</th><th>Total</th><th></th></tr></thead>
                <tbody>
                    @forelse ($period->submissions as $submission)
                        <tr>
                            <td>{{ $submission->owner?->name ?? '-' }}</td>
                            <td><span class="badge bg-light text-dark">{{ str($submission->status)->replace('_', ' ')->title() }}</span></td>
                            <td>{{ $submission->teaching_sks }}</td>
                            <td>{{ $submission->structural_sks }}</td>
                            <td>{{ $submission->tridharma_sks }}</td>
                            <td class="fw-bold">{{ $submission->total_sks }}</td>
                            <td><a class="btn btn-primary" href="{{ route('admin.organization.lecturer-workload-submissions.show', $submission->id) }}"><i class="fas fa-eye me-1"></i>Lihat</a></td>
                        </tr>
                    @empty
                        <tr><td colspan="7" class="text-center text-muted py-4">Belum ada submission.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>
