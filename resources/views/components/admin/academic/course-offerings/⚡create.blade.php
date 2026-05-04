<?php

use App\Models\Academic\AcademicYear;
use App\Models\Academic\StudyProgram;
use App\Models\Academic\Course;
use App\Models\Academic\Curriculum;
use Livewire\Component;

new class extends Component {
    public array $courseOfferingForm = [];
    public array $availableAcademicYears = [];
    public array $availableStudyPrograms = [];
    public array $availableCurriculums = [];
    public array $availableCourses = [];

    public function cancel(): void
    {
        $this->redirectRoute('admin.academic.course-offerings.index');
    }

    public function mount(): void
    {
        $this->courseOfferingForm = [
            'academic_year_id' => '',
            'study_program_id' => '',
            'curriculum_id' => '',
            'course_id' => '',
            'label' => '',
            'code' => '',
            'semester_no' => '',
            'capacity' => '',
            'credits' => '',
            'is_required' => true,
            'delivery_mode' => 'Offline',
            'status' => 'Draft',
            'notes' => '',
        ];

        $this->availableAcademicYears = AcademicYear::orderByDesc('start_date')->get(['id', 'name'])->toArray();
        $this->availableStudyPrograms = StudyProgram::orderBy('name')->get(['id', 'name'])->toArray();
        $this->availableCourses = Course::orderBy('code')->get(['id', 'code', 'name'])->toArray();
    }

    public function createCourseOffering(): void
    {
        $validatedData = $this->validate([
            'courseOfferingForm.academic_year_id' => 'required|integer|exists:academic_years,id',
            'courseOfferingForm.study_program_id' => 'required|integer|exists:study_programs,id',
            'courseOfferingForm.curriculum_id' => 'nullable|integer|exists:curriculums,id',
            'courseOfferingForm.course_id' => 'required|integer|exists:courses,id',
            'courseOfferingForm.label' => 'nullable|string|max:255',
            'courseOfferingForm.code' => 'nullable|string|max:100',
            'courseOfferingForm.semester_no' => 'nullable|integer|min:1|max:14',
            'courseOfferingForm.capacity' => 'nullable|integer|min:1',
            'courseOfferingForm.credits' => 'nullable|integer|min:1|max:24',
            'courseOfferingForm.is_required' => 'boolean',
            'courseOfferingForm.delivery_mode' => 'required|in:Offline,Online,Hybrid',
            'courseOfferingForm.status' => 'required|in:Draft,Open,Closed,Cancelled',
            'courseOfferingForm.notes' => 'nullable|string',
        ]);

        \App\Models\Academic\CourseOffering::create([
            'academic_year_id' => $validatedData['courseOfferingForm']['academic_year_id'],
            'study_program_id' => $validatedData['courseOfferingForm']['study_program_id'],
            'curriculum_id' => $validatedData['courseOfferingForm']['curriculum_id'] ?: null,
            'course_id' => $validatedData['courseOfferingForm']['course_id'],
            'label' => $validatedData['courseOfferingForm']['label'] ?: null,
            'code' => $validatedData['courseOfferingForm']['code'] ?: null,
            'semester_no' => $validatedData['courseOfferingForm']['semester_no'] ?: null,
            'capacity' => $validatedData['courseOfferingForm']['capacity'] ?: null,
            'credits' => $validatedData['courseOfferingForm']['credits'] ?: null,
            'is_required' => (bool) $validatedData['courseOfferingForm']['is_required'],
            'delivery_mode' => $validatedData['courseOfferingForm']['delivery_mode'],
            'status' => $validatedData['courseOfferingForm']['status'],
            'notes' => $validatedData['courseOfferingForm']['notes'] ?: null,
            'created_by' => auth()->id(),
        ]);

        session()->flash('success', 'Course offering berhasil ditambahkan.');
        $this->redirectRoute('admin.academic.course-offerings.index');
    }

    public function render()
    {
        $data = [
            'menus' => 'Academic Management',
            'pages' => 'Tambah Course Offering',
        ];

        return $this->view()->layout('layouts.app', $data);
    }
};
?>

