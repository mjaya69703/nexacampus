<?php

use App\Models\Admission\AdmissionPeriod;
use App\Models\Academic\AcademicYear;
use Illuminate\Validation\Rule;
use Livewire\Component;

new class extends Component
{
    public AdmissionPeriod $period;

    public array $periodForm = [];

    public array $documentRequirements = [];

    public array $academicYears = [];

    public function mount($id): void
    {
        $this->period = AdmissionPeriod::with('documentRequirements')->findOrFail($id);

        $this->periodForm = [
            'name' => $this->period->name,
            'code' => $this->period->code,
            'academic_year_id' => $this->period->academic_year_id,
            'academic_year' => $this->period->academic_year,
            'wave' => $this->period->wave,
            'opens_at' => $this->period->opens_at?->toDateString(),
            'closes_at' => $this->period->closes_at?->toDateString(),
            'is_active' => $this->period->is_active,
            'is_published' => $this->period->is_published,
            'description' => $this->period->description,
        ];

        $this->documentRequirements = $this->period->documentRequirements
            ->sortBy('sort_order')
            ->map(fn ($requirement) => [
                'id' => $requirement->id,
                'document_type' => $requirement->document_type,
                'label' => $requirement->label,
                'is_required' => $requirement->is_required,
                'allowed_extensions' => $requirement->allowed_extensions,
                'max_size_kb' => $requirement->max_size_kb,
            ])
            ->values()
            ->toArray();

        $this->academicYears = AcademicYear::query()
            ->orderByDesc('start_date')
            ->get(['id', 'name', 'code', 'start_date'])
            ->map(fn (AcademicYear $academicYear) => [
                'id' => $academicYear->id,
                'name' => $academicYear->name,
                'year' => (int) $academicYear->start_date?->format('Y'),
            ])
            ->toArray();
    }

    public function updatedPeriodFormAcademicYearId($value): void
    {
        $this->syncAcademicYearFromSelection($value);
    }

    public function addRequirement(): void
    {
        $this->documentRequirements[] = [
            'id' => null,
            'document_type' => '',
            'label' => '',
            'is_required' => true,
            'allowed_extensions' => 'pdf,jpg,jpeg,png',
            'max_size_kb' => 2048,
        ];
    }

    public function removeRequirement(int $index): void
    {
        $id = $this->documentRequirements[$index]['id'] ?? null;

        if ($id) {
            $this->period->documentRequirements()->whereKey($id)->delete();
        }

        unset($this->documentRequirements[$index]);
        $this->documentRequirements = array_values($this->documentRequirements);
    }

    public function updatePeriod(): void
    {
        $this->syncAcademicYearFromSelection($this->periodForm['academic_year_id'] ?? null);

        $validated = $this->validate([
            'periodForm.name' => 'required|string|max:255',
            'periodForm.code' => ['required', 'string', 'max:50', Rule::unique('admission_periods', 'code')->ignore($this->period->id)],
            'periodForm.academic_year_id' => 'required|exists:academic_years,id',
            'periodForm.academic_year' => 'required|integer|min:2000|max:2100',
            'periodForm.wave' => 'required|integer|min:1|max:20',
            'periodForm.opens_at' => 'required|date',
            'periodForm.closes_at' => 'required|date|after_or_equal:periodForm.opens_at',
            'periodForm.is_active' => 'boolean',
            'periodForm.is_published' => 'boolean',
            'periodForm.description' => 'nullable|string',
            'documentRequirements' => 'array',
            'documentRequirements.*.id' => 'nullable|integer',
            'documentRequirements.*.document_type' => 'required|string|max:80|distinct',
            'documentRequirements.*.label' => 'required|string|max:255',
            'documentRequirements.*.is_required' => 'boolean',
            'documentRequirements.*.allowed_extensions' => 'nullable|string|max:255',
            'documentRequirements.*.max_size_kb' => 'nullable|integer|min:1|max:10240',
        ]);

        $this->period->update([
            ...$validated['periodForm'],
            'updated_by' => auth()->id(),
        ]);

        foreach ($validated['documentRequirements'] as $index => $requirement) {
            $id = $requirement['id'] ?? null;
            unset($requirement['id']);
            $requirement['allowed_extensions'] = $this->sanitizeAllowedExtensions($requirement['allowed_extensions'] ?? null);

            $this->period->documentRequirements()->updateOrCreate(
                ['id' => $id],
                [
                    ...$requirement,
                    'sort_order' => $index + 1,
                ],
            );
        }

        session()->flash('success', 'Admission period berhasil diperbarui.');
        $this->redirectRoute('admin.admission.admission-periods.index');
    }

    public function cancel(): void
    {
        $this->redirectRoute('admin.admission.admission-periods.index');
    }

    public function render()
    {
        return $this->view()->layout('layouts.app', [
            'menus' => 'Admission',
            'pages' => 'Edit Periode PMB',
        ]);
    }

    private function sanitizeAllowedExtensions(?string $extensions): string
    {
        $safeAllowList = ['pdf', 'jpg', 'jpeg', 'png', 'webp'];

        $allowed = collect(explode(',', $extensions ?: 'pdf,jpg,jpeg,png'))
            ->map(fn (string $extension) => strtolower(trim($extension)))
            ->filter()
            ->unique()
            ->intersect($safeAllowList)
            ->values()
            ->all();

        return implode(',', $allowed ?: ['pdf', 'jpg', 'jpeg', 'png']);
    }

    private function syncAcademicYearFromSelection($value): void
    {
        $selected = collect($this->academicYears)->firstWhere('id', (int) $value);

        if ($selected) {
            $this->periodForm['academic_year'] = $selected['year'];
        }
    }
};
?>

