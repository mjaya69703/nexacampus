<?php

use App\Models\Academic\Course;
use App\Models\Academic\CourseScope;
use App\Models\Academic\Faculty;
use App\Models\Academic\StudyProgram;
use Livewire\Component;

new class extends Component
{
    public array $courseForm = [];
    public array $availableFaculties = [];
    public array $availableStudyPrograms = [];
    public array $availablePrerequisites = [];
    public array $selectedPrerequisites = [];

    public function cancel(): void
    {
        $this->redirectRoute('admin.academic.courses.index');
    }

    public function mount(): void
    {
        $this->courseForm = [
            'scope_type' => 'global',
            'scope_id' => null,
            'code' => '',
            'name' => '',
            'short_name' => '',
            'credits' => 2,
            'semester_recommendation' => null,
            'requirement_type' => 'Wajib',
            'category_type' => 'Keilmuan',
            'is_active' => true,
            'desc' => '',
        ];

        $this->availableFaculties = Faculty::query()
            ->orderBy('name')
            ->get(['id', 'name', 'is_active'])
            ->map(fn (Faculty $faculty) => [
                'id' => $faculty->id,
                'name' => $faculty->name,
                'is_active' => (bool) $faculty->is_active,
            ])
            ->toArray();

        $this->availableStudyPrograms = StudyProgram::query()
            ->orderBy('name')
            ->get(['id', 'name', 'is_active'])
            ->map(fn (StudyProgram $studyProgram) => [
                'id' => $studyProgram->id,
                'name' => $studyProgram->name,
                'is_active' => (bool) $studyProgram->is_active,
            ])
            ->toArray();

        $this->availablePrerequisites = Course::query()
            ->orderBy('code')
            ->get(['id', 'code', 'name'])
            ->map(fn (Course $course) => [
                'id' => $course->id,
                'label' => $course->code . ' - ' . $course->name,
            ])
            ->toArray();
    }

    public function createCourse(): void
    {
        $validatedData = $this->validate([
            'courseForm.scope_type' => 'required|in:faculty,study_program,global',
            'courseForm.scope_id' => 'nullable|integer',
            'courseForm.code' => 'required|string|max:20',
            'courseForm.name' => 'required|string|max:255',
            'courseForm.short_name' => 'nullable|string|max:100',
            'courseForm.credits' => 'required|integer|min:1|max:24',
            'courseForm.semester_recommendation' => 'nullable|integer|min:1|max:14',
            'courseForm.requirement_type' => 'required|in:Wajib,Pilihan',
            'courseForm.category_type' => 'required|in:Umum,MKWU,MKU,Keilmuan,Praktikum,Tugas Akhir,Magang',
            'courseForm.is_active' => 'boolean',
            'courseForm.desc' => 'nullable|string',
            'selectedPrerequisites' => 'nullable|array',
            'selectedPrerequisites.*' => 'integer|exists:courses,id',
        ]);

        $scopeType = $validatedData['courseForm']['scope_type'];
        $scopeId = $validatedData['courseForm']['scope_id'] ?: null;
        $isActive = (bool) $validatedData['courseForm']['is_active'];

        if ($scopeType === 'global') {
            $scopeId = null;
        }

        if ($scopeType === 'faculty') {
            $faculty = Faculty::find($scopeId);

            if (! $faculty) {
                $this->addError('courseForm.scope_id', 'Fakultas tidak ditemukan.');

                return;
            }

            if ($isActive && ! $faculty->is_active) {
                $this->addError('courseForm.scope_id', 'Mata kuliah aktif harus berada pada fakultas yang aktif.');

                return;
            }
        }

        if ($scopeType === 'study_program') {
            $studyProgram = StudyProgram::find($scopeId);

            if (! $studyProgram) {
                $this->addError('courseForm.scope_id', 'Program studi tidak ditemukan.');

                return;
            }

            if ($isActive && ! $studyProgram->is_active) {
                $this->addError('courseForm.scope_id', 'Mata kuliah aktif harus berada pada program studi yang aktif.');

                return;
            }
        }

        $isCodeExist = Course::query()
            ->where('code', $validatedData['courseForm']['code'])
            ->exists();

        if ($isCodeExist) {
            $this->addError('courseForm.code', 'Kode mata kuliah sudah digunakan.');

            return;
        }

        $course = Course::create([
            'code' => $validatedData['courseForm']['code'],
            'name' => $validatedData['courseForm']['name'],
            'short_name' => $validatedData['courseForm']['short_name'] ?: null,
            'credits' => $validatedData['courseForm']['credits'],
            'semester_recommendation' => $validatedData['courseForm']['semester_recommendation'] ?: null,
            'requirement_type' => $validatedData['courseForm']['requirement_type'],
            'category_type' => $validatedData['courseForm']['category_type'],
            'is_active' => $isActive,
            'desc' => $validatedData['courseForm']['desc'] ?: null,
            'created_by' => auth()->id(),
        ]);

        CourseScope::create([
            'course_id' => $course->id,
            'scope_type' => $scopeType,
            'scope_id' => $scopeId,
            'created_by' => auth()->id(),
        ]);

        $course->prerequisites()->sync($validatedData['selectedPrerequisites'] ?? []);

        session()->flash('success', 'Mata kuliah berhasil ditambahkan.');
        $this->redirectRoute('admin.academic.courses.index');
    }

    public function render()
    {
        $data = [
            'menus' => 'Academic Management',
            'pages' => 'Tambah Mata Kuliah',
        ];

        return $this->view()->layout('layouts.app', $data);
    }
};
?>

