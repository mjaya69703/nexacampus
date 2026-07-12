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

<div>
    <x-alert />

    <x-admin.academic.header
        title="Edit & Atur Kurikulum"
        description="Perbarui informasi data kurikulum dan kelola susunan sebaran mata kuliah per semester."
        icon="book-open"
    >
        <div class="d-flex align-items-center gap-2">
            <button type="button" class="btn btn-sm btn-light text-dark fw-semibold d-inline-flex align-items-center gap-2 shadow-sm rounded-pill px-3 py-2" wire:click="cancel">
                <i class="fa fa-arrow-left"></i> <span>Kembali ke daftar</span>
            </button>
            @activecan('curriculum.view')
                <a href="{{ route('admin.academic.curriculums.show', ['id' => $curriculum->id]) }}" class="btn btn-sm btn-primary fw-semibold d-inline-flex align-items-center gap-2 shadow-sm rounded-pill px-3 py-2">
                    <i class="fa fa-eye"></i> <span>Lihat Pratinjau Detail</span>
                </a>
            @endactivecan
        </div>
    </x-admin.academic.header>

    <div class="row g-4 align-items-start">
        <div class="col-lg-4">
            <div class="card border-0 shadow-sm rounded-4 overflow-hidden mb-4">
                <div class="card-header bg-white border-bottom p-4">
                    <div class="d-flex align-items-center gap-3">
                        <div class="bg-primary bg-opacity-10 text-primary rounded-3 d-flex align-items-center justify-content-center" style="width: 42px; height: 42px;">
                            <i class="fa fa-book-open fs-5"></i>
                        </div>
                        <div>
                            <h5 class="card-title fw-bold mb-1 text-dark">Informasi Kurikulum</h5>
                            <div class="text-muted small">Perbarui rincian kurikulum ini.</div>
                        </div>
                    </div>
                </div>
                <div class="card-body p-4">
                    <form wire:submit.prevent="update" class="row g-3">
                        <div class="col-12">
                            <label class="form-label fw-semibold">Program Studi <span class="text-danger">*</span></label>
                            <select class="form-select" wire:model="curriculumForm.study_program_id" required>
                                <option value="">Pilih Program Studi</option>
                                @foreach($availableStudyPrograms as $id => $name)
                                    <option value="{{ $id }}">{{ $name }}</option>
                                @endforeach
                            </select>
                            @error('curriculumForm.study_program_id') <span class="text-danger small mt-1 d-block">{{ $message }}</span> @enderror
                        </div>

                        <div class="col-12">
                            <label class="form-label fw-semibold">Nama Kurikulum <span class="text-danger">*</span></label>
                            <input type="text" class="form-control" wire:model="curriculumForm.name" placeholder="Contoh: Kurikulum Merdeka 2024" required>
                            @error('curriculumForm.name') <span class="text-danger small mt-1 d-block">{{ $message }}</span> @enderror
                        </div>

                        <div class="col-12">
                            <label class="form-label fw-semibold">Kode Kurikulum</label>
                            <input type="text" class="form-control" wire:model="curriculumForm.code" placeholder="Contoh: KUR-2024">
                            @error('curriculumForm.code') <span class="text-danger small mt-1 d-block">{{ $message }}</span> @enderror
                        </div>

                        <div class="col-6">
                            <label class="form-label fw-semibold">Tahun Mulai</label>
                            <input type="number" class="form-control" wire:model="curriculumForm.start_year" placeholder="2024" min="1900">
                            @error('curriculumForm.start_year') <span class="text-danger small mt-1 d-block">{{ $message }}</span> @enderror
                        </div>
                        <div class="col-6">
                            <label class="form-label fw-semibold">Tahun Akhir</label>
                            <input type="number" class="form-control" wire:model="curriculumForm.end_year" placeholder="2028" min="1900">
                            @error('curriculumForm.end_year') <span class="text-danger small mt-1 d-block">{{ $message }}</span> @enderror
                        </div>

                        <div class="col-12">
                            <label class="form-label fw-semibold">Deskripsi</label>
                            <textarea class="form-control" wire:model="curriculumForm.desc" rows="3" placeholder="Keterangan kurikulum"></textarea>
                            @error('curriculumForm.desc') <span class="text-danger small mt-1 d-block">{{ $message }}</span> @enderror
                        </div>

                        <div class="col-12">
                            <div class="form-check form-switch mt-1">
                                <input class="form-check-input" type="checkbox" wire:model="curriculumForm.is_active" id="edit_is_active">
                                <label class="form-check-label fw-semibold" for="edit_is_active">Status Kurikulum Aktif</label>
                            </div>
                        </div>

                        <div class="col-12 border-top pt-3 mt-3 d-flex justify-content-end gap-2">
                            <button type="button" class="btn btn-light rounded-pill px-3 py-2" wire:click="cancel">
                                <i class="fa fa-times me-1"></i> Batal
                            </button>
                            <button type="submit" class="btn btn-primary rounded-pill px-4 py-2 shadow-sm">
                                <i class="fa fa-save me-1"></i> Simpan
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>

        <div class="col-lg-8">
            <div class="card border-0 shadow-sm rounded-4 overflow-hidden">
                <div class="card-header bg-white p-4 border-bottom d-flex justify-content-between align-items-center flex-wrap gap-2">
                    <div>
                        <h5 class="card-title fw-bold mb-1">Daftar & Sebaran Mata Kuliah</h5>
                        <p class="text-muted small mb-0">Kelola penempatan mata kuliah pada semester di kurikulum ini.</p>
                    </div>
                    @activecan('curriculum.update')
                        <button type="button" class="btn btn-primary btn-sm rounded-pill px-3 py-2 shadow-sm d-flex align-items-center gap-1" wire:click="startCreateCurriculumCourse">
                            <i class="fa fa-plus-circle"></i> Tambah Mata Kuliah
                        </button>
                    @endactivecan
                </div>
                <div class="card-body p-4">
                    @if($showCurriculumCourseForm)
                        <div class="card bg-light border-0 rounded-4 p-4 mb-4 shadow-sm">
                            <div class="d-flex justify-content-between align-items-center mb-3">
                                <h6 class="fw-bold text-primary mb-0">
                                    <i class="fa fa-layer-group me-2"></i>{{ $editingCurriculumCourseId ? 'Edit Assignment Mata Kuliah' : 'Tambah Assignment Mata Kuliah' }}
                                </h6>
                                <button type="button" class="btn-close" wire:click="cancelCurriculumCourseForm" aria-label="Close"></button>
                            </div>
                            <form wire:submit.prevent="saveCurriculumCourse" class="row g-3">
                                <div class="col-md-6">
                                    <label class="form-label fw-semibold">Mata Kuliah <span class="text-danger">*</span></label>
                                    <select class="form-select" wire:model="curriculumCourseForm.course_id" required>
                                        <option value="">Pilih Mata Kuliah</option>
                                        @foreach($availableCourses as $id => $label)
                                            <option value="{{ $id }}">{{ $label }}</option>
                                        @endforeach
                                    </select>
                                    @error('curriculumCourseForm.course_id') <span class="text-danger small mt-1 d-block">{{ $message }}</span> @enderror
                                </div>
                                <div class="col-md-3">
                                    <label class="form-label fw-semibold">Semester Ke-</label>
                                    <input type="number" class="form-control" min="1" max="14" wire:model="curriculumCourseForm.semester_no" placeholder="Contoh: 1">
                                    @error('curriculumCourseForm.semester_no') <span class="text-danger small mt-1 d-block">{{ $message }}</span> @enderror
                                </div>
                                <div class="col-md-3">
                                    <label class="form-label fw-semibold">Urutan (Sort)</label>
                                    <input type="number" class="form-control" min="0" wire:model="curriculumCourseForm.sort_order" placeholder="0">
                                    @error('curriculumCourseForm.sort_order') <span class="text-danger small mt-1 d-block">{{ $message }}</span> @enderror
                                </div>
                                <div class="col-md-4">
                                    <label class="form-label fw-semibold">Override SKS (Opsional)</label>
                                    <input type="number" class="form-control" min="1" max="30" wire:model="curriculumCourseForm.credits_override" placeholder="Kosongkan jika sesuai SKS asli">
                                    @error('curriculumCourseForm.credits_override') <span class="text-danger small mt-1 d-block">{{ $message }}</span> @enderror
                                </div>
                                <div class="col-md-8">
                                    <label class="form-label fw-semibold">Catatan Tambahan</label>
                                    <input type="text" class="form-control" wire:model="curriculumCourseForm.notes" placeholder="Contoh: Mata kuliah prasyarat skripsi">
                                    @error('curriculumCourseForm.notes') <span class="text-danger small mt-1 d-block">{{ $message }}</span> @enderror
                                </div>
                                <div class="col-md-6 d-flex align-items-center">
                                    <div class="form-check form-switch mt-2">
                                        <input class="form-check-input" type="checkbox" wire:model="curriculumCourseForm.is_required" id="is_required">
                                        <label class="form-check-label fw-semibold" for="is_required">Mata Kuliah Wajib</label>
                                    </div>
                                </div>
                                <div class="col-md-6 d-flex align-items-center">
                                    <div class="form-check form-switch mt-2">
                                        <input class="form-check-input" type="checkbox" wire:model="curriculumCourseForm.is_active" id="cc_is_active">
                                        <label class="form-check-label fw-semibold" for="cc_is_active">Aktif dalam Kurikulum</label>
                                    </div>
                                </div>

                                <div class="col-12 border-top pt-3 mt-3 d-flex justify-content-end gap-2">
                                    <button type="button" class="btn btn-light rounded-pill px-3 py-2" wire:click="cancelCurriculumCourseForm">
                                        Batal
                                    </button>
                                    <button type="submit" class="btn btn-primary rounded-pill px-4 py-2 shadow-sm">
                                        <i class="fa fa-save me-1"></i> Simpan Assignment
                                    </button>
                                </div>
                            </form>
                        </div>
                    @endif

                    @if($this->curriculumCourses->count() > 0)
                        <div class="table-responsive">
                            <table class="table table-hover align-middle mb-0">
                                <thead class="table-light">
                                    <tr>
                                        <th style="width: 80px;" class="text-center">Smt</th>
                                        <th>Kode</th>
                                        <th>Mata Kuliah</th>
                                        <th class="text-center">SKS</th>
                                        <th class="text-center">Sifat</th>
                                        <th class="text-center">Sort</th>
                                        <th class="text-center">Status</th>
                                        <th class="text-center" style="width: 100px;">Aksi</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach($this->curriculumCourses as $cc)
                                        <tr>
                                            <td class="text-center fw-bold text-primary">{{ $cc->semester_no ?? '-' }}</td>
                                            <td><span class="badge bg-light text-dark border">{{ $cc->course->code }}</span></td>
                                            <td class="fw-semibold">{{ $cc->course->name }}</td>
                                            <td class="text-center">
                                                @if($cc->credits_override)
                                                    <span class="fw-bold">{{ $cc->credits_override }}</span> <small class="text-muted">({{ $cc->course->credits }})</small>
                                                @else
                                                    {{ $cc->course->credits }}
                                                @endif
                                            </td>
                                            <td class="text-center">
                                                <span class="badge {{ $cc->is_required ? 'bg-primary bg-opacity-10 text-primary' : 'bg-secondary bg-opacity-10 text-secondary' }} rounded-pill px-2 py-1">
                                                    {{ $cc->is_required ? 'Wajib' : 'Pilihan' }}
                                                </span>
                                            </td>
                                            <td class="text-center text-muted small">{{ $cc->sort_order }}</td>
                                            <td class="text-center">
                                                <span class="badge {{ $cc->is_active ? 'bg-success' : 'bg-secondary' }}">
                                                    {{ $cc->is_active ? 'Aktif' : 'Nonaktif' }}
                                                </span>
                                            </td>
                                            <td class="text-center">
                                                @activecan('curriculum.update')
                                                    <div class="btn-group btn-group-sm">
                                                        <button type="button" class="btn btn-light text-primary" wire:click="startEditCurriculumCourse({{ $cc->id }})" title="Edit">
                                                            <i class="fa fa-edit"></i>
                                                        </button>
                                                        <button type="button" class="btn btn-light text-danger" wire:click="confirmDeleteCurriculumCourse({{ $cc->id }})" title="Hapus">
                                                            <i class="fa fa-trash"></i>
                                                        </button>
                                                    </div>
                                                @endactivecan
                                            </td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    @else
                        <div class="text-center py-5">
                            <div class="text-muted mb-2"><i class="fa fa-folder-open fs-1 text-opacity-50"></i></div>
                            <h6 class="fw-bold text-muted">Belum ada mata kuliah dalam kurikulum ini</h6>
                            <p class="small text-muted mb-3">Klik tombol "Tambah Mata Kuliah" di atas untuk menempatkan mata kuliah pada semester tertentu.</p>
                        </div>
                    @endif
                </div>
            </div>
        </div>
    </div>
</div>
