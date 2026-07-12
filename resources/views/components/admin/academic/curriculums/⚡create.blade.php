<?php

use Livewire\Component;
use App\Models\Academic\Curriculum;
use App\Models\Academic\StudyProgram;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

new class extends Component {
    public $curriculumForm = [];
    public array $availableStudyPrograms = [];

    public function mount(): void
    {
        $this->availableStudyPrograms = StudyProgram::where('is_active', true)
            ->pluck('name', 'id')
            ->toArray();

        $this->curriculumForm['is_active'] = false;
    }

    public function cancel(): void
    {
        $this->redirect(route('admin.academic.curriculums.index'));
    }

    public function store(): void
    {
        $studyProgramId = $this->curriculumForm['study_program_id'] ?? null;

        $validatedData = $this->validate([
            'curriculumForm.study_program_id' => 'required|exists:study_programs,id',
            'curriculumForm.name' => [
                'required',
                'string',
                'max:255',
                Rule::unique('curriculums', 'name')->where(fn ($query) => $query->where('study_program_id', $studyProgramId)),
            ],
            'curriculumForm.code' => [
                'nullable',
                'string',
                'max:50',
                Rule::unique('curriculums', 'code')->where(fn ($query) => $query->where('study_program_id', $studyProgramId)),
            ],
            'curriculumForm.start_year' => 'nullable|integer|min:1900|max:' . date('Y'),
            'curriculumForm.end_year' => 'nullable|integer|min:1900|max:' . (date('Y') + 10),
            'curriculumForm.is_active' => 'nullable|boolean',
            'curriculumForm.desc' => 'nullable|string',
        ]);

        DB::beginTransaction();

        try {
            $curriculum = Curriculum::create(array_merge(
                $validatedData['curriculumForm'],
                ['created_by' => auth()->id()]
            ));

            DB::commit();
            session()->flash('success', 'Kurikulum berhasil ditambahkan!');
            $this->redirect(route('admin.academic.curriculums.edit', ['id' => $curriculum->id]));
        } catch (\Exception $e) {
            DB::rollBack();
            session()->flash('error', 'Terjadi kesalahan: ' . $e->getMessage());
        }
    }

    public function render()
    {
        return $this->view()
            ->layout('layouts.app', [
                'menus' => 'Academic',
                'pages' => 'Tambah Kurikulum',
            ]);
    }
};
?>

<div>
    <x-alert />

    <x-admin.academic.header
        title="Tambah Kurikulum Baru"
        description="Buat rancangan kurikulum akademik baru untuk mengatur daftar mata kuliah yang akan dipelajari oleh angkatan mahasiswa tertentu."
        icon="plus-circle"
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
                            <i class="fa fa-book-open fs-5"></i>
                        </div>
                        <div>
                            <h4 class="card-title fw-bold mb-1 text-dark">Formulir Kurikulum</h4>
                            <div class="text-muted small">Tentukan program studi, nama kurikulum, kode, tahun berlaku, serta status keaktifan.</div>
                        </div>
                    </div>
                </div>
                <div class="card-body p-4">
                    <form wire:submit.prevent="store" class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label fw-semibold">Program Studi <span class="text-danger">*</span></label>
                            <select class="form-select" wire:model="curriculumForm.study_program_id" required>
                                <option value="">Pilih Program Studi</option>
                                @foreach($availableStudyPrograms as $id => $name)
                                    <option value="{{ $id }}">{{ $name }}</option>
                                @endforeach
                            </select>
                            @error('curriculumForm.study_program_id') <span class="text-danger small mt-1 d-block">{{ $message }}</span> @enderror
                        </div>

                        <div class="col-md-6">
                            <label class="form-label fw-semibold">Nama Kurikulum <span class="text-danger">*</span></label>
                            <input type="text" class="form-control" wire:model="curriculumForm.name" placeholder="Contoh: Kurikulum Merdeka 2024" required>
                            @error('curriculumForm.name') <span class="text-danger small mt-1 d-block">{{ $message }}</span> @enderror
                        </div>

                        <div class="col-md-4">
                            <label class="form-label fw-semibold">Kode Kurikulum</label>
                            <input type="text" class="form-control" wire:model="curriculumForm.code" placeholder="Contoh: KUR-2024">
                            @error('curriculumForm.code') <span class="text-danger small mt-1 d-block">{{ $message }}</span> @enderror
                        </div>

                        <div class="col-md-4">
                            <label class="form-label fw-semibold">Tahun Mulai</label>
                            <input type="number" class="form-control" wire:model="curriculumForm.start_year" placeholder="Contoh: 2024" min="1900">
                            @error('curriculumForm.start_year') <span class="text-danger small mt-1 d-block">{{ $message }}</span> @enderror
                        </div>

                        <div class="col-md-4">
                            <label class="form-label fw-semibold">Tahun Akhir</label>
                            <input type="number" class="form-control" wire:model="curriculumForm.end_year" placeholder="Contoh: 2028" min="1900">
                            @error('curriculumForm.end_year') <span class="text-danger small mt-1 d-block">{{ $message }}</span> @enderror
                        </div>

                        <div class="col-12">
                            <label class="form-label fw-semibold">Deskripsi</label>
                            <textarea class="form-control" wire:model="curriculumForm.desc" rows="3" placeholder="Tambahkan deskripsi atau landasan kurikulum ini"></textarea>
                            @error('curriculumForm.desc') <span class="text-danger small mt-1 d-block">{{ $message }}</span> @enderror
                        </div>

                        <div class="col-12">
                            <div class="form-check form-switch mt-2">
                                <input class="form-check-input" type="checkbox" wire:model="curriculumForm.is_active" id="is_active">
                                <label class="form-check-label fw-semibold" for="is_active">Jadikan Kurikulum Aktif Sekarang</label>
                            </div>
                            @error('curriculumForm.is_active') <span class="text-danger small mt-1 d-block">{{ $message }}</span> @enderror
                        </div>

                        <div class="col-12 border-top pt-3 mt-4 d-flex align-items-center justify-content-end gap-2">
                            <button type="button" class="btn btn-light rounded-pill px-4 py-2" wire:click="cancel">
                                <i class="fa fa-times me-1"></i> Batal
                            </button>
                            <button type="submit" class="btn btn-primary rounded-pill px-4 py-2 shadow-sm">
                                <i class="fa fa-save me-1"></i> Simpan & Lanjutkan
                            </button>
                        </div>
                    </form>
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
                            <h5 class="fw-bold mb-1">Pedoman Kurikulum</h5>
                            <div class="text-muted small">Panduan pembuatan kurikulum.</div>
                        </div>
                    </div>

                    <ul class="list-unstyled mb-0 text-muted small d-grid gap-2">
                        <li class="d-flex gap-2"><i class="fa fa-check text-success mt-1"></i><span><strong>Alur Kerja:</strong> Setelah kurikulum disimpan, Anda akan diarahkan ke halaman pengelolaan mata kuliah untuk menambahkan sebaran mata kuliah per semester.</span></li>
                        <li class="d-flex gap-2"><i class="fa fa-check text-success mt-1"></i><span><strong>Rentang Tahun:</strong> Tentukan tahun mulai dan tahun akhir berlakunya kurikulum untuk acuan mahasiswa baru angkatan tersebut.</span></li>
                        <li class="d-flex gap-2"><i class="fa fa-check text-success mt-1"></i><span><strong>Keunikan Nama:</strong> Nama kurikulum tidak boleh duplikat pada Program Studi yang sama.</span></li>
                    </ul>
                </div>
            </div>
        </div>
    </div>
</div>
