<?php

use App\Models\Academic\Course;
use App\Models\Academic\CourseScope;
use App\Models\Academic\Faculty;
use App\Models\Academic\StudyProgram;
use Livewire\Component;

new class extends Component
{
    public int $courseId;
    public array $courseForm = [];
    public array $availableFaculties = [];
    public array $availableStudyPrograms = [];
    public array $availablePrerequisites = [];
    public array $selectedPrerequisites = [];

    public function cancel(): void
    {
        $this->redirectRoute('admin.academic.courses.index');
    }

    public function mount($id): void
    {
        $course = Course::query()->with(['prerequisites', 'latestScope'])->findOrFail($id);
        $this->courseId = (int) $id;
        $scope = $course->latestScope;

        $this->courseForm = [
            'scope_type' => $scope?->scope_type ?? 'global',
            'scope_id' => $scope?->scope_id,
            'code' => $course->code,
            'name' => $course->name,
            'short_name' => $course->short_name,
            'credits' => $course->credits,
            'semester_recommendation' => $course->semester_recommendation,
            'requirement_type' => $course->requirement_type,
            'category_type' => $course->category_type,
            'is_active' => (bool) $course->is_active,
            'desc' => $course->desc,
        ];

        $this->selectedPrerequisites = $course->prerequisites->pluck('id')->map(fn ($id) => (int) $id)->toArray();

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
            ->where('id', '!=', $this->courseId)
            ->orderBy('code')
            ->get(['id', 'code', 'name'])
            ->map(fn (Course $courseItem) => [
                'id' => $courseItem->id,
                'label' => $courseItem->code . ' - ' . $courseItem->name,
            ])
            ->toArray();
    }

    public function updateCourse(): void
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

        if (in_array($this->courseId, $validatedData['selectedPrerequisites'] ?? [], true)) {
            $this->addError('selectedPrerequisites', 'Mata kuliah tidak bisa menjadi prasyarat untuk dirinya sendiri.');

            return;
        }

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
            ->where('id', '!=', $this->courseId)
            ->exists();

        if ($isCodeExist) {
            $this->addError('courseForm.code', 'Kode mata kuliah sudah digunakan.');

            return;
        }

        $course = Course::findOrFail($this->courseId);

        $course->update([
            'code' => $validatedData['courseForm']['code'],
            'name' => $validatedData['courseForm']['name'],
            'short_name' => $validatedData['courseForm']['short_name'] ?: null,
            'credits' => $validatedData['courseForm']['credits'],
            'semester_recommendation' => $validatedData['courseForm']['semester_recommendation'] ?: null,
            'requirement_type' => $validatedData['courseForm']['requirement_type'],
            'category_type' => $validatedData['courseForm']['category_type'],
            'is_active' => $isActive,
            'desc' => $validatedData['courseForm']['desc'] ?: null,
            'updated_by' => auth()->id(),
        ]);

        $course->scopes()->delete();
        CourseScope::create([
            'course_id' => $course->id,
            'scope_type' => $scopeType,
            'scope_id' => $scopeId,
            'created_by' => auth()->id(),
        ]);

        $course->prerequisites()->sync($validatedData['selectedPrerequisites'] ?? []);

        session()->flash('success', 'Mata kuliah berhasil diperbarui.');
        $this->redirectRoute('admin.academic.courses.index');
    }

    public function render()
    {
        $data = [
            'menus' => 'Academic Management',
            'pages' => 'Edit Mata Kuliah',
        ];

        return $this->view()->layout('layouts.app', $data);
    }
};
?>

