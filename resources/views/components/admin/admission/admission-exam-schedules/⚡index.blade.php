<?php

use Livewire\Component;

new class extends Component
{
    public function render()
    {
        return $this->view()->layout('layouts.app', [
            'menus' => 'Admission',
            'pages' => 'Admission Exam Schedules',
        ]);
    }
};
?>

<div class="w-full" style="width: 100% !important">
    <x-alert />

    <div class="card">
        <div class="card-header d-flex justify-content-between align-items-center">
            <div>
                <h3 class="card-title mb-0">Exam & Interview Schedules</h3>
                <small class="text-muted">Manage selection sessions, participants, attendance, and scoring.</small>
            </div>
            @activecan('admission-exam-schedule.create')
                <a href="{{ route('admin.admission.admission-exam-schedules.create') }}" class="btn btn-ghost-primary">
                    <i class="fas fa-plus me-1"></i> Add Schedule
                </a>
            @endactivecan
        </div>
        <div class="card-body">
            <livewire:admission.exam-schedule-table />
        </div>
    </div>
</div>
