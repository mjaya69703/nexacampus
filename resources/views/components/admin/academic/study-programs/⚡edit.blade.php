<?php

use App\Models\Academic\Faculty;
use App\Models\Academic\StudyProgram;
use Illuminate\Validation\Rule;
use Livewire\Component;

new class extends Component
{
    public int $studyProgramId;
    public array $studyProgramForm = [];
    public array $availableFaculties = [];

    public function cancel(): void
    {
        $this->redirectRoute('admin.academic.study-programs.index');
    }

    public function mount($id): void
    {
        $studyProgram = StudyProgram::findOrFail($id);
        $this->studyProgramId = (int) $id;

        $this->studyProgramForm = [
            'faculty_id' => $studyProgram->faculty_id,
            'name' => $studyProgram->name,
            'code' => $studyProgram->code,
            'short_name' => $studyProgram->short_name,
            'degree' => $studyProgram->degree,
            'prefix_degree' => $studyProgram->prefix_degree,
            'suffix_degree' => $studyProgram->suffix_degree,
            'is_active' => (bool) $studyProgram->is_active,
            'desc' => $studyProgram->desc,
        ];

        $this->availableFaculties = Faculty::query()
            ->orderBy('name')
            ->get(['id', 'name'])
            ->map(fn (Faculty $faculty) => [
                'id' => $faculty->id,
                'name' => $faculty->name,
            ])
            ->toArray();
    }

    public function updateStudyProgram(): void
    {
        $validatedData = $this->validate([
            'studyProgramForm.faculty_id' => 'nullable|exists:faculties,id',
            'studyProgramForm.name' => 'required|string|max:255',
            'studyProgramForm.code' => [
                'required',
                'string',
                'max:20',
                Rule::unique('study_programs', 'code')->ignore($this->studyProgramId),
            ],
            'studyProgramForm.short_name' => 'nullable|string|max:50',
            'studyProgramForm.degree' => 'required|in:D3,D4,S1,S2,S3',
            'studyProgramForm.prefix_degree' => 'nullable|string|max:255',
            'studyProgramForm.suffix_degree' => 'nullable|string|max:255',
            'studyProgramForm.is_active' => 'boolean',
            'studyProgramForm.desc' => 'nullable|string',
        ]);

        $faculty = null;

        if (! empty($validatedData['studyProgramForm']['faculty_id'])) {
            $faculty = Faculty::find($validatedData['studyProgramForm']['faculty_id']);
        }

        $isActive = (bool) $validatedData['studyProgramForm']['is_active'];

        if ($isActive && (! $faculty || ! $faculty->is_active)) {
            $this->addError('studyProgramForm.faculty_id', 'Program studi aktif harus terhubung ke fakultas yang aktif.');

            return;
        }

        $studyProgram = StudyProgram::findOrFail($this->studyProgramId);

        $studyProgram->update([
            'faculty_id' => $validatedData['studyProgramForm']['faculty_id'] ?: null,
            'name' => $validatedData['studyProgramForm']['name'],
            'code' => $validatedData['studyProgramForm']['code'],
            'short_name' => $validatedData['studyProgramForm']['short_name'] ?: null,
            'degree' => $validatedData['studyProgramForm']['degree'],
            'prefix_degree' => $validatedData['studyProgramForm']['prefix_degree'] ?: null,
            'suffix_degree' => $validatedData['studyProgramForm']['suffix_degree'] ?: null,
            'is_active' => $isActive,
            'desc' => $validatedData['studyProgramForm']['desc'] ?: null,
            'updated_by' => auth()->id(),
        ]);

        session()->flash('success', 'Program studi berhasil diperbarui.');
        $this->redirectRoute('admin.academic.study-programs.index');
    }

    public function render()
    {
        $data = [
            'menus' => 'Academic Management',
            'pages' => 'Edit Program Studi',
        ];

        return $this->view()->layout('layouts.app', $data);
    }
};
?>

