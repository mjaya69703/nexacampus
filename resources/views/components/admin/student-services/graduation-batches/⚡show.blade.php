<?php

use App\Models\StudentService\GraduationBatch;
use App\Support\ActivePermission;
use App\Support\StudentService\GraduationApplicationService;
use Livewire\Component;

new class extends Component
{
    public GraduationBatch $batch;

    public ?string $finalizeNotes = null;

    public function mount($id): void
    {
        $this->batch = GraduationBatch::with([
            'academicPeriod.academicYear',
            'studyProgram',
            'applications.studentProfile.user',
            'applications.studentProfile.studyProgram',
        ])->withCount([
            'applications',
            'applications as approved_count' => fn ($query) => $query->where('status', 'approved'),
            'applications as finalized_count' => fn ($query) => $query->where('status', 'finalized'),
        ])->findOrFail($id);
    }

    public function bulkFinalize(GraduationApplicationService $service): void
    {
        abort_unless(ActivePermission::check('graduation-batch.update'), 403);

        try {
            $count = $service->finalizeApprovedBatch($this->batch, $this->finalizeNotes, auth()->id());
            session()->flash('success', $count.' pengajuan approved berhasil difinalisasi.');
        } catch (\Throwable $exception) {
            session()->flash('error', $exception->getMessage());
        }

        $this->reload();
    }

    public function render()
    {
        return $this->view()->layout('layouts.app', [
            'menus' => 'Layanan Mahasiswa',
            'pages' => 'Detail Batch Yudisium',
        ]);
    }

    public function statusBadge(string $status): string
    {
        return match ($status) {
            'open' => 'bg-success',
            'review' => 'bg-primary',
            'finalized' => 'bg-info',
            'cancelled' => 'bg-danger',
            default => 'bg-secondary',
        };
    }

    private function reload(): void
    {
        $this->batch->refresh()->load([
            'academicPeriod.academicYear',
            'studyProgram',
            'applications.studentProfile.user',
            'applications.studentProfile.studyProgram',
        ])->loadCount([
            'applications',
            'applications as approved_count' => fn ($query) => $query->where('status', 'approved'),
            'applications as finalized_count' => fn ($query) => $query->where('status', 'finalized'),
        ]);
    }
};
?>

