<?php

use App\Models\StudentService\StudentLeaveApplication;
use Livewire\Component;

new class extends Component
{
    public function render()
    {
        return $this->view()->layout('layouts.app', [
            'menus' => 'Student Services',
            'pages' => 'Leave Applications',
        ]);
    }

    public function stats(): array
    {
        return [
            'total' => StudentLeaveApplication::count(),
            'pending' => StudentLeaveApplication::whereIn('status', ['submitted', 'under_review'])->count(),
            'approved' => StudentLeaveApplication::where('status', 'approved')->count(),
            'active' => StudentLeaveApplication::where('status', 'activated')->count(),
        ];
    }
};
?>

<div class="w-full" style="width: 100% !important">
    <x-alert />

    <div class="row g-3 mb-3">
        @foreach ([['label' => 'Applications', 'value' => $this->stats()['total']], ['label' => 'Need Review', 'value' => $this->stats()['pending']], ['label' => 'Approved', 'value' => $this->stats()['approved']], ['label' => 'Active Leave', 'value' => $this->stats()['active']]] as $card)
            <div class="col-md-3">
                <div class="card">
                    <div class="card-body">
                        <div class="text-muted text-uppercase small">{{ $card['label'] }}</div>
                        <div class="h2 mb-0">{{ $card['value'] }}</div>
                    </div>
                </div>
            </div>
        @endforeach
    </div>

    <div class="card">
        <div class="card-header">
            <div>
                <h3 class="card-title mb-0">Leave Applications</h3>
                <small class="text-muted">Review dan kelola pengajuan cuti akademik mahasiswa.</small>
            </div>
        </div>
        <div class="card-body">
            <livewire:student-service.student-leave-application-table />
        </div>
    </div>
</div>
