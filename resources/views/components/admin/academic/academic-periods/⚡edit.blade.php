<?php

use App\Models\Academic\AcademicPeriod;
use App\Models\Academic\AcademicYear;
use Illuminate\Validation\Rule;
use Livewire\Component;

new class extends Component
{
    public int $academicPeriodId;
    public array $academicPeriodForm = [];
    public array $availableAcademicYears = [];

    public function cancel(): void
    {
        $this->redirectRoute('admin.academic.academic-periods.index');
    }

    public function mount($id): void
    {
        $period = AcademicPeriod::findOrFail($id);
        $this->academicPeriodId = (int) $id;

        $this->academicPeriodForm = [
            'academic_year_id' => $period->academic_year_id,
            'name' => $period->name,
            'code' => $period->code,
            'type' => $period->type,
            'start_at' => optional($period->start_at)->format('Y-m-d\TH:i'),
            'end_at' => optional($period->end_at)->format('Y-m-d\TH:i'),
            'is_active' => (bool) $period->is_active,
            'desc' => $period->desc,
        ];

        $this->availableAcademicYears = AcademicYear::query()
            ->orderByDesc('start_date')
            ->get(['id', 'name'])
            ->map(fn (AcademicYear $year) => [
                'id' => $year->id,
                'name' => $year->name,
            ])
            ->toArray();
    }

    public function updateAcademicPeriod(): void
    {
        $validatedData = $this->validate([
            'academicPeriodForm.academic_year_id' => 'required|integer|exists:academic_years,id',
            'academicPeriodForm.name' => 'required|string|max:255',
            'academicPeriodForm.code' => [
                'nullable',
                'string',
                'max:50',
                Rule::unique('academic_periods', 'code')
                    ->ignore($this->academicPeriodId)
                    ->whereNull('deleted_at'),
            ],
            'academicPeriodForm.type' => 'required|in:Student Registration,Study Plan,Study Plan Revision,Grading,Exam,Remedial,Academic Leave,Yudisium,Custom',
            'academicPeriodForm.start_at' => 'required|date',
            'academicPeriodForm.end_at' => 'required|date|after:academicPeriodForm.start_at',
            'academicPeriodForm.is_active' => 'boolean',
            'academicPeriodForm.desc' => 'nullable|string',
        ]);

        $period = AcademicPeriod::findOrFail($this->academicPeriodId);

        $period->update([
            'academic_year_id' => $validatedData['academicPeriodForm']['academic_year_id'],
            'name' => $validatedData['academicPeriodForm']['name'],
            'code' => $validatedData['academicPeriodForm']['code'] ?: null,
            'type' => $validatedData['academicPeriodForm']['type'],
            'start_at' => $validatedData['academicPeriodForm']['start_at'],
            'end_at' => $validatedData['academicPeriodForm']['end_at'],
            'is_active' => (bool) $validatedData['academicPeriodForm']['is_active'],
            'desc' => $validatedData['academicPeriodForm']['desc'] ?: null,
            'updated_by' => auth()->id(),
        ]);

        session()->flash('success', 'Periode akademik berhasil diperbarui.');
        $this->redirectRoute('admin.academic.academic-periods.index');
    }

    public function render()
    {
        $data = [
            'menus' => 'Academic Management',
            'pages' => 'Edit Periode Akademik',
        ];

        return $this->view()->layout('layouts.app', $data);
    }
};
?>