<div class="w-full" style="width: 100% !important">
    <x-alert />

    <x-admin.admission.header
        title="Edit Periode & Gelombang PMB"
        description="Perbarui informasi jadwal pembukaan pendaftaran, ubah status aktif atau publikasi, serta sesuaikan syarat berkas dokumen."
        icon="calendar-check"
    >
        <button type="button" wire:click="cancel" class="btn btn-light rounded-pill px-4 py-2 text-dark fw-bold shadow-sm d-inline-flex align-items-center gap-2 border-0">
            <i class="fas fa-arrow-left"></i> Kembali ke Daftar
        </button>
    </x-admin.admission.header>

    <div class="card border-0 shadow-sm rounded-4 overflow-hidden mb-4">
        <div class="card-header bg-white border-bottom p-4">
            <h4 class="fw-bold mb-1 text-dark d-flex align-items-center gap-2">
                <i class="fas fa-edit text-primary"></i> Perbarui Informasi Gelombang
            </h4>
            <p class="text-muted fs-7 mb-0">Sesuaikan tanggal mulai dan akhir masa pendaftaran serta penetapan gelombang penerimaan.</p>
        </div>
        <div class="card-body p-4 row g-3">
            <div class="col-lg-6">
                <label class="form-label fw-bold text-dark fs-7">Nama Gelombang / Periode <span class="text-danger">*</span></label>
                <input type="text" class="form-control rounded-3 border-secondary border-opacity-25" wire:model.defer="periodForm.name">
                @error('periodForm.name') <span class="text-danger fs-8 mt-1 d-block">{{ $message }}</span> @enderror
            </div>
            <div class="col-lg-3">
                <label class="form-label fw-bold text-dark fs-7">Kode Unik <span class="text-danger">*</span></label>
                <input type="text" class="form-control rounded-3 border-secondary border-opacity-25" wire:model.defer="periodForm.code">
                @error('periodForm.code') <span class="text-danger fs-8 mt-1 d-block">{{ $message }}</span> @enderror
            </div>
            <div class="col-lg-3">
                <label class="form-label fw-bold text-dark fs-7">Tahun Akademik <span class="text-danger">*</span></label>
                <select class="form-select rounded-3 border-secondary border-opacity-25" wire:model.live="periodForm.academic_year_id">
                    <option value="">-- Pilih Tahun Akademik --</option>
                    @foreach ($academicYears as $academicYear)
                        <option value="{{ $academicYear['id'] }}">{{ $academicYear['name'] }}</option>
                    @endforeach
                </select>
                @error('periodForm.academic_year_id') <span class="text-danger fs-8 mt-1 d-block">{{ $message }}</span> @enderror
            </div>
            <div class="col-lg-3">
                <label class="form-label fw-bold text-dark fs-7">Gelombang Ke- <span class="text-danger">*</span></label>
                <input type="number" min="1" max="20" class="form-control rounded-3 border-secondary border-opacity-25" wire:model.defer="periodForm.wave">
                @error('periodForm.wave') <span class="text-danger fs-8 mt-1 d-block">{{ $message }}</span> @enderror
            </div>
            <div class="col-lg-3">
                <label class="form-label fw-bold text-dark fs-7">Tanggal Buka <span class="text-danger">*</span></label>
                <input type="date" class="form-control rounded-3 border-secondary border-opacity-25" wire:model.defer="periodForm.opens_at">
                @error('periodForm.opens_at') <span class="text-danger fs-8 mt-1 d-block">{{ $message }}</span> @enderror
            </div>
            <div class="col-lg-3">
                <label class="form-label fw-bold text-dark fs-7">Tanggal Tutup <span class="text-danger">*</span></label>
                <input type="date" class="form-control rounded-3 border-secondary border-opacity-25" wire:model.defer="periodForm.closes_at">
                @error('periodForm.closes_at') <span class="text-danger fs-8 mt-1 d-block">{{ $message }}</span> @enderror
            </div>
            <div class="col-lg-3 d-flex flex-column justify-content-end pb-1">
                <div class="d-flex gap-4">
                    <div class="form-check form-switch">
                        <input id="is_active" class="form-check-input" type="checkbox" wire:model.defer="periodForm.is_active">
                        <label for="is_active" class="form-check-label fw-bold text-dark">Aktif</label>
                    </div>
                    <div class="form-check form-switch">
                        <input id="is_published" class="form-check-input" type="checkbox" wire:model.defer="periodForm.is_published">
                        <label for="is_published" class="form-check-label fw-bold text-dark">Publikasi</label>
                    </div>
                </div>
            </div>
            <div class="col-12">
                <label class="form-label fw-bold text-dark fs-7">Keterangan / Deskripsi</label>
                <textarea class="form-control rounded-3 border-secondary border-opacity-25" rows="3" wire:model.defer="periodForm.description"></textarea>
                @error('periodForm.description') <span class="text-danger fs-8 mt-1 d-block">{{ $message }}</span> @enderror
            </div>
        </div>
    </div>

    <div class="card border-0 shadow-sm rounded-4 overflow-hidden mb-4">
        <div class="card-header bg-white border-bottom p-4 d-flex justify-content-between align-items-center flex-wrap gap-2">
            <div>
                <h4 class="fw-bold mb-1 text-dark d-flex align-items-center gap-2">
                    <i class="fas fa-file-alt text-primary"></i> Persyaratan Dokumen Pendaftaran
                </h4>
                <p class="text-muted fs-7 mb-0">Atur kembali daftar dokumen digital yang diwajibkan maupun opsional untuk gelombang ini.</p>
            </div>
            <button type="button" class="btn btn-outline-primary rounded-pill px-3 py-1.5 fw-bold fs-7 shadow-sm d-flex align-items-center gap-1" wire:click="addRequirement">
                <i class="fas fa-plus-circle"></i> Tambah Syarat Dokumen
            </button>
        </div>
        <div class="card-body p-4">
            @foreach ($documentRequirements as $index => $requirement)
                <div class="row g-3 align-items-end border-bottom border-light pb-4 mb-4" wire:key="requirement-edit-{{ $index }}">
                    <div class="col-lg-3">
                        <label class="form-label fw-semibold text-dark fs-8">Tipe Dokumen (Kode)</label>
                        <input type="text" class="form-control rounded-3 fs-7 border-secondary border-opacity-25" wire:model.defer="documentRequirements.{{ $index }}.document_type">
                        @error("documentRequirements.$index.document_type") <span class="text-danger fs-8 mt-1 d-block">{{ $message }}</span> @enderror
                    </div>
                    <div class="col-lg-4">
                        <label class="form-label fw-semibold text-dark fs-8">Label yang Ditampilkan ke Pendaftar</label>
                        <input type="text" class="form-control rounded-3 fs-7 border-secondary border-opacity-25" wire:model.defer="documentRequirements.{{ $index }}.label">
                        @error("documentRequirements.$index.label") <span class="text-danger fs-8 mt-1 d-block">{{ $message }}</span> @enderror
                    </div>
                    <div class="col-lg-2">
                        <label class="form-label fw-semibold text-dark fs-8">Ekstensi Diizinkan</label>
                        <input type="text" class="form-control rounded-3 fs-7 border-secondary border-opacity-25" wire:model.defer="documentRequirements.{{ $index }}.allowed_extensions">
                    </div>
                    <div class="col-lg-2">
                        <label class="form-label fw-semibold text-dark fs-8">Maks. Ukuran (KB)</label>
                        <input type="number" class="form-control rounded-3 fs-7 border-secondary border-opacity-25" wire:model.defer="documentRequirements.{{ $index }}.max_size_kb">
                    </div>
                    <div class="col-lg-1 d-flex align-items-center justify-content-end gap-2 pb-1">
                        <div class="form-check form-switch mb-0" title="Wajib Diunggah?">
                            <input class="form-check-input" type="checkbox" wire:model.defer="documentRequirements.{{ $index }}.is_required" id="req-edit-{{ $index }}">
                            <label class="form-check-label fs-8" for="req-edit-{{ $index }}">Wajib</label>
                        </div>
                        <button type="button" class="btn btn-outline-danger btn-icon rounded-circle shadow-sm" style="width: 36px; height: 36px;" wire:click="removeRequirement({{ $index }})" title="Hapus Syarat">
                            <i class="fas fa-trash-alt fs-7"></i>
                        </button>
                    </div>
                </div>
            @endforeach
        </div>
    </div>

    <div class="d-flex justify-content-end gap-3 mb-5">
        <button type="button" class="btn btn-light rounded-pill px-4 py-2.5 fw-bold text-secondary shadow-sm border" wire:click="cancel">
            <i class="fas fa-times me-1"></i> Batal
        </button>
        <button type="button" class="btn btn-primary rounded-pill px-5 py-2.5 fw-bold shadow-sm d-flex align-items-center gap-2" wire:click="updatePeriod">
            <i class="fas fa-save"></i> Simpan Perubahan
        </button>
    </div>
</div>