<div>
    <x-alert />

    <div class="row mb-3">
        @foreach ([['label' => 'Applications', 'value' => $batch->applications_count, 'color' => 'text-primary'], ['label' => 'Approved', 'value' => $batch->approved_count, 'color' => 'text-info'], ['label' => 'Finalized', 'value' => $batch->finalized_count, 'color' => 'text-success'], ['label' => 'Pending', 'value' => max(0, $batch->applications_count - $batch->approved_count - $batch->finalized_count), 'color' => 'text-warning']] as $card)
            <div class="col-md-3">
                <div class="card">
                    <div class="card-body">
                        <div class="text-muted text-uppercase small">{{ $card['label'] }}</div>
                        <div class="h2 mb-0 {{ $card['color'] }}">{{ $card['value'] }}</div>
                    </div>
                </div>
            </div>
        @endforeach
    </div>

    <div class="row">
        <div class="col-lg-8">
            <div class="card mb-3">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <div>
                        <h3 class="card-title mb-0">{{ $batch->name }}</h3>
                        <small class="text-muted">{{ $batch->code }}</small>
                    </div>
                    <div class="d-flex gap-2">
                        <a href="{{ route('admin.student-services.graduation-batches.edit', ['id' => $batch->id]) }}" class="btn btn-primary">
                            <i class="fas fa-edit me-1"></i> Edit
                        </a>
                        <a href="{{ route('admin.student-services.graduation-batches.index') }}" class="btn btn-secondary">
                            <i class="fas fa-arrow-left me-1"></i> Back
                        </a>
                    </div>
                </div>
                <div class="card-body">
                    <div class="row g-3">
                        <div class="col-md-4">
                            <small class="text-muted">Status</small>
                            <div><span class="badge {{ $this->statusBadge($batch->status) }}">{{ str($batch->status)->title() }}</span></div>
                        </div>
                        <div class="col-md-4">
                            <small class="text-muted">Academic Period</small>
                            <div class="fw-bold">{{ $batch->academicPeriod?->name ?? '-' }}</div>
                        </div>
                        <div class="col-md-4">
                            <small class="text-muted">Academic Year</small>
                            <div class="fw-bold">{{ $batch->academicPeriod?->academicYear?->name ?? '-' }}</div>
                        </div>
                        <div class="col-md-4">
                            <small class="text-muted">Scope</small>
                            <div class="fw-bold">{{ $batch->studyProgram?->name ?? 'All Programs' }}</div>
                        </div>
                        <div class="col-md-4">
                            <small class="text-muted">Tanggal Yudisium</small>
                            <div class="fw-bold">{{ $batch->yudisium_date?->format('d M Y') ?? '-' }}</div>
                        </div>
                        <div class="col-md-4">
                            <small class="text-muted">SK</small>
                            <div class="fw-bold">{{ $batch->sk_number ?: '-' }}{{ $batch->sk_date ? ' / '.$batch->sk_date->format('d M Y') : '' }}</div>
                        </div>
                        @if ($batch->notes)
                            <div class="col-12">
                                <div class="alert alert-info mb-0">{{ $batch->notes }}</div>
                            </div>
                        @endif
                    </div>
                </div>
            </div>

            <div class="card">
                <div class="card-header">
                    <h3 class="card-title mb-0">Pengajuan dalam Batch</h3>
                </div>
                <div class="table-responsive">
                    <table class="table table-vcenter">
                        <thead>
                            <tr>
                                <th>No</th>
                                <th>Mahasiswa</th>
                                <th>Program Studi</th>
                                <th>Status</th>
                                <th class="text-end">Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse ($batch->applications as $application)
                                <tr>
                                    <td>{{ $application->application_number }}</td>
                                    <td>
                                        <div class="fw-bold">{{ $application->studentProfile?->user?->name ?? '-' }}</div>
                                        <div class="small text-muted">{{ $application->studentProfile?->nim ?? '-' }}</div>
                                    </td>
                                    <td>{{ $application->studentProfile?->studyProgram?->name ?? '-' }}</td>
                                    <td><span class="badge bg-secondary">{{ str($application->status)->replace('_', ' ')->title() }}</span></td>
                                    <td class="text-end">
                                        <a href="{{ route('admin.student-services.graduation-applications.show', ['id' => $application->id]) }}" class="btn btn-sm btn-primary">
                                            <i class="fas fa-eye"></i>
                                        </a>
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="5" class="text-muted">Belum ada pengajuan di batch ini.</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <div class="col-lg-4">
            <div class="card">
                <div class="card-header">
                    <h3 class="card-title mb-0">Bulk Finalize</h3>
                </div>
                <div class="card-body d-grid gap-3">
                    <div class="alert alert-info mb-0">
                        Bulk finalize akan memproses semua application berstatus <strong>Approved</strong> di batch ini memakai tanggal yudisium batch.
                    </div>
                    <label class="form-label">Finalize Notes</label>
                    <textarea wire:model="finalizeNotes" rows="3" class="form-control" placeholder="Opsional"></textarea>
                    <button wire:click="bulkFinalize" class="btn btn-primary" @disabled(! $batch->yudisium_date || $batch->approved_count < 1 || $batch->status === 'cancelled')>
                        <i class="fas fa-user-graduate me-1"></i> Bulk Finalize Approved
                    </button>
                    @if (! $batch->yudisium_date)
                        <div class="alert alert-danger mb-0">Isi tanggal yudisium batch sebelum bulk finalize.</div>
                    @endif
                </div>
            </div>
        </div>
    </div>
</div>