<div>
    <x-alert />
    <div class="card">
        <div class="card-header d-flex justify-content-between align-items-center">
            <h3 class="card-title">Tambah Course Offering</h3>
        </div>
        <div class="card-body row">
            <div class="form-group col-lg-6 col-md-6 col-sm-12 mt-2">
                <label for="academic_year_id">Tahun Akademik</label>
                <select id="academic_year_id" class="form-control" wire:model.defer="courseOfferingForm.academic_year_id">
                    <option value="">Pilih Tahun Akademik</option>
                    @foreach ($availableAcademicYears as $year)
                        <option value="{{ $year['id'] }}">{{ $year['name'] }}</option>
                    @endforeach
                </select>
                @error('courseOfferingForm.academic_year_id')
                    <span class="text-danger">{{ $message }}</span>
                @enderror
            </div>

            <div class="form-group col-lg-6 col-md-6 col-sm-12 mt-2">
                <label for="study_program_id">Program Studi</label>
                <select id="study_program_id" class="form-control" wire:model.defer="courseOfferingForm.study_program_id">
                    <option value="">Pilih Program Studi</option>
                    @foreach ($availableStudyPrograms as $program)
                        <option value="{{ $program['id'] }}">{{ $program['name'] }}</option>
                    @endforeach
                </select>
                @error('courseOfferingForm.study_program_id')
                    <span class="text-danger">{{ $message }}</span>
                @enderror
            </div>

            <div class="form-group col-lg-6 col-md-6 col-sm-12 mt-2">
                <label for="course_id">Mata Kuliah</label>
                <select id="course_id" class="form-control" wire:model.defer="courseOfferingForm.course_id">
                    <option value="">Pilih Mata Kuliah</option>
                    @foreach ($availableCourses as $course)
                        <option value="{{ $course['id'] }}">{{ $course['code'] }} - {{ $course['name'] }}</option>
                    @endforeach
                </select>
                @error('courseOfferingForm.course_id')
                    <span class="text-danger">{{ $message }}</span>
                @enderror
            </div>

            <div class="form-group col-lg-6 col-md-6 col-sm-12 mt-2">
                <label for="label">Label (Kelas)</label>
                <input type="text" id="label" class="form-control" wire:model.defer="courseOfferingForm.label" placeholder="Contoh: Reguler A / Karyawan">
                @error('courseOfferingForm.label')
                    <span class="text-danger">{{ $message }}</span>
                @enderror
            </div>

            <div class="form-group col-lg-3 col-md-6 col-sm-12 mt-2">
                <label for="code">Kode</label>
                <input type="text" id="code" class="form-control" wire:model.defer="courseOfferingForm.code" placeholder="Opsional">
                @error('courseOfferingForm.code')
                    <span class="text-danger">{{ $message }}</span>
                @enderror
            </div>

            <div class="form-group col-lg-3 col-md-6 col-sm-12 mt-2">
                <label for="semester_no">Semester</label>
                <input type="number" id="semester_no" class="form-control" min="1" max="14" wire:model.defer="courseOfferingForm.semester_no" placeholder="Opsional">
                @error('courseOfferingForm.semester_no')
                    <span class="text-danger">{{ $message }}</span>
                @enderror
            </div>

            <div class="form-group col-lg-3 col-md-6 col-sm-12 mt-2">
                <label for="capacity">Kapasitas</label>
                <input type="number" id="capacity" class="form-control" min="1" wire:model.defer="courseOfferingForm.capacity" placeholder="Opsional">
                @error('courseOfferingForm.capacity')
                    <span class="text-danger">{{ $message }}</span>
                @enderror
            </div>

            <div class="form-group col-lg-3 col-md-6 col-sm-12 mt-2">
                <label for="credits">SKS</label>
                <input type="number" id="credits" class="form-control" min="1" max="24" wire:model.defer="courseOfferingForm.credits" placeholder="Opsional">
                @error('courseOfferingForm.credits')
                    <span class="text-danger">{{ $message }}</span>
                @enderror
            </div>

            <div class="form-group col-lg-4 col-md-6 col-sm-12 mt-2">
                <label for="delivery_mode">Mode Pengiriman</label>
                <select id="delivery_mode" class="form-control" wire:model.defer="courseOfferingForm.delivery_mode">
                    <option value="Offline">Offline</option>
                    <option value="Online">Online</option>
                    <option value="Hybrid">Hybrid</option>
                </select>
                @error('courseOfferingForm.delivery_mode')
                    <span class="text-danger">{{ $message }}</span>
                @enderror
            </div>

            <div class="form-group col-lg-4 col-md-6 col-sm-12 mt-2">
                <label for="status">Status</label>
                <select id="status" class="form-control" wire:model.defer="courseOfferingForm.status">
                    <option value="Draft">Draft</option>
                    <option value="Open">Open</option>
                    <option value="Closed">Closed</option>
                    <option value="Cancelled">Cancelled</option>
                </select>
                @error('courseOfferingForm.status')
                    <span class="text-danger">{{ $message }}</span>
                @enderror
            </div>

            <div class="form-group col-lg-4 col-md-6 col-sm-12 mt-2">
                <div class="form-check form-switch mt-3">
                    <input id="is_required" class="form-check-input" type="checkbox" wire:model.defer="courseOfferingForm.is_required">
                    <label for="is_required" class="form-check-label">Wajib Diambil</label>
                </div>
            </div>

            <div class="form-group col-12 mt-2">
                <label for="notes">Catatan</label>
                <textarea id="notes" class="form-control" rows="3" wire:model.defer="courseOfferingForm.notes"></textarea>
                @error('courseOfferingForm.notes')
                    <span class="text-danger">{{ $message }}</span>
                @enderror
            </div>

            <div class="form-group col-12 mt-4 d-flex align-items-center justify-content-end gap-2">
                <button class="btn btn-primary" wire:click="createCourseOffering">
                    <i class="fas fa-save me-2"></i> Simpan</button>
                <button class="btn btn-secondary" wire:click="cancel">
                    <i class="fas fa-times me-2"></i> Batal</button>
            </div>
        </div>
    </div>
</div>
