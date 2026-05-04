<?php

use Livewire\Component;
use App\Support\ActivePermission;
use App\Models\Academic\Course;
use App\Models\Academic\Curriculum;
use App\Models\Academic\CurriculumCourse;
use App\Models\Academic\StudyProgram;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

new class extends Component {
    public $curriculum;
    public $curriculumForm = [];
    public $curriculumCourseForm = [];
    public array $availableStudyPrograms = [];
    public array $availableCourses = [];
    public ?int $editingCurriculumCourseId = null;
    public bool $showCurriculumCourseForm = false;

    public function mount($id): void
    {
        $this->curriculum = Curriculum::with('studyProgram')->findOrFail($id);
        $this->curriculumForm = $this->curriculum->toArray();
        $this->availableStudyPrograms = StudyProgram::where('is_active', true)
            ->pluck('name', 'id')
            ->toArray();

        $this->availableCourses = Course::where('is_active', true)
            ->orderBy('code')
            ->get()
            ->mapWithKeys(fn (Course $course) => [
                $course->id => "{$course->code} - {$course->name} ({$course->credits} SKS)",
            ])
            ->toArray();

        $this->resetCurriculumCourseForm();
    }

    public function cancel(): void
    {
        $this->redirect(route('admin.academic.curriculums.index'));
    }

    public function update(): void
    {
        $validatedData = $this->validate([
            'curriculumForm.study_program_id' => 'required|exists:study_programs,id',
            'curriculumForm.name' => [
                'required',
                'string',
                'max:255',
                Rule::unique('curriculums', 'name')
                    ->where(fn ($query) => $query->where('study_program_id', $this->curriculumForm['study_program_id'] ?? null))
                    ->ignore($this->curriculum->id),
            ],
            'curriculumForm.code' => [
                'nullable',
                'string',
                'max:50',
                Rule::unique('curriculums', 'code')
                    ->where(fn ($query) => $query->where('study_program_id', $this->curriculumForm['study_program_id'] ?? null))
                    ->ignore($this->curriculum->id),
            ],
            'curriculumForm.start_year' => 'nullable|integer|min:1900|max:' . date('Y'),
            'curriculumForm.end_year' => 'nullable|integer|min:1900|max:' . (date('Y') + 10),
            'curriculumForm.is_active' => 'nullable|boolean',
            'curriculumForm.desc' => 'nullable|string',
        ]);

        DB::beginTransaction();

        try {
            $this->curriculum->update(array_merge(
                $validatedData['curriculumForm'],
                ['updated_by' => auth()->id()]
            ));

            DB::commit();
            $this->curriculum->refresh();
            session()->flash('success', 'Kurikulum berhasil diperbarui!');
        } catch (\Exception $e) {
            DB::rollBack();
            session()->flash('error', 'Terjadi kesalahan: ' . $e->getMessage());
        }
    }

    public function startCreateCurriculumCourse(): void
    {
        if (! ActivePermission::check('curriculum.update')) {
            session()->flash('error', 'Anda tidak memiliki izin untuk mengelola mata kuliah kurikulum.');

            return;
        }

        $this->editingCurriculumCourseId = null;
        $this->showCurriculumCourseForm = true;
        $this->resetCurriculumCourseForm();
    }

    public function startEditCurriculumCourse(int $id): void
    {
        if (! ActivePermission::check('curriculum.update')) {
            session()->flash('error', 'Anda tidak memiliki izin untuk mengelola mata kuliah kurikulum.');

            return;
        }

        $curriculumCourse = $this->curriculum
            ->curriculumCourses()
            ->findOrFail($id);

        $this->editingCurriculumCourseId = $curriculumCourse->id;
        $this->showCurriculumCourseForm = true;
        $this->curriculumCourseForm = [
            'course_id' => $curriculumCourse->course_id,
            'semester_no' => $curriculumCourse->semester_no,
            'is_required' => $curriculumCourse->is_required,
            'sort_order' => $curriculumCourse->sort_order,
            'credits_override' => $curriculumCourse->credits_override,
            'notes' => $curriculumCourse->notes,
            'is_active' => $curriculumCourse->is_active,
        ];
    }

    public function cancelCurriculumCourseForm(): void
    {
        $this->showCurriculumCourseForm = false;
        $this->editingCurriculumCourseId = null;
        $this->resetCurriculumCourseForm();
    }

    public function saveCurriculumCourse(): void
    {
        if (! ActivePermission::check('curriculum.update')) {
            session()->flash('error', 'Anda tidak memiliki izin untuk mengelola mata kuliah kurikulum.');

            return;
        }

        $validatedData = $this->validate([
            'curriculumCourseForm.course_id' => [
                'required',
                'exists:courses,id',
                Rule::unique('curriculum_courses', 'course_id')
                    ->where(fn ($query) => $query->where('curriculum_id', $this->curriculum->id))
                    ->ignore($this->editingCurriculumCourseId),
            ],
            'curriculumCourseForm.semester_no' => 'nullable|integer|min:1|max:14',
            'curriculumCourseForm.is_required' => 'nullable|boolean',
            'curriculumCourseForm.sort_order' => 'nullable|integer|min:0',
            'curriculumCourseForm.credits_override' => 'nullable|integer|min:1|max:30',
            'curriculumCourseForm.notes' => 'nullable|string',
            'curriculumCourseForm.is_active' => 'nullable|boolean',
        ]);

        $payload = array_merge($validatedData['curriculumCourseForm'], [
            'curriculum_id' => $this->curriculum->id,
            'is_required' => (bool) ($validatedData['curriculumCourseForm']['is_required'] ?? false),
            'is_active' => (bool) ($validatedData['curriculumCourseForm']['is_active'] ?? false),
        ]);

        if ($this->editingCurriculumCourseId) {
            $curriculumCourse = $this->curriculum
                ->curriculumCourses()
                ->findOrFail($this->editingCurriculumCourseId);

            $curriculumCourse->update(array_merge($payload, ['updated_by' => auth()->id()]));
            session()->flash('success', 'Mata kuliah kurikulum berhasil diperbarui!');
        } else {
            CurriculumCourse::create(array_merge($payload, ['created_by' => auth()->id()]));
            session()->flash('success', 'Mata kuliah berhasil ditambahkan ke kurikulum!');
        }

        $this->curriculum->refresh();
        $this->cancelCurriculumCourseForm();
    }

    public function confirmDeleteCurriculumCourse(int $id): void
    {
        if (! ActivePermission::check('curriculum.update')) {
            session()->flash('error', 'Anda tidak memiliki izin untuk mengelola mata kuliah kurikulum.');

            return;
        }

        $curriculumCourse = $this->curriculum
            ->curriculumCourses()
            ->with('course')
            ->find($id);

        if ($curriculumCourse) {
            $courseLabel = $curriculumCourse->course
                ? $curriculumCourse->course->code . ' - ' . $curriculumCourse->course->name
                : 'Mata kuliah';

            $this->js('
                Swal.fire({
                    title: "Hapus mata kuliah?",
                    text: "' . $courseLabel . ' - Data tidak bisa dikembalikan!",
                    icon: "warning",
                    showCancelButton: true,
                    confirmButtonText: "Ya hapus",
                    cancelButtonText: "Batal"
                }).then((result) => {
                    if (result.isConfirmed) {
                        Livewire.dispatch("deleteConfirmed", { id: ' . $id . ' })
                    }
                });
            ');
        }
    }

    #[\Livewire\Attributes\On('deleteConfirmed')]
    public function deleteConfirmed($id = null): void
    {
        if (! ActivePermission::check('curriculum.update')) {
            session()->flash('error', 'Anda tidak memiliki izin untuk mengelola mata kuliah kurikulum.');

            return;
        }

        if ($id === null) {
            session()->flash('error', 'Id mata kuliah kurikulum tidak ditemukan!');

            return;
        }

        $curriculumCourse = $this->curriculum
            ->curriculumCourses()
            ->find($id);

        if ($curriculumCourse) {
            $curriculumCourse->update(['deleted_by' => auth()->id()]);
            $curriculumCourse->delete();
            $this->curriculum->refresh();

            $this->js('
                Swal.fire({
                    title: "Data dihapus",
                    text: "Mata kuliah berhasil dihapus dari kurikulum!",
                    icon: "success",
                    timer: 2000,
                    showConfirmButton: false
                });
            ');
        }
    }

    public function getCurriculumCoursesProperty()
    {
        return $this->curriculum
            ->curriculumCourses()
            ->with('course')
            ->whereHas('course')
            ->join('courses', 'courses.id', '=', 'curriculum_courses.course_id')
            ->orderByRaw('COALESCE(curriculum_courses.semester_no, 999) ASC')
            ->orderBy('curriculum_courses.sort_order')
            ->orderBy('courses.name')
            ->select('curriculum_courses.*')
            ->get();
    }

    protected function resetCurriculumCourseForm(): void
    {
        $this->curriculumCourseForm = [
            'course_id' => null,
            'semester_no' => null,
            'is_required' => true,
            'sort_order' => 0,
            'credits_override' => null,
            'notes' => null,
            'is_active' => true,
        ];
    }

    public function render()
    {
        return $this->view()
            ->layout('layouts.app', [
                'menus' => 'Academic',
                'pages' => 'Edit Kurikulum',
            ]);
    }
};
?>

<div class="row">
    <div class="col-12">
        <x-alert />

        <div class="card">
            <div class="card-header">
                <h5 class="card-title">Section A - Form Curriculum</h5>
            </div>
            <div class="card-body">
                <form wire:submit.prevent="update">
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
                            <i class="fas fa-save me-2"></i> Simpan Perubahan
                        </button>
                        @activecan('curriculum.view')
                            <a href="{{ route('admin.academic.curriculums.show', ['id' => $curriculum->id]) }}" class="btn btn-info">
                                <i class="fas fa-eye me-2"></i> Lihat Detail
                            </a>
                        @endactivecan
                        <button type="button" class="btn btn-secondary" wire:click="cancel">
                            <i class="fas fa-times me-2"></i> Batal
                        </button>
                    </div>
                </form>
            </div>
        </div>

        <div class="card mt-3">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h5 class="card-title mb-0">Section B - Manage Curriculum Courses</h5>
                @activecan('curriculum.update')
                    <button type="button" class="btn  btn-ghost-primary" wire:click="startCreateCurriculumCourse">
                        <i class="fa fa-plus me-1"></i> Tambah Mata Kuliah
                    </button>
                @endactivecan
            </div>
            <div class="card-body">
                @if($showCurriculumCourseForm)
                    <div class="border rounded p-3 mb-3 ">
                        <h6 class="mb-3">{{ $editingCurriculumCourseId ? 'Edit Assignment Mata Kuliah' : 'Tambah Assignment Mata Kuliah' }}</h6>
                        <form wire:submit.prevent="saveCurriculumCourse">
                            <div class="row">
                                <div class="col-md-6 mb-3">
                                    <label class="form-label">Mata Kuliah <span class="text-danger">*</span></label>
                                    <select class="form-select" wire:model="curriculumCourseForm.course_id" required>
                                        <option value="">Pilih Mata Kuliah</option>
                                        @foreach($availableCourses as $id => $label)
                                            <option value="{{ $id }}">{{ $label }}</option>
                                        @endforeach
                                    </select>
                                    @error('curriculumCourseForm.course_id') <span class="text-danger">{{ $message }}</span> @enderror
                                </div>
                                <div class="col-md-3 mb-3">
                                    <label class="form-label">Semester</label>
                                    <input type="number" class="form-control" min="1" max="14" wire:model="curriculumCourseForm.semester_no">
                                    @error('curriculumCourseForm.semester_no') <span class="text-danger">{{ $message }}</span> @enderror
                                </div>
                                <div class="col-md-3 mb-3">
                                    <label class="form-label">Urutan</label>
                                    <input type="number" class="form-control" min="0" wire:model="curriculumCourseForm.sort_order">
                                    @error('curriculumCourseForm.sort_order') <span class="text-danger">{{ $message }}</span> @enderror
                                </div>
                                <div class="col-md-4 mb-3">
                                    <label class="form-label">Credits Override</label>
                                    <input type="number" class="form-control" min="1" max="30" wire:model="curriculumCourseForm.credits_override">
                                    @error('curriculumCourseForm.credits_override') <span class="text-danger">{{ $message }}</span> @enderror
                                </div>
                                <div class="col-md-8 mb-3">
                                    <label class="form-label">Catatan</label>
                                    <input type="text" class="form-control" wire:model="curriculumCourseForm.notes">
                                    @error('curriculumCourseForm.notes') <span class="text-danger">{{ $message }}</span> @enderror
                                </div>
                                <div class="col-md-6 mb-3">
                                    <label class="form-check">
                                        <input class="form-check-input" type="checkbox" wire:model="curriculumCourseForm.is_required">
                                        <span class="form-check-label">Mata kuliah wajib</span>
                                    </label>
                                </div>
                                <div class="col-md-6 mb-3">
                                    <label class="form-check">
                                        <input class="form-check-input" type="checkbox" wire:model="curriculumCourseForm.is_active">
                                        <span class="form-check-label">Aktif</span>
                                    </label>
                                </div>
                            </div>

                            <div class="text-end">
                                <button type="submit" class="btn btn-primary">
                                    <i class="fas fa-save me-1"></i> Simpan Assignment
                                </button>
                                <button type="button" class="btn btn-secondary" wire:click="cancelCurriculumCourseForm">
                                    Batal
                                </button>
                            </div>
                        </form>
                    </div>
                @endif

                @if($this->curriculumCourses->count() > 0)
                    <div class="table-responsive">
                        <table class="table table-sm table-hover table-vcenter">
                            <thead class="table-light">
                                <tr>
                                    <th style="min-width: 80px;" class="text-center">Semester</th>
                                    <th style="min-width: 120px;">Course Code</th>
                                    <th>Course Name</th>
                                    <th style="min-width: 160px;" class="text-center">Credits Asli / Override</th>
                                    <th style="min-width: 90px;" class="text-center">Wajib</th>
                                    <th style="min-width: 90px;" class="text-center">Sort</th>
                                    <th style="min-width: 90px;" class="text-center">Status</th>
                                    <th style="min-width: 120px;" class="text-center">Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse($this->curriculumCourses as $cc)
                                    <tr>
                                        <td class="text-center">{{ $cc->semester_no ?? '-' }}</td>
                                        <td>{{ $cc->course->code }}</td>
                                        <td>{{ $cc->course->name }}</td>
                                        <td class="text-center">{{ $cc->course->credits }} / {{ $cc->credits_override ?? '-' }}</td>
                                        <td class="text-center">
                                            <span class="badge {{ $cc->is_required ? 'bg-success' : 'bg-secondary' }}">
                                                {{ $cc->is_required ? 'Ya' : 'Tidak' }}
                                            </span>
                                        </td>
                                        <td class="text-center">{{ $cc->sort_order }}</td>
                                        <td class="text-center">
                                            <span class="badge {{ $cc->is_active ? 'bg-success' : 'bg-secondary' }}">
                                                {{ $cc->is_active ? 'Aktif' : 'Nonaktif' }}
                                            </span>
                                        </td>
                                        <td class="text-center">
                                            @activecan('curriculum.update')
                                                <button type="button" class="btn  btn-icon btn-warning" wire:click="startEditCurriculumCourse({{ $cc->id }})" title="Edit">
                                                    <i class="fas fa-edit"></i>
                                                </button>
                                                <button class="btn  btn-icon btn-danger" wire:click="confirmDeleteCurriculumCourse({{ $cc->id }})" title="Hapus" type="button">
                                                    <i class="fas fa-trash"></i>
                                                </button>
                                            @endactivecan
                                        </td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="8" class="text-center text-muted py-3">
                                            <small>Belum ada mata kuliah yang ditambahkan ke kurikulum ini</small>
                                        </td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                @else
                    <div class="alert alert-light border text-center py-3 mb-0">
                        <i class="fas fa-info-circle me-2"></i>
                        <small>Belum ada mata kuliah yang ditambahkan ke kurikulum ini</small>
                    </div>
                @endif
            </div>
        </div>
    </div>
</div>