<div>
    <x-alert />
    <div class="card">
        <div class="card-header d-flex justify-content-between align-items-center">
            <h3 class="card-title">Edit Program Studi {{ $studyProgramForm['name'] ?? '' }}</h3>
        </div>
        <div class="card-body row">
            <div class="form-group col-lg-6 col-md-6 col-sm-12 mt-2">
                <label for="faculty_id">Fakultas</label>
                <select id="faculty_id" class="form-control" wire:model.defer="studyProgramForm.faculty_id">
                    <option value="">Pilih Fakultas</option>
                    @foreach ($availableFaculties as $faculty)
                        <option value="{{ $faculty['id'] }}">{{ $faculty['name'] }}</option>
                    @endforeach
                </select>
                @error('studyProgramForm.faculty_id')
                    <span class="text-danger">{{ $message }}</span>
                @enderror
            </div>
            <div class="form-group col-lg-6 col-md-6 col-sm-12 mt-2">
                <label for="name">Nama Program Studi</label>
                <input type="text" id="name" class="form-control" wire:model.defer="studyProgramForm.name">
                @error('studyProgramForm.name')
                    <span class="text-danger">{{ $message }}</span>
                @enderror
            </div>
            <div class="form-group col-lg-4 col-md-6 col-sm-12 mt-2">
                <label for="code">Kode</label>
                <input type="text" id="code" class="form-control" wire:model.defer="studyProgramForm.code">
                @error('studyProgramForm.code')
                    <span class="text-danger">{{ $message }}</span>
                @enderror
            </div>
            <div class="form-group col-lg-4 col-md-6 col-sm-12 mt-2">
                <label for="short_name">Nama Singkat</label>
                <input type="text" id="short_name" class="form-control" wire:model.defer="studyProgramForm.short_name">
                @error('studyProgramForm.short_name')
                    <span class="text-danger">{{ $message }}</span>
                @enderror
            </div>
            <div class="form-group col-lg-4 col-md-6 col-sm-12 mt-2">
                <label for="degree">Jenjang</label>
                <select id="degree" class="form-control" wire:model.defer="studyProgramForm.degree">
                    <option value="D3">D3</option>
                    <option value="D4">D4</option>
                    <option value="S1">S1</option>
                    <option value="S2">S2</option>
                    <option value="S3">S3</option>
                </select>
                @error('studyProgramForm.degree')
                    <span class="text-danger">{{ $message }}</span>
                @enderror
            </div>
            <div class="form-group col-lg-6 col-md-6 col-sm-12 mt-2">
                <label for="prefix_degree">Prefix Gelar</label>
                <input type="text" id="prefix_degree" class="form-control" wire:model.defer="studyProgramForm.prefix_degree">
                @error('studyProgramForm.prefix_degree')
                    <span class="text-danger">{{ $message }}</span>
                @enderror
            </div>
            <div class="form-group col-lg-6 col-md-6 col-sm-12 mt-2">
                <label for="suffix_degree">Suffix Gelar</label>
                <input type="text" id="suffix_degree" class="form-control" wire:model.defer="studyProgramForm.suffix_degree">
                @error('studyProgramForm.suffix_degree')
                    <span class="text-danger">{{ $message }}</span>
                @enderror
            </div>
            <div class="form-group col-12 mt-2">
                <label for="desc">Deskripsi</label>
                <textarea id="desc" class="form-control" rows="3" wire:model.defer="studyProgramForm.desc"></textarea>
                @error('studyProgramForm.desc')
                    <span class="text-danger">{{ $message }}</span>
                @enderror
            </div>
            <div class="form-group col-12 mt-2">
                <div class="form-check form-switch mt-2">
                    <input id="is_active" class="form-check-input" type="checkbox" wire:model.defer="studyProgramForm.is_active">
                    <label for="is_active" class="form-check-label">Aktif</label>
                </div>
                @error('studyProgramForm.is_active')
                    <span class="text-danger">{{ $message }}</span>
                @enderror
            </div>

            <div class="form-group col-12 mt-4 d-flex align-items-center justify-content-end gap-2">
                <button class="btn btn-primary" wire:click="updateStudyProgram">
                    <i class="fas fa-save me-2"></i> Simpan Perubahan
                </button>
                <button class="btn btn-secondary" wire:click="cancel">
                    <i class="fas fa-times me-2"></i> Batal
                </button>
            </div>
        </div>
    </div>
</div>
