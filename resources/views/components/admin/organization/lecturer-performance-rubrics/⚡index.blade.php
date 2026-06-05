<?php

use App\Models\Organization\LecturerPerformanceRubric;
use Livewire\Component;

new class extends Component
{
    public function toggleActive(int $id): void
    {
        $rubric = LecturerPerformanceRubric::findOrFail($id);
        LecturerPerformanceRubric::query()->whereKeyNot($rubric->id)->update(['is_active' => false]);
        $rubric->update(['is_active' => true, 'updated_by' => auth()->id()]);
        session()->flash('success', 'Rubrik aktif berhasil diperbarui.');
    }

    public function render()
    {
        return $this->view([
            'rubrics' => LecturerPerformanceRubric::query()->latest('is_active')->latest('updated_at')->get(),
        ])->layout('layouts.app', ['menus' => 'Kepegawaian', 'pages' => 'Rubrik Performa']);
    }
};
?>

<div>
    <x-alert />
    <div class="card mb-3">
        <div class="card-header d-flex justify-content-between align-items-center gap-3 flex-wrap">
            <div>
                <h3 class="card-title mb-0">Rubrik Performa Dosen</h3>
                <small class="text-muted">Atur bobot EDOM, kepatuhan mengajar, absensi pegawai, dan BKD untuk skor performa.</small>
            </div>
            @activecan('lecturer-performance-rubric.create')
                <a href="{{ route('admin.organization.lecturer-performance-rubrics.create') }}" class="btn btn-primary"><i class="fas fa-plus me-2"></i>Tambah Rubrik</a>
            @endactivecan
        </div>
    </div>

    <div class="row g-3">
        @forelse ($rubrics as $rubric)
            <div class="col-lg-6">
                <div class="card h-100">
                    <div class="card-body">
                        <div class="d-flex justify-content-between align-items-start gap-3">
                            <div>
                                <div class="d-flex flex-wrap gap-2 mb-2">
                                    <span class="badge bg-blue-lt">{{ $rubric->code }}</span>
                                    @if ($rubric->is_active)
                                        <span class="badge bg-green-lt">Aktif</span>
                                    @endif
                                </div>
                                <h3 class="h4 mb-1">{{ $rubric->name }}</h3>
                                <div class="text-muted small">Minimal respon {{ $rubric->minimum_responses }} / target BKD {{ $rubric->target_workload_sks }} SKS</div>
                            </div>
                            <div class="text-end">
                                <div class="h2 mb-0">{{ number_format($rubric->totalWeight(), 2) }}</div>
                                <small class="text-muted">total bobot</small>
                            </div>
                        </div>
                        <div class="row g-2 mt-3">
                            @foreach ([['EDOM', $rubric->edom_weight], ['Mengajar', $rubric->teaching_weight], ['Absensi', $rubric->attendance_weight], ['BKD', $rubric->workload_weight]] as [$label, $value])
                                <div class="col-6"><div class="border rounded p-2"><small class="text-muted">{{ $label }}</small><div class="fw-bold">{{ $value }}%</div></div></div>
                            @endforeach
                        </div>
                    </div>
                    <div class="card-footer d-flex justify-content-end gap-2">
                        @activecan('lecturer-performance-rubric.update')
                            @unless ($rubric->is_active)
                                <button type="button" wire:click="toggleActive({{ $rubric->id }})" class="btn btn-outline-primary">Jadikan Aktif</button>
                            @endunless
                            <a href="{{ route('admin.organization.lecturer-performance-rubrics.edit', $rubric->id) }}" class="btn btn-primary"><i class="fas fa-edit me-1"></i>Edit</a>
                        @endactivecan
                    </div>
                </div>
            </div>
        @empty
            <div class="col-12"><div class="card"><div class="card-body text-center text-muted py-5">Belum ada rubrik performa.</div></div></div>
        @endforelse
    </div>
</div>