<div>
    <x-alert />
    <div class="card">
        <div class="card-header d-flex justify-content-between align-items-center">
            <h3 class="card-title">Tambah Mata Kuliah</h3>
        </div>
        <div class="card-body row">
            <div class="form-group col-lg-6 col-md-6 col-sm-12 mt-2">
                <label for="scope_type">Scope Mata Kuliah</label>
                <select id="scope_type" class="form-control" wire:model.live="courseForm.scope_type">
                    <option value="global">Global</option>
                    <option value="faculty">Fakultas</option>
                    <option value="study_program">Program Studi</option>
                </select>
                @error('courseForm.scope_type')
                    <span class="text-danger">{{ $message }}</span>
                @enderror
            </div>

            <div class="form-group col-lg-6 col-md-6 col-sm-12 mt-2">
                <label for="scope_id">Tujuan Scope</label>
                <select id="scope_id" class="form-control" wire:model.defer="courseForm.scope_id" @disabled(($courseForm['scope_type'] ?? 'global') === 'global')>
                    <option value="">Pilih Tujuan</option>
                    @if (($courseForm['scope_type'] ?? 'global') === 'faculty')
                        @foreach ($availableFaculties as $faculty)
                            <option value="{{ $faculty['id'] }}">{{ $faculty['name'] }}@if(!$faculty['is_active']) (Nonaktif) @endif</option>
                        @endforeach
                    @elseif (($courseForm['scope_type'] ?? 'global') === 'study_program')
                        @foreach ($availableStudyPrograms as $studyProgram)
                            <option value="{{ $studyProgram['id'] }}">{{ $studyProgram['name'] }}@if(!$studyProgram['is_active']) (Nonaktif) @endif</option>
                        @endforeach
                    @endif
                </select>
                @error('courseForm.scope_id')
                    <span class="text-danger">{{ $message }}</span>
                @enderror
            </div>
            <div class="form-group col-lg-3 col-md-6 col-sm-12 mt-2">
                <label for="code">Kode</label>
                <input type="text" id="code" class="form-control" wire:model.defer="courseForm.code">
                @error('courseForm.code')
                    <span class="text-danger">{{ $message }}</span>
                @enderror
            </div>
            <div class="form-group col-lg-3 col-md-6 col-sm-12 mt-2">
                <label for="short_name">Nama Singkat</label>
                <input type="text" id="short_name" class="form-control" wire:model.defer="courseForm.short_name">
                @error('courseForm.short_name')
                    <span class="text-danger">{{ $message }}</span>
                @enderror
            </div>
            <div class="form-group col-lg-6 col-md-12 col-sm-12 mt-2">
                <label for="name">Nama Mata Kuliah</label>
                <input type="text" id="name" class="form-control" wire:model.defer="courseForm.name">
                @error('courseForm.name')
                    <span class="text-danger">{{ $message }}</span>
                @enderror
            </div>
            <div class="form-group col-lg-2 col-md-4 col-sm-12 mt-2">
                <label for="credits">SKS</label>
                <input type="number" id="credits" class="form-control" min="1" max="24" wire:model.defer="courseForm.credits">
                @error('courseForm.credits')
                    <span class="text-danger">{{ $message }}</span>
                @enderror
            </div>
            <div class="form-group col-lg-4 col-md-8 col-sm-12 mt-2">
                <label for="semester_recommendation">Semester Rekomendasi</label>
                <input type="number" id="semester_recommendation" class="form-control" min="1" max="14" wire:model.defer="courseForm.semester_recommendation">
                @error('courseForm.semester_recommendation')
                    <span class="text-danger">{{ $message }}</span>
                @enderror
            </div>
            <div class="form-group col-lg-6 col-md-6 col-sm-12 mt-2">
                <label for="requirement_type">Jenis Kebutuhan</label>
                <select id="requirement_type" class="form-control" wire:model.defer="courseForm.requirement_type">
                    <option value="Wajib">Wajib</option>
                    <option value="Pilihan">Pilihan</option>
                </select>
                @error('courseForm.requirement_type')
                    <span class="text-danger">{{ $message }}</span>
                @enderror
            </div>
            <div class="form-group col-lg-6 col-md-6 col-sm-12 mt-2">
                <label for="category_type">Kategori</label>
                <select id="category_type" class="form-control" wire:model.defer="courseForm.category_type">
                    <option value="Umum">Umum</option>
                    <option value="MKWU">MKWU</option>
                    <option value="MKU">MKU</option>
                    <option value="Keilmuan">Keilmuan</option>
                    <option value="Praktikum">Praktikum</option>
                    <option value="Tugas Akhir">Tugas Akhir</option>
                    <option value="Magang">Magang</option>
                </select>
                @error('courseForm.category_type')
                    <span class="text-danger">{{ $message }}</span>
                @enderror
            </div>
            <div class="form-group col-12 mt-2">
                <label for="prerequisites">Prasyarat Mata Kuliah</label>
                <select id="prerequisites" class="form-control" multiple wire:model.defer="selectedPrerequisites">
                    @foreach ($availablePrerequisites as $prerequisite)
                        <option value="{{ $prerequisite['id'] }}">{{ $prerequisite['label'] }}</option>
                    @endforeach
                </select>
                @error('selectedPrerequisites')
                    <span class="text-danger">{{ $message }}</span>
                @enderror
                @error('selectedPrerequisites.*')
                    <span class="text-danger">{{ $message }}</span>
                @enderror
            </div>
            <div class="form-group col-12 mt-2">
                <label for="desc">Deskripsi</label>
                <textarea id="desc" class="form-control" rows="3" wire:model.defer="courseForm.desc"></textarea>
                @error('courseForm.desc')
                    <span class="text-danger">{{ $message }}</span>
                @enderror
            </div>
            <div class="form-group col-12 mt-2">
                <div class="form-check form-switch mt-2">
                    <input id="is_active" class="form-check-input" type="checkbox" wire:model.defer="courseForm.is_active">
                    <label for="is_active" class="form-check-label">Aktif</label>
                </div>
                @error('courseForm.is_active')
                    <span class="text-danger">{{ $message }}</span>
                @enderror
            </div>

            <div class="form-group col-12 mt-4 d-flex align-items-center justify-content-end gap-2">
                <button class="btn btn-primary" wire:click="createCourse">
                    <i class="fas fa-save me-2"></i> Simpan Perubahan
                </button>
                <button class="btn btn-secondary" wire:click="cancel">
                    <i class="fas fa-times me-2"></i> Batal
                </button>
            </div>
        </div>
    </div>
</div>
