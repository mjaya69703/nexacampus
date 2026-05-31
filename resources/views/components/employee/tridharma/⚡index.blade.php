<?php

use App\Models\Organization\TridharmaRecord;
use Illuminate\Support\Collection;
use Livewire\Component;

new class extends Component
{
    public string $filter = 'all';
    public string $search = '';

    public function mount(): void
    {
        abort_unless($this->canUseTridharmaSelfService(), 403);
    }

    public function records(): Collection
    {
        return TridharmaRecord::query()
            ->withCount(['milestones', 'outputs', 'attachments'])
            ->with(['milestones:id,tridharma_record_id,progress_percentage'])
            ->where('user_id', auth()->id())
            ->when($this->filter !== 'all', fn ($query) => $query->where('type', $this->filter))
            ->when(filled($this->search), function ($query) {
                $search = '%'.trim($this->search).'%';
                $query->where(function ($query) use ($search) {
                    $query->where('title', 'like', $search)
                        ->orWhere('scheme', 'like', $search)
                        ->orWhere('funding_source', 'like', $search);
                });
            })
            ->latest()
            ->get();
    }

    public function render()
    {
        return $this->view()->layout('layouts.app', [
            'menus' => 'Tridharma',
            'pages' => 'Tridharma Saya',
        ]);
    }

    public function typeLabel(string $type): string
    {
        return match ($type) {
            'research' => 'Penelitian',
            'community_service' => 'Pengabdian',
            'publication' => 'Publikasi',
            default => str($type)->replace('_', ' ')->title()->toString(),
        };
    }

    public function statusLabel(string $status): string
    {
        return str($status)->replace('_', ' ')->title()->toString();
    }

    public function statusStyle(string $status): string
    {
        return match ($status) {
            'approved', 'active', 'completed' => 'background:#dcfce7;color:#15803d;',
            'rejected' => 'background:#fee2e2;color:#dc2626;',
            'in_approval', 'submitted' => 'background:#fef3c7;color:#b45309;',
            'archived' => 'background:#f1f5f9;color:#64748b;',
            default => 'background:#eef2ff;color:#4f46e5;',
        };
    }

    public function progressFor(TridharmaRecord $record): int
    {
        if ($record->milestones->isEmpty()) {
            return 0;
        }

        return (int) round($record->milestones->avg('progress_percentage'));
    }

    public function routePrefix(): string
    {
        return request()->routeIs('lecturer.*') ? 'lecturer.' : 'employee.';
    }

    private function canUseTridharmaSelfService(): bool
    {
        if (request()->routeIs('lecturer.*')) {
            return true;
        }

        return (bool) auth()->user()?->employeeProfile?->is_active;
    }
};
?>

@include('components.lecturer.assignments.assignment-styles')

@php
    $records = $this->records();
    $totalFunding = $records->sum(fn ($record) => (float) $record->funding_amount);
@endphp

