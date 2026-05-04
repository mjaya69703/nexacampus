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

<div class="row">
    <div class="col-md-8">
        <x-alert />
        <div class="card">
            <div class="card-header">
                <h5 class="card-title">Form Tambah Kurikulum</h5>
            </div>
            <div class="card-body">
                <form wire:submit.prevent="store">
                    <div class="mb-3">
                        <label class="form-label">Program Studi <span class="text-danger">*</span></label>
                        <select class="form-select" wire:model="curriculumForm.study_program_id" required>
                            <option value="">Pilih Program Studi</option>
                            @foreach($availableStudyPrograms as $id => $name)
                                <option value="{{ $id }}">{{ $name }}</option>
                            @endforeach
                        </select>
                        @error('curriculumForm.study_program_id') <span class="text-danger">{{ $message }}</span> @enderror
                    </div>

                    <div class="mb-3">
                        <label class="form-label">Nama Kurikulum <span class="text-danger">*</span></label>
                        <input type="text" class="form-control" wire:model="curriculumForm.name" placeholder="Contoh: Kurikulum 2024" required>
                        @error('curriculumForm.name') <span class="text-danger">{{ $message }}</span> @enderror
                    </div>

                    <div class="mb-3">
                        <label class="form-label">Kode Kurikulum</label>
                        <input type="text" class="form-control" wire:model="curriculumForm.code" placeholder="Contoh: K2024">
                        @error('curriculumForm.code') <span class="text-danger">{{ $message }}</span> @enderror
                    </div>

                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Tahun Mulai</label>
                            <input type="number" class="form-control" wire:model="curriculumForm.start_year" placeholder="Contoh: 2024" min="1900">
                            @error('curriculumForm.start_year') <span class="text-danger">{{ $message }}</span> @enderror
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Tahun Akhir</label>
                            <input type="number" class="form-control" wire:model="curriculumForm.end_year" placeholder="Contoh: 2028" min="1900">
                            @error('curriculumForm.end_year') <span class="text-danger">{{ $message }}</span> @enderror
                        </div>
                    </div>

                    <div class="mb-3">
                        <div class="form-check form-switch">
                            <input class="form-check-input" type="checkbox" wire:model="curriculumForm.is_active">
                            <label class="form-check-label">Kurikulum Aktif</label>
                        </div>
                    </div>

                    <div class="mb-3">
                        <label class="form-label">Deskripsi</label>
                        <textarea class="form-control" wire:model="curriculumForm.desc" rows="3" placeholder="Tambahkan deskripsi kurikulum"></textarea>
                        @error('curriculumForm.desc') <span class="text-danger">{{ $message }}</span> @enderror
                    </div>

                    <div class="text-end">
                        <button type="submit" class="btn btn-primary">
                            <i class="fas fa-save me-2"></i> Simpan
                        </button>
                        <button type="button" class="btn btn-secondary" wire:click="cancel">
                            <i class="fas fa-times me-2"></i> Batal
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>
