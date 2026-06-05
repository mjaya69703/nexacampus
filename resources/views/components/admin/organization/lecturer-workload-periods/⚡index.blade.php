<?php

use App\Models\Organization\LecturerWorkloadPeriod;
use Livewire\Component;

new class extends Component
{
    public function stats(): array
    {
        return [
            'total' => LecturerWorkloadPeriod::count(),
            'open' => LecturerWorkloadPeriod::where('status', 'open')->count(),
            'review' => LecturerWorkloadPeriod::where('status', 'review')->count(),
            'closed' => LecturerWorkloadPeriod::where('status', 'closed')->count(),
        ];
    }

    public function render()
    {
        return $this->view()->layout('layouts.app', ['menus' => 'Kepegawaian', 'pages' => 'Periode BKD']);
    }
};
?>

<div>
    <x-alert />
    <div class="row mb-3">
        @foreach ([['Total', $this->stats()['total'], 'text-primary'], ['Dibuka', $this->stats()['open'], 'text-success'], ['Review', $this->stats()['review'], 'text-warning'], ['Ditutup', $this->stats()['closed'], 'text-secondary']] as [$label, $value, $color])
            <div class="col-md-3 mb-3"><div class="card"><div class="card-body"><small class="text-muted text-uppercase">{{ $label }}</small><h2 class="{{ $color }} mb-0">{{ $value }}</h2></div></div></div>
        @endforeach
    </div>
    <div class="card">
        <div class="card-header d-flex justify-content-between align-items-center">
            <div>
                <h3 class="card-title mb-0">Periode BKD</h3>
                <small class="text-muted">Kelola periode pengajuan dan review beban kerja dosen.</small>
            </div>
            @activecan('lecturer-workload-period.create')
                <a href="{{ route('admin.organization.lecturer-workload-periods.create') }}" class="btn btn-primary"><i class="fas fa-plus me-2"></i>Tambah Periode</a>
            @endactivecan
        </div>
        <div class="card-body">
            <livewire:organization.lecturer-workload-period-table />
        </div>
    </div>
</div>