<div>
    <x-alert />

    <x-admin.academic.header
        title="Edit Mata Kuliah: {{ $courseForm['name'] ?? '' }}"
        description="Perbarui data mata kuliah, bobot SKS, prasyarat, serta lingkup unit penanggung jawab."
        icon="book"
    >
        <button type="button" class="btn btn-sm btn-light text-dark fw-semibold d-inline-flex align-items-center gap-2 shadow-sm rounded-pill px-3 py-2" wire:click="cancel">
            <i class="fa fa-arrow-left"></i> <span>Kembali ke daftar</span>
        </button>
    </x-admin.academic.header>

    <form wire:submit.prevent="updateCourse">
        <div class="row g-4 align-items-start">
            <div class="col-lg-8">
                <div class="card border-0 shadow-sm rounded-4 overflow-hidden mb-4">
                    <div class="card-header bg-white border-bottom p-4">
                        <div class="d-flex align-items-center gap-3">
                            <div class="bg-primary bg-opacity-10 text-primary rounded-3 d-flex align-items-center justify-content-center" style="width: 42px; height: 42px;">
                                <i class="fa fa-book fs-5"></i>
                            </div>
                            <div>
                                <h4 class="card-title fw-bold mb-1 text-dark">Edit Data Mata Kuliah</h4>
                                <div class="text-muted small">Perbarui informasi kode, bobot kredit SKS, kategori keilmuan, serta sifat mata kuliah.</div>
                            </div>
                        </div>
                    </div>
                    <div class="card-body p-4">
                        <div class="row g-3">
                            <div class="col-md-4">
                                <label class="form-label fw-semibold" for="code">Kode Mata Kuliah <span class="text-danger">*</span></label>
                                <input type="text" id="code" class="form-control" wire:model.defer="courseForm.code" placeholder="Contoh: IF101">
                                @error('courseForm.code') <span class="text-danger small mt-1 d-block">{{ $message }}</span> @enderror
                            </div>

                            <div class="col-md-8">
                                <label class="form-label fw-semibold" for="name">Nama Mata Kuliah <span class="text-danger">*</span></label>
                                <input type="text" id="name" class="form-control" wire:model.defer="courseForm.name" placeholder="Contoh: Algoritma & Pemrograman">
                                @error('courseForm.name') <span class="text-danger small mt-1 d-block">{{ $message }}</span> @enderror
                            </div>

                            <div class="col-md-4">
                                <label class="form-label fw-semibold" for="short_name">Nama Singkat (Opsional)</label>
                                <input type="text" id="short_name" class="form-control" wire:model.defer="courseForm.short_name" placeholder="Contoh: ALPRO">
                                @error('courseForm.short_name') <span class="text-danger small mt-1 d-block">{{ $message }}</span> @enderror
                            </div>

                            <div class="col-md-4">
                                <label class="form-label fw-semibold" for="credits">Bobot SKS <span class="text-danger">*</span></label>
                                <input type="number" id="credits" class="form-control" min="1" max="24" wire:model.defer="courseForm.credits" placeholder="3">
                                @error('courseForm.credits') <span class="text-danger small mt-1 d-block">{{ $message }}</span> @enderror
                            </div>

                            <div class="col-md-4">
                                <label class="form-label fw-semibold" for="semester_recommendation">Semester Rekomendasi</label>
                                <input type="number" id="semester_recommendation" class="form-control" min="1" max="14" wire:model.defer="courseForm.semester_recommendation" placeholder="Contoh: 1">
                                @error('courseForm.semester_recommendation') <span class="text-danger small mt-1 d-block">{{ $message }}</span> @enderror
                            </div>

                            <div class="col-md-6">
                                <label class="form-label fw-semibold" for="requirement_type">Sifat Mata Kuliah <span class="text-danger">*</span></label>
                                <select id="requirement_type" class="form-select" wire:model.defer="courseForm.requirement_type">
                                    <option value="Wajib">Wajib</option>
                                    <option value="Pilihan">Pilihan</option>
                                </select>
                                @error('courseForm.requirement_type') <span class="text-danger small mt-1 d-block">{{ $message }}</span> @enderror
                            </div>

                            <div class="col-md-6">
                                <label class="form-label fw-semibold" for="category_type">Kategori Keilmuan <span class="text-danger">*</span></label>
                                <select id="category_type" class="form-select" wire:model.defer="courseForm.category_type">
                                    <option value="Umum">Umum</option>
                                    <option value="MKWU">MKWU</option>
                                    <option value="MKU">MKU</option>
                                    <option value="Keilmuan">Keilmuan</option>
                                    <option value="Praktikum">Praktikum</option>
                                    <option value="Tugas Akhir">Tugas Akhir</option>
                                    <option value="Magang">Magang</option>
                                </select>
                                @error('courseForm.category_type') <span class="text-danger small mt-1 d-block">{{ $message }}</span> @enderror
                            </div>

                            <div class="col-12">
                                <label class="form-label fw-semibold" for="desc">Deskripsi Mata Kuliah</label>
                                <textarea id="desc" class="form-control" rows="4" wire:model.defer="courseForm.desc" placeholder="Tuliskan silabus singkat atau deskripsi mata kuliah..."></textarea>
                                @error('courseForm.desc') <span class="text-danger small mt-1 d-block">{{ $message }}</span> @enderror
                            </div>
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
                                <h5 class="fw-bold mb-1">Pedoman Mata Kuliah</h5>
                                <div class="text-muted small">Panduan pengisian kurikulum.</div>
                            </div>
                        </div>

                        <ul class="list-unstyled mb-0 text-muted small d-grid gap-2">
                            <li class="d-flex gap-2"><i class="fa fa-check text-success mt-1"></i><span>Pastikan <strong>Kode Mata Kuliah</strong> unik dan belum pernah digunakan di sistem sebelumnya.</span></li>
                            <li class="d-flex gap-2"><i class="fa fa-check text-success mt-1"></i><span>Pilih <strong>Tingkat Scope</strong> (Global, Fakultas, atau Prodi) agar mata kuliah tepat sasaran pada kurikulum.</span></li>
                            <li class="d-flex gap-2"><i class="fa fa-check text-success mt-1"></i><span>Gunakan fitur <strong>Prasyarat</strong> untuk mewajibkan kelulusan mata kuliah dasar sebelum mengambil mata kuliah ini.</span></li>
                        </ul>
                    </div>
                </div>

                <div class="card border-0 shadow-sm rounded-4 overflow-hidden mb-4">
                    <div class="card-header bg-white border-bottom p-4">
                        <div class="d-flex align-items-center gap-3">
                            <div class="bg-info bg-opacity-10 text-info rounded-3 d-flex align-items-center justify-content-center" style="width: 42px; height: 42px;">
                                <i class="fa fa-sitemap fs-5"></i>
                            </div>
                            <div>
                                <h5 class="card-title fw-bold mb-0 text-dark">Scope & Prasyarat</h5>
                                <div class="text-muted small">Penempatan unit dan syarat kelulusan.</div>
                            </div>
                        </div>
                    </div>
                    <div class="card-body p-4">
                        <div class="row g-3">
                            <div class="col-12">
                                <label class="form-label fw-semibold" for="scope_type">Tingkat Scope <span class="text-danger">*</span></label>
                                <select id="scope_type" class="form-select" wire:model.live="courseForm.scope_type">
                                    <option value="global">Global (Semua Unit)</option>
                                    <option value="faculty">Fakultas</option>
                                    <option value="study_program">Program Studi</option>
                                </select>
                                @error('courseForm.scope_type') <span class="text-danger small mt-1 d-block">{{ $message }}</span> @enderror
                            </div>

                            @if(($courseForm['scope_type'] ?? 'global') !== 'global')
                                <div class="col-12">
                                    <label class="form-label fw-semibold" for="scope_id">Unit Tujuan <span class="text-danger">*</span></label>
                                    <select id="scope_id" class="form-select" wire:model.defer="courseForm.scope_id">
                                        <option value="">Pilih Tujuan</option>
                                        @if (($courseForm['scope_type'] ?? 'global') === 'faculty')
                                            @foreach ($availableFaculties as $faculty)
                                                <option value="{{ $faculty['id'] }}">{{ $faculty['name'] }} @if(!$faculty['is_active']) (Nonaktif) @endif</option>
                                            @endforeach
                                        @elseif (($courseForm['scope_type'] ?? 'global') === 'study_program')
                                            @foreach ($availableStudyPrograms as $studyProgram)
                                                <option value="{{ $studyProgram['id'] }}">{{ $studyProgram['name'] }} @if(!$studyProgram['is_active']) (Nonaktif) @endif</option>
                                            @endforeach
                                        @endif
                                    </select>
                                    @error('courseForm.scope_id') <span class="text-danger small mt-1 d-block">{{ $message }}</span> @enderror
                                </div>
                            @endif

                            <div class="col-12">
                                <label class="form-label fw-semibold" for="prerequisites">Prasyarat Mata Kuliah</label>
                                <select id="prerequisites" class="form-select" multiple size="5" wire:model.defer="selectedPrerequisites">
                                    @foreach ($availablePrerequisites as $prerequisite)
                                        <option value="{{ $prerequisite['id'] }}">{{ $prerequisite['label'] }}</option>
                                    @endforeach
                                </select>
                                <small class="text-muted d-block mt-1">Tahan tombol Ctrl / Cmd untuk memilih lebih dari satu mata kuliah.</small>
                                @error('selectedPrerequisites') <span class="text-danger small mt-1 d-block">{{ $message }}</span> @enderror
                                @error('selectedPrerequisites.*') <span class="text-danger small mt-1 d-block">{{ $message }}</span> @enderror
                            </div>

                            <div class="col-12 border-top pt-3 mt-3">
                                <div class="form-check form-switch">
                                    <input id="is_active" class="form-check-input" type="checkbox" wire:model.defer="courseForm.is_active">
                                    <label for="is_active" class="form-check-label fw-semibold">Status Mata Kuliah Aktif</label>
                                </div>
                                @error('courseForm.is_active') <span class="text-danger small mt-1 d-block">{{ $message }}</span> @enderror
                            </div>

                            <div class="col-12 border-top pt-3 mt-3 d-flex justify-content-end gap-2">
                                <button type="button" class="btn btn-light rounded-pill px-3 py-2" wire:click="cancel">
                                    Batal
                                </button>
                                <button type="submit" class="btn btn-primary rounded-pill px-4 py-2 shadow-sm">
                                    <i class="fas fa-save me-1"></i> Simpan
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </form>
</div>
