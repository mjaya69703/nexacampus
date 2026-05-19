<?php

use App\Models\Academic\StudyProgram;
use App\Models\Admission\AdmissionApplication;
use App\Models\Admission\AdmissionPeriod;
use App\Support\ActivePermission;
use App\Support\Admission\AdmissionSelectionService;
use App\Support\Admission\AdmissionStatusService;
use Livewire\Component;

new class extends Component
{
    public string $periodId = '';
    public string $studyProgramId = '';
    public string $classType = '';
    public string $bulkStatus = 'accepted';
    public array $selectedIds = [];
    public array $periods = [];
    public array $studyPrograms = [];

    public function mount(): void
    {
        $this->periods = AdmissionPeriod::query()
            ->orderByDesc('created_at')
            ->get(['id', 'name', 'code'])
            ->map(fn ($period) => ['id' => $period->id, 'label' => $period->name.' ('.$period->code.')'])
            ->toArray();

        $this->studyPrograms = StudyProgram::query()
            ->orderBy('name')
            ->get(['id', 'name', 'code'])
            ->map(fn ($program) => ['id' => $program->id, 'label' => $program->name.' ('.$program->code.')'])
            ->toArray();
    }

    public function rows(AdmissionSelectionService $selectionService)
    {
        return $selectionService->rankedRows($this->filters());
    }

    public function updateDecision(int $applicationId, string $status, AdmissionStatusService $statusService, AdmissionSelectionService $selectionService): void
    {
        abort_unless(ActivePermission::check('admission-selection.update'), 403);
        abort_unless(in_array($status, ['accepted', 'waitlisted', 'rejected', 'under_review'], true), 422);

        $application = AdmissionApplication::findOrFail($applicationId);
        $quota = $selectionService->quotaFor($application);

        if ($status === 'accepted' && $quota && $quota->accepted_count >= $quota->quota && $application->status !== 'accepted') {
            session()->flash('error', 'Quota untuk program/class ini sudah penuh. Gunakan waitlist atau tambah quota.');
            return;
        }

        $statusService->change($application, $status, 'Selection decision updated from ranking dashboard.', auth()->id());
        $selectionService->refreshAcceptedCounts($application->admission_period_id);

        session()->flash('success', 'Decision applicant berhasil diperbarui.');
    }

    public function applyBulkDecision(AdmissionStatusService $statusService, AdmissionSelectionService $selectionService): void
    {
        abort_unless(ActivePermission::check('admission-selection.update'), 403);

        $ids = collect($this->selectedIds)->filter()->values();

        if ($ids->isEmpty()) {
            session()->flash('error', 'Pilih minimal satu applicant.');
            return;
        }

        if (! in_array($this->bulkStatus, ['accepted', 'waitlisted', 'rejected', 'under_review'], true)) {
            session()->flash('error', 'Status bulk tidak valid.');
            return;
        }

        $updated = 0;
        $errors = [];

        foreach (AdmissionApplication::whereKey($ids)->get() as $application) {
            $quota = $selectionService->quotaFor($application);

            if ($this->bulkStatus === 'accepted' && $quota && $quota->accepted_count >= $quota->quota && $application->status !== 'accepted') {
                $errors[] = $application->application_number.' skipped: quota full.';
                continue;
            }

            $statusService->change($application, $this->bulkStatus, 'Bulk selection decision updated.', auth()->id());
            $selectionService->refreshAcceptedCounts($application->admission_period_id);
            $updated++;
        }

        $this->selectedIds = [];

        if ($updated > 0) {
            session()->flash('success', $updated.' applicant berhasil diperbarui.');
        }

        if ($errors) {
            session()->flash('error', implode(' ', $errors));
        }
    }

    public function render()
    {
        return $this->view()->layout('layouts.app', [
            'menus' => 'Admission',
            'pages' => 'Admission Selection',
        ]);
    }

    private function filters(): array
    {
        return [
            'period_id' => $this->periodId ?: null,
            'study_program_id' => $this->studyProgramId ?: null,
            'class_type' => $this->classType ?: null,
        ];
    }
};
?>

