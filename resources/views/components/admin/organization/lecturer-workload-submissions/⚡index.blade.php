<?php

use App\Models\Organization\LecturerWorkloadSubmission;
use Livewire\Component;

new class extends Component
{
    public function render()
    {
        return $this->view(['stats' => [
            'total' => LecturerWorkloadSubmission::count(),
            'approval' => LecturerWorkloadSubmission::where('status', 'in_approval')->count(),
            'approved' => LecturerWorkloadSubmission::where('status', 'approved')->count(),
            'avg' => LecturerWorkloadSubmission::avg('total_sks') ?: 0,
        ]])->layout('layouts.app', ['menus' => 'Kepegawaian', 'pages' => 'Review BKD']);
    }
};
?>

<div>
    <x-alert />
    <div class="row mb-3">
        @foreach ([['Total', $stats['total'], 'text-primary'], ['Menunggu Approval', $stats['approval'], 'text-warning'], ['Disetujui', $stats['approved'], 'text-success'], ['Rata-rata SKS', number_format($stats['avg'], 2), 'text-info']] as [$label, $value, $color])
            <div class="col-md-3 mb-3"><div class="card"><div class="card-body"><small class="text-muted">{{ $label }}</small><h2 class="{{ $color }} mb-0">{{ $value }}</h2></div></div></div>
        @endforeach
    </div>
    <div class="card">
        <div class="card-header"><h3 class="card-title mb-0">Review BKD Dosen</h3></div>
        <div class="card-body"><livewire:organization.lecturer-workload-submission-table /></div>
    </div>
</div>