<div>
    <x-alert />

    <x-admin.academic.header
        title="Edit Periode Akademik"
        description="Perbarui informasi masa operasional periode, batas waktu aktif KRS, atau penanggalan kegiatan akademik."
        icon="edit"
    >
        <button type="button" class="btn btn-sm btn-light text-dark fw-semibold d-inline-flex align-items-center gap-2 shadow-sm rounded-pill px-3 py-2" wire:click="cancel">
            <i class="fa fa-arrow-left"></i> <span>Kembali ke daftar</span>
        </button>
    </x-admin.academic.header>

    <div class="row g-4 align-items-start">
        <div class="col-lg-8">
            <div class="card border-0 shadow-sm rounded-4 overflow-hidden mb-4">
                <div class="card-header bg-white border-bottom p-4">
                    <div class="d-flex align-items-center gap-3">
                        <div class="bg-primary bg-opacity-10 text-primary rounded-3 d-flex align-items-center justify-content-center" style="width: 42px; height: 42px;">
                            <i class="fa fa-calendar-check fs-5"></i>
                        </div>
                        <div>
                            <h4 class="card-title fw-bold mb-1 text-dark">Edit Data Periode Akademik</h4>
                            <div class="text-muted small">Perbarui tahun akademik, tipe periode, nama kegiatan, serta rentang waktu mulai dan selesai.</div>
                        </div>
                    </div>
                </div>
                <div class="card-body p-4 row g-3">
                    <div class="col-md-6">
                        <label class="form-label fw-semibold" for="academic_year_id">Tahun Akademik <span class="text-danger">*</span></label>
                        <select id="academic_year_id" class="form-select" wire:model.defer="academicPeriodForm.academic_year_id">
                            <option value="">Pilih Tahun Akademik</option>
                            @foreach ($availableAcademicYears as $academicYear)
                                <option value="{{ $academicYear['id'] }}">{{ $academicYear['name'] }}</option>
                            @endforeach
                        </select>
                        @error('academicPeriodForm.academic_year_id')
                            <span class="text-danger small mt-1 d-block">{{ $message }}</span>
                        @enderror
                    </div>

                    <div class="col-md-6">
                        <label class="form-label fw-semibold" for="type">Tipe Periode <span class="text-danger">*</span></label>
                        <select id="type" class="form-select" wire:model.live="academicPeriodForm.type">
                            <option value="Student Registration">Student Registration</option>
                            <option value="Study Plan">Study Plan</option>
                            <option value="Study Plan Revision">Study Plan Revision</option>
                            <option value="Grading">Grading</option>
                            <option value="Exam">Exam</option>
                            <option value="Remedial">Remedial</option>
                            <option value="Academic Leave">Academic Leave</option>
                            <option value="Yudisium">Yudisium</option>
                            <option value="Custom">Custom</option>
                        </select>
                        @error('academicPeriodForm.type')
                            <span class="text-danger small mt-1 d-block">{{ $message }}</span>
                        @enderror
                    </div>

                    <div class="col-md-8">
                        <label class="form-label fw-semibold" for="name">Nama Periode <span class="text-danger">*</span></label>
                        <input type="text" id="name" class="form-control" wire:model.defer="academicPeriodForm.name">
                        @error('academicPeriodForm.name')
                            <span class="text-danger small mt-1 d-block">{{ $message }}</span>
                        @enderror
                    </div>

                    <div class="col-md-4">
                        <label class="form-label fw-semibold" for="code">Kode</label>
                        <input type="text" id="code" class="form-control" wire:model.defer="academicPeriodForm.code">
                        @error('academicPeriodForm.code')
                            <span class="text-danger small mt-1 d-block">{{ $message }}</span>
                        @enderror
                    </div>

                    <div class="col-md-6">
                        <label class="form-label fw-semibold" for="start_at">Tanggal & Waktu Mulai <span class="text-danger">*</span></label>
                        <input type="datetime-local" id="start_at" class="form-control" wire:model.defer="academicPeriodForm.start_at">
                        @error('academicPeriodForm.start_at')
                            <span class="text-danger small mt-1 d-block">{{ $message }}</span>
                        @enderror
                    </div>

                    <div class="col-md-6">
                        <label class="form-label fw-semibold" for="end_at">Tanggal & Waktu Selesai <span class="text-danger">*</span></label>
                        <input type="datetime-local" id="end_at" class="form-control" wire:model.defer="academicPeriodForm.end_at">
                        @error('academicPeriodForm.end_at')
                            <span class="text-danger small mt-1 d-block">{{ $message }}</span>
                        @enderror
                    </div>

                    <div class="col-12">
                        <label class="form-label fw-semibold" for="desc">Deskripsi / Keterangan</label>
                        <textarea id="desc" class="form-control" rows="3" wire:model.defer="academicPeriodForm.desc"></textarea>
                        @error('academicPeriodForm.desc')
                            <span class="text-danger small mt-1 d-block">{{ $message }}</span>
                        @enderror
                    </div>

                    <div class="col-12">
                        <div class="form-check form-switch mt-2">
                            <input id="is_active" class="form-check-input" type="checkbox" wire:model.defer="academicPeriodForm.is_active">
                            <label for="is_active" class="form-check-label fw-semibold">Jadikan Status Aktif Sekarang</label>
                        </div>
                        @error('academicPeriodForm.is_active')
                            <span class="text-danger small mt-1 d-block">{{ $message }}</span>
                        @enderror
                    </div>

                    <div class="col-12 border-top pt-3 mt-4 d-flex align-items-center justify-content-end gap-2">
                        <button class="btn btn-light rounded-pill px-4 py-2" wire:click="cancel" type="button">
                            <i class="fa fa-times me-1"></i> Batal
                        </button>
                        <button class="btn btn-primary rounded-pill px-4 py-2 shadow-sm" wire:click="updateAcademicPeriod" type="button">
                            <i class="fa fa-save me-1"></i> Simpan Perubahan
                        </button>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-lg-4">
            <div class="card border-0 shadow-sm rounded-4 mb-4 overflow-hidden">
                <div class="card-body p-4 bg-light bg-opacity-50">
                    <div class="d-flex align-items-center gap-3 mb-3">
                        <div class="bg-success bg-opacity-10 text-success rounded-3 d-flex align-items-center justify-content-center" style="width: 42px; height: 42px;">
                            <i class="fa fa-circle-info"></i>
                        </div>
                        <div>
                            <h5 class="fw-bold mb-1">Catatan Perubahan</h5>
                            <div class="text-muted small">Panduan pengeditan data.</div>
                        </div>
                    </div>

                    <ul class="list-unstyled mb-0 text-muted small d-grid gap-2">
                        <li class="d-flex gap-2"><i class="fa fa-check text-success mt-1"></i><span><strong>Konsistensi Waktu:</strong> Mengubah tanggal selesai/mulai dapat memengaruhi mahasiswa yang sedang melakukan pengisian di portal.</span></li>
                        <li class="d-flex gap-2"><i class="fa fa-check text-success mt-1"></i><span><strong>Status Aktif:</strong> Nonaktifkan periode jika sesi pendaftaran, pengisian KRS, atau penilaian sudah berakhir agar tidak ada perubahan data dari mahasiswa.</span></li>
                    </ul>
                </div>
            </div>
        </div>
    </div>
</div>