<div>
    <x-alert />

    <div class="card mb-4">
        <div class="card-header">
            <div>
                <h3 class="card-title mb-0">Selection Ranking</h3>
                <small class="text-muted">Rank applicants by final score, monitor quota usage, and apply decisions.</small>
            </div>
        </div>
        <div class="card-body">
            <div class="row g-3">
                <div class="col-md-4">
                    <label class="form-label">Admission Period</label>
                    <select class="form-select" wire:model.live="periodId">
                        <option value="">All Periods</option>
                        @foreach($periods as $period)
                            <option value="{{ $period['id'] }}">{{ $period['label'] }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-4">
                    <label class="form-label">Study Program</label>
                    <select class="form-select" wire:model.live="studyProgramId">
                        <option value="">All Programs</option>
                        @foreach($studyPrograms as $program)
                            <option value="{{ $program['id'] }}">{{ $program['label'] }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-4">
                    <label class="form-label">Class Type</label>
                    <select class="form-select" wire:model.live="classType">
                        <option value="">All Classes</option>
                        <option value="regular">Regular</option>
                        <option value="evening">Evening</option>
                        <option value="weekend">Weekend</option>
                    </select>
                </div>
            </div>
        </div>
    </div>

    @activecan('admission-selection.update')
        <div class="card mb-4">
            <div class="card-body">
                <div class="row g-2 align-items-end">
                    <div class="col-md-4">
                        <label class="form-label">Bulk Decision</label>
                        <select class="form-select" wire:model="bulkStatus">
                            <option value="accepted">Accepted</option>
                            <option value="waitlisted">Waitlisted</option>
                            <option value="rejected">Rejected</option>
                            <option value="under_review">Under Review</option>
                        </select>
                    </div>
                    <div class="col-md-3">
                        <button class="btn btn-primary w-100" wire:click="applyBulkDecision">
                            <i class="fas fa-check-double me-1"></i> Apply to Selected
                        </button>
                    </div>
                    <div class="col-md-5 text-muted">
                        {{ count(array_filter($selectedIds)) }} applicant selected
                    </div>
                </div>
            </div>
        </div>
    @endactivecan

    <div class="card">
        <div class="table-responsive">
            <table class="table table-vcenter card-table">
                <thead>
                    <tr>
                        <th style="width:40px;"></th>
                        <th>Rank</th>
                        <th>Applicant</th>
                        <th>Program</th>
                        <th>Final Score</th>
                        <th>Scores</th>
                        <th>Quota</th>
                        <th>Status</th>
                        <th class="text-end">Decision</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($this->rows(app(App\Support\Admission\AdmissionSelectionService::class)) as $row)
                        <tr>
                            <td><input type="checkbox" class="form-check-input" value="{{ $row->id }}" wire:model="selectedIds"></td>
                            <td class="fw-bold">#{{ $row->rank_position }}</td>
                            <td>
                                <div class="fw-bold">{{ $row->full_name }}</div>
                                <small class="text-muted">{{ $row->application_number }}</small>
                            </td>
                            <td>
                                <div>{{ $row->studyProgram?->name ?? '-' }}</div>
                                <small class="text-muted">{{ ucfirst($row->class_type ?? '-') }}</small>
                            </td>
                            <td class="fw-bold">{{ $row->final_score ?? '-' }}</td>
                            <td>{{ $row->scores_count }}</td>
                            <td>
                                @if($row->quota_limit)
                                    {{ $row->quota_used }}/{{ $row->quota_limit }}
                                @else
                                    <span class="text-muted">No quota</span>
                                @endif
                            </td>
                            <td><span class="badge bg-blue-lt text-blue">{{ str($row->status)->replace('_', ' ')->title() }}</span></td>
                            <td class="text-end">
                                @activecan('admission-selection.update')
                                    <div class="btn-list justify-content-end">
                                        <button class="btn btn-sm btn-success" wire:click="updateDecision({{ $row->id }}, 'accepted')">Accept</button>
                                        <button class="btn btn-sm btn-warning" wire:click="updateDecision({{ $row->id }}, 'waitlisted')">Waitlist</button>
                                        <button class="btn btn-sm btn-danger" wire:click="updateDecision({{ $row->id }}, 'rejected')">Reject</button>
                                    </div>
                                @endactivecan
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="9" class="text-center text-muted py-5">Belum ada applicant untuk filter ini.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>