<div>
    <x-alert />

    <div class="card assignment-card assignment-hero mb-4">
        <div class="card-body p-4 p-lg-5" style="position:relative;">
            <div class="d-flex justify-content-between gap-3 flex-wrap">
                <div class="d-flex gap-3">
                    <span class="assignment-icon" style="background:rgba(255,255,255,.2);font-size:1.8rem;">
                        <i class="fas fa-seedling"></i>
                    </span>
                    <div>
                        <div style="opacity:.86;font-weight:700;">Ruang Kerja Dosen</div>
                        <h1 class="h2 mb-2" style="font-weight:900;">Tridharma Saya</h1>
                        <div style="opacity:.9;">Kelola proposal, approval, progress, luaran, dan dokumen pendukung.</div>
                        <div class="d-flex flex-wrap gap-2 mt-3">
                            <span class="assignment-pill assignment-info-pill"><i class="fas fa-folder-open"></i>{{ $records->count() }} record</span>
                            <span class="assignment-pill assignment-info-pill"><i class="fas fa-check-circle"></i>{{ $records->whereIn('status', ['approved', 'active', 'completed'])->count() }} disetujui</span>
                            <span class="assignment-pill assignment-info-pill"><i class="fas fa-paperclip"></i>{{ $records->sum('attachments_count') }} file</span>
                        </div>
                    </div>
                </div>
                <div class="d-flex flex-wrap gap-2 align-items-end" style="min-width:min(100%, 600px);">
                    <div class="flex-grow-1" style="min-width:240px;">
                        <label class="form-label text-white fw-bold">Cari</label>
                        <input type="text" class="form-control" wire:model.live.debounce.300ms="search" placeholder="Judul, skema, sumber dana...">
                    </div>
                    <div style="min-width:190px;">
                        <label class="form-label text-white fw-bold">Kategori</label>
                        <select class="form-control" wire:model.live="filter">
                            <option value="all">Semua</option>
                            <option value="research">Penelitian</option>
                            <option value="community_service">Pengabdian</option>
                            <option value="publication">Publikasi</option>
                        </select>
                    </div>
                    <a href="{{ route($this->routePrefix().'tridharma.create') }}" class="assignment-action" style="background:rgba(255,255,255,.95);color:#4f46e5;">
                        <i class="fas fa-plus"></i>Ajukan
                    </a>
                </div>
            </div>
        </div>
    </div>

    <div class="row g-3 mb-4">
        <div class="col-md-3"><div class="assignment-panel h-100"><div class="text-secondary">Draft</div><div class="h2 fw-bold mb-0">{{ $records->where('status', 'draft')->count() }}</div></div></div>
        <div class="col-md-3"><div class="assignment-panel h-100"><div class="text-secondary">Approval</div><div class="h2 fw-bold mb-0 text-warning">{{ $records->whereIn('status', ['submitted', 'in_approval'])->count() }}</div></div></div>
        <div class="col-md-3"><div class="assignment-panel h-100"><div class="text-secondary">Luaran</div><div class="h2 fw-bold mb-0 text-success">{{ $records->sum('outputs_count') }}</div></div></div>
        <div class="col-md-3"><div class="assignment-panel h-100"><div class="text-secondary">Pendanaan</div><div class="h2 fw-bold mb-0 text-primary">Rp {{ number_format($totalFunding, 0, ',', '.') }}</div></div></div>
    </div>

    <div class="card assignment-card">
        <div class="card-header py-3 d-flex justify-content-between align-items-center gap-3 flex-wrap">
            <h3 class="card-title mb-0" style="font-weight:800;"><i class="fas fa-list-check me-2 text-primary"></i>Daftar Tridharma</h3>
            <span class="assignment-pill">{{ $records->count() }} record</span>
        </div>
        <div class="card-body p-4">
            <div class="assignment-shell">
                @forelse ($records as $record)
                    @php($progress = $this->progressFor($record))
                    <div class="assignment-list-item">
                        <div class="row g-3 align-items-center">
                            <div class="col-xl-6">
                                <div class="d-flex gap-3">
                                    <span class="assignment-icon" style="background:#eef2ff;color:#4f46e5;">
                                        <i class="fas {{ $record->type === 'publication' ? 'fa-newspaper' : ($record->type === 'community_service' ? 'fa-hands-helping' : 'fa-flask') }}"></i>
                                    </span>
                                    <div>
                                        <div class="d-flex flex-wrap gap-2 mb-2">
                                            <span class="assignment-pill">{{ $this->typeLabel($record->type) }}</span>
                                            <span class="assignment-pill" style="{{ $this->statusStyle($record->status) }}">{{ $this->statusLabel($record->status) }}</span>
                                            @if ($record->is_verified)
                                                <span class="assignment-pill" style="background:#dcfce7;color:#15803d;">Terverifikasi</span>
                                            @endif
                                        </div>
                                        <div class="fw-bold">{{ $record->title }}</div>
                                        <div class="text-secondary small">{{ $record->scheme ?: 'Tanpa skema' }} / {{ $record->funding_source ?: 'Tanpa sumber dana' }}</div>
                                        <div class="text-secondary small mt-1"><i class="fas fa-calendar me-1"></i>{{ $record->starts_at?->format('d M Y') ?? '-' }} - {{ $record->ends_at?->format('d M Y') ?? '-' }}</div>
                                    </div>
                                </div>
                            </div>
                            <div class="col-xl-3">
                                <div class="d-flex justify-content-between small mb-1"><span>Progress</span><strong>{{ $progress }}%</strong></div>
                                <div class="assignment-progress"><span style="width:{{ $progress }}%;"></span></div>
                                <div class="text-secondary small mt-2">{{ $record->milestones_count }} milestone / {{ $record->outputs_count }} luaran / {{ $record->attachments_count }} file</div>
                            </div>
                            <div class="col-xl-3">
                                <div class="d-flex justify-content-xl-end gap-2 flex-wrap">
                                    <a href="{{ route($this->routePrefix().'tridharma.show', $record->id) }}" class="assignment-action" style="background:linear-gradient(135deg,#667eea 0%,#764ba2 100%);color:white;">
                                        <i class="fas fa-eye"></i>Detail
                                    </a>
                                </div>
                            </div>
                        </div>
                    </div>
                @empty
                    <div class="text-center text-secondary py-5">
                        <i class="fas fa-inbox fa-3x mb-3"></i>
                        <div class="fw-bold">Belum ada record Tridharma.</div>
                        <div class="mt-1">Mulai dari draft proposal penelitian, pengabdian, atau publikasi.</div>
                        <a href="{{ route($this->routePrefix().'tridharma.create') }}" class="assignment-action mt-3" style="background:linear-gradient(135deg,#667eea 0%,#764ba2 100%);color:white;">
                            <i class="fas fa-plus"></i>Ajukan Tridharma
                        </a>
                    </div>
                @endforelse
            </div>
        </div>
    </div>
</div>
