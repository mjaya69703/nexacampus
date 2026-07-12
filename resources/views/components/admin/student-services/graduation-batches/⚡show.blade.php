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

@push('styles')
    <style>
        .service-show .soft-card {
            border: 0;
            border-radius: 16px;
            box-shadow: 0 4px 20px rgba(15, 23, 42, .08);
            margin-bottom: 1.5rem;
        }
    </style>
@endpush

<div class="w-full service-show" style="width: 100% !important">
    <x-alert />

    <x-admin.student-services.header
        title="{{ $batch->name }}"
        description="Kode Batch: {{ $batch->code }} • Periode: {{ $batch->academicPeriod?->name ?? '-' }}"
        icon="layer-group"
    >
        <div class="d-flex align-items-center gap-2">
            <a href="{{ route('admin.student-services.graduation-batches.edit', ['id' => $batch->id]) }}" class="btn btn-sm btn-light text-primary fw-semibold d-inline-flex align-items-center gap-2 shadow-sm rounded-pill px-3 py-2">
                <i class="fa fa-edit"></i> <span>Edit Batch</span>
            </a>
            <a href="{{ route('admin.student-services.graduation-batches.index') }}" class="btn btn-sm btn-light text-dark fw-semibold d-inline-flex align-items-center gap-2 shadow-sm rounded-pill px-3 py-2">
                <i class="fa fa-arrow-left"></i> <span>Kembali ke daftar</span>
            </a>
        </div>

        <x-slot:stats>
            <div class="d-flex flex-wrap gap-2 gap-lg-3">
                <div class="bg-white bg-opacity-10 rounded-3 px-3 py-2 d-flex align-items-center gap-2">
                    <i class="fa fa-circle-info fs-6"></i>
                    <div>
                        <div class="small text-white text-opacity-75">Status Batch</div>
                        <div class="fw-bold">{{ str($batch->status)->title() }}</div>
                    </div>
                </div>
                <div class="bg-white bg-opacity-10 rounded-3 px-3 py-2 d-flex align-items-center gap-2">
                    <i class="fa fa-calendar-check fs-6"></i>
                    <div>
                        <div class="small text-white text-opacity-75">Tanggal Yudisium</div>
                        <div class="fw-bold">{{ $batch->yudisium_date?->format('d M Y') ?? 'Belum ditentukan' }}</div>
                    </div>
                </div>
                <div class="bg-white bg-opacity-10 rounded-3 px-3 py-2 d-flex align-items-center gap-2">
                    <i class="fa fa-users fs-6"></i>
                    <div>
                        <div class="small text-white text-opacity-75">Total Peserta</div>
                        <div class="fw-bold">{{ $batch->applications_count }} Mahasiswa</div>
                    </div>
                </div>
                <div class="bg-white bg-opacity-10 rounded-3 px-3 py-2 d-flex align-items-center gap-2">
                    <i class="fa fa-graduation-cap fs-6"></i>
                    <div>
                        <div class="small text-white text-opacity-75">Sudah Lulus (Final)</div>
                        <div class="fw-bold">{{ $batch->finalized_count }} Mahasiswa</div>
                    </div>
                </div>
            </div>
        </x-slot:stats>
    </x-admin.student-services.header>

    <div class="row g-3 mb-4">
        @foreach ([
            ['label' => 'Total Applications', 'value' => $batch->applications_count, 'color' => 'primary', 'icon' => 'fa-users'],
            ['label' => 'Approved (Siap Finalisasi)', 'value' => $batch->approved_count, 'color' => 'info', 'icon' => 'fa-check-circle'],
            ['label' => 'Finalized (Lulus)', 'value' => $batch->finalized_count, 'color' => 'success', 'icon' => 'fa-graduation-cap'],
            ['label' => 'Pending / Review', 'value' => max(0, $batch->applications_count - $batch->approved_count - $batch->finalized_count), 'color' => 'warning', 'icon' => 'fa-clock']
        ] as $card)
            <div class="col-12 col-sm-6 col-xl-3">
                <div class="border rounded-4 p-3 h-100 bg-white shadow-sm d-flex align-items-center justify-content-between">
                    <div>
                        <div class="text-muted small fw-semibold mb-1">{{ $card['label'] }}</div>
                        <div class="h3 mb-0 fw-bold text-{{ $card['color'] }}">{{ $card['value'] }}</div>
                    </div>
                    <div class="bg-{{ $card['color'] }} bg-opacity-10 text-{{ $card['color'] }} rounded-3 d-flex align-items-center justify-content-center" style="width: 48px; height: 48px;">
                        <i class="fa {{ $card['icon'] }} fs-5"></i>
                    </div>
                </div>
            </div>
        @endforeach
    </div>

    <div class="row g-4 align-items-start">
        <div class="col-lg-8">
            <div class="card soft-card">
                <div class="card-header border-bottom-0 pt-4 px-4 pb-0">
                    <h4 class="card-title fw-bold mb-0">Informasi & Spesifikasi Batch Yudisium</h4>
                </div>
                <div class="card-body p-4">
                    <div class="row g-3">
                        <div class="col-md-4">
                            <small class="text-muted">Status Batch</small>
                            <div><span class="badge {{ $this->statusBadge($batch->status) }} px-3 py-1">{{ str($batch->status)->title() }}</span></div>
                        </div>
                        <div class="col-md-4">
                            <small class="text-muted">Periode Akademik</small>
                            <div class="fw-bold">{{ $batch->academicPeriod?->name ?? '-' }}</div>
                        </div>
                        <div class="col-md-4">
                            <small class="text-muted">Tahun Ajaran</small>
                            <div class="fw-bold">{{ $batch->academicPeriod?->academicYear?->name ?? '-' }}</div>
                        </div>
                        <div class="col-md-4">
                            <small class="text-muted">Lingkup Program Studi (Scope)</small>
                            <div class="fw-bold">{{ $batch->studyProgram?->name ?? 'Semua Program Studi' }}</div>
                        </div>
                        <div class="col-md-4">
                            <small class="text-muted">Tanggal Pelaksanaan Yudisium</small>
                            <div class="fw-bold text-primary">{{ $batch->yudisium_date?->format('d M Y') ?? '-' }}</div>
                        </div>
                        <div class="col-md-4">
                            <small class="text-muted">Nomor & Tanggal SK</small>
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

            <div class="card soft-card">
                <div class="card-header border-bottom-0 pt-4 px-4 pb-0 d-flex justify-content-between align-items-center">
                    <h4 class="card-title fw-bold mb-0">Daftar Pengajuan Mahasiswa dalam Batch Ini</h4>
                    <span class="badge bg-primary">{{ $batch->applications->count() }} Pengajuan</span>
                </div>
                <div class="table-responsive p-3">
                    <table class="table table-vcenter table-hover">
                        <thead>
                            <tr>
                                <th>No</th>
                                <th>Mahasiswa</th>
                                <th>Program Studi</th>
                                <th>Status</th>
                                <th class="text-end">Aksi</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse ($batch->applications as $application)
                                <tr>
                                    <td class="fw-bold">{{ $application->application_number }}</td>
                                    <td>
                                        <div class="fw-bold">{{ $application->studentProfile?->user?->name ?? '-' }}</div>
                                        <div class="small text-muted">{{ $application->studentProfile?->nim ?? '-' }}</div>
                                    </td>
                                    <td>{{ $application->studentProfile?->studyProgram?->name ?? '-' }}</td>
                                    <td><span class="badge {{ $this->statusBadge($application->status) }} px-2 py-1">{{ str($application->status)->replace('_', ' ')->title() }}</span></td>
                                    <td class="text-end">
                                        <a href="{{ route('admin.student-services.graduation-applications.show', ['id' => $application->id]) }}" class="btn btn-sm btn-outline-primary fw-semibold shadow-sm">
                                            <i class="fas fa-eye me-1"></i> Detail
                                        </a>
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="5" class="text-muted text-center py-4">Belum ada pengajuan mahasiswa yang dikelompokkan ke batch yudisium ini.</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <div class="col-lg-4">
            <div class="card soft-card">
                <div class="card-header border-bottom-0 pt-4 px-4 pb-0">
                    <h4 class="card-title fw-bold mb-0">Finalisasi Massal (Bulk Finalize)</h4>
                </div>
                <div class="card-body p-4 d-grid gap-3">
                    <div class="alert alert-info mb-0">
                        <strong>Bulk Finalize</strong> akan memproses dan meluluskan semua pengajuan berstatus <strong>Approved</strong> di batch ini secara serentak memakai tanggal yudisium batch.
                    </div>
                    <div>
                        <label class="form-label fw-semibold">Catatan Finalisasi (Opsional)</label>
                        <textarea wire:model="finalizeNotes" rows="3" class="form-control" placeholder="Tuliskan catatan kelulusan atau referensi yudisium..."></textarea>
                    </div>
                    <button wire:click="bulkFinalize" class="btn btn-primary fw-bold py-2 shadow-sm" @disabled(! $batch->yudisium_date || $batch->approved_count < 1 || $batch->status === 'cancelled')>
                        <i class="fas fa-user-graduate me-1"></i> Bulk Finalize ({{ $batch->approved_count }} Approved)
                    </button>
                    @if (! $batch->yudisium_date)
                        <div class="alert alert-danger mb-0 mt-1">Isi tanggal yudisium pada pengaturan batch sebelum melakukan bulk finalize.</div>
                    @endif
                </div>
            </div>
        </div>
    </div>
</div>
