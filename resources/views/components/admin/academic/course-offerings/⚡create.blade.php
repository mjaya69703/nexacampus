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

    <x-admin.academic.header
        title="Tambah Kelas Penawaran Baru"
        description="Jadwalkan penawaran kelas untuk mata kuliah pada tahun akademik dan program studi tertentu."
        icon="layer-group"
    >
        <button type="button" class="btn btn-sm btn-light text-dark fw-semibold d-inline-flex align-items-center gap-2 shadow-sm rounded-pill px-3 py-2" wire:click="cancel">
            <i class="fa fa-arrow-left"></i> <span>Kembali ke daftar</span>
        </button>
    </x-admin.academic.header>

    <form wire:submit.prevent="createCourseOffering">
        <div class="row g-4 align-items-start">
            <div class="col-lg-8">
                <div class="card border-0 shadow-sm rounded-4 overflow-hidden mb-4">
                    <div class="card-header bg-white border-bottom p-4">
                        <div class="d-flex align-items-center gap-3">
                            <div class="bg-primary bg-opacity-10 text-primary rounded-3 d-flex align-items-center justify-content-center" style="width: 42px; height: 42px;">
                                <i class="fa fa-layer-group fs-5"></i>
                            </div>
                            <div>
                                <h4 class="card-title fw-bold mb-1 text-dark">Formulir Penawaran Kelas</h4>
                                <div class="text-muted small">Tentukan mata kuliah, tahun akademik, program studi, dan label kelas yang ditawarkan.</div>
                            </div>
                        </div>
                    </div>
                    <div class="card-body p-4">
                        <div class="row g-3">
                            <div class="col-md-6">
                                <label class="form-label fw-semibold" for="academic_year_id">Tahun Akademik <span class="text-danger">*</span></label>
                                <select id="academic_year_id" class="form-select" wire:model.defer="courseOfferingForm.academic_year_id">
                                    <option value="">Pilih Tahun Akademik</option>
                                    @foreach ($availableAcademicYears as $year)
                                        <option value="{{ $year['id'] }}">{{ $year['name'] }}</option>
                                    @endforeach
                                </select>
                                @error('courseOfferingForm.academic_year_id') <span class="text-danger small mt-1 d-block">{{ $message }}</span> @enderror
                            </div>

                            <div class="col-md-6">
                                <label class="form-label fw-semibold" for="study_program_id">Program Studi <span class="text-danger">*</span></label>
                                <select id="study_program_id" class="form-select" wire:model.defer="courseOfferingForm.study_program_id">
                                    <option value="">Pilih Program Studi</option>
                                    @foreach ($availableStudyPrograms as $program)
                                        <option value="{{ $program['id'] }}">{{ $program['name'] }}</option>
                                    @endforeach
                                </select>
                                @error('courseOfferingForm.study_program_id') <span class="text-danger small mt-1 d-block">{{ $message }}</span> @enderror
                            </div>

                            <div class="col-12">
                                <label class="form-label fw-semibold" for="course_id">Mata Kuliah <span class="text-danger">*</span></label>
                                <select id="course_id" class="form-select" wire:model.defer="courseOfferingForm.course_id">
                                    <option value="">Pilih Mata Kuliah</option>
                                    @foreach ($availableCourses as $course)
                                        <option value="{{ $course['id'] }}">{{ $course['code'] }} - {{ $course['name'] }}</option>
                                    @endforeach
                                </select>
                                @error('courseOfferingForm.course_id') <span class="text-danger small mt-1 d-block">{{ $message }}</span> @enderror
                            </div>

                            <div class="col-md-6">
                                <label class="form-label fw-semibold" for="label">Label Kelas <span class="text-danger">*</span></label>
                                <input type="text" id="label" class="form-control" wire:model.defer="courseOfferingForm.label" placeholder="Contoh: Reguler A / Karyawan / Kelas A">
                                @error('courseOfferingForm.label') <span class="text-danger small mt-1 d-block">{{ $message }}</span> @enderror
                            </div>

                            <div class="col-md-6">
                                <label class="form-label fw-semibold" for="code">Kode Penawaran (Opsional)</label>
                                <input type="text" id="code" class="form-control" wire:model.defer="courseOfferingForm.code" placeholder="Contoh: OFF-IF101-A">
                                @error('courseOfferingForm.code') <span class="text-danger small mt-1 d-block">{{ $message }}</span> @enderror
                            </div>

                            <div class="col-md-6">
                                <label class="form-label fw-semibold" for="semester_no">Target Semester (Opsional)</label>
                                <input type="number" id="semester_no" class="form-control" min="1" max="14" wire:model.defer="courseOfferingForm.semester_no" placeholder="Contoh: 1">
                                @error('courseOfferingForm.semester_no') <span class="text-danger small mt-1 d-block">{{ $message }}</span> @enderror
                            </div>

                            <div class="col-md-6">
                                <label class="form-label fw-semibold" for="credits">Bobot SKS (Opsional)</label>
                                <input type="number" id="credits" class="form-control" min="1" max="24" wire:model.defer="courseOfferingForm.credits" placeholder="Opsional (Override SKS)">
                                @error('courseOfferingForm.credits') <span class="text-danger small mt-1 d-block">{{ $message }}</span> @enderror
                            </div>

                            <div class="col-12">
                                <label class="form-label fw-semibold" for="notes">Catatan & Informasi Tambahan</label>
                                <textarea id="notes" class="form-control" rows="3" wire:model.defer="courseOfferingForm.notes" placeholder="Tuliskan catatan untuk dosen atau mahasiswa..."></textarea>
                                @error('courseOfferingForm.notes') <span class="text-danger small mt-1 d-block">{{ $message }}</span> @enderror
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
                                <h5 class="fw-bold mb-1">Pedoman Penawaran</h5>
                                <div class="text-muted small">Panduan pembukaan kelas.</div>
                            </div>
                        </div>

                        <ul class="list-unstyled mb-0 text-muted small d-grid gap-2">
                            <li class="d-flex gap-2"><i class="fa fa-check text-success mt-1"></i><span>Pilih <strong>Tahun Akademik</strong> dan <strong>Program Studi</strong> sesuai dengan sasaran kelas perkuliahan.</span></li>
                            <li class="d-flex gap-2"><i class="fa fa-check text-success mt-1"></i><span>Gunakan <strong>Label Kelas</strong> yang informatif seperti Reguler A, Kelas Pagi, atau Karyawan.</span></li>
                            <li class="d-flex gap-2"><i class="fa fa-check text-success mt-1"></i><span>Atur <strong>Kapasitas Mahasiswa</strong> secara realistis dengan luas ruangan atau kuota dosen pengampu.</span></li>
                        </ul>
                    </div>
                </div>

                <div class="card border-0 shadow-sm rounded-4 overflow-hidden mb-4">
                    <div class="card-header bg-white border-bottom p-4">
                        <div class="d-flex align-items-center gap-3">
                            <div class="bg-info bg-opacity-10 text-info rounded-3 d-flex align-items-center justify-content-center" style="width: 42px; height: 42px;">
                                <i class="fa fa-sliders fs-5"></i>
                            </div>
                            <div>
                                <h5 class="card-title fw-bold mb-0 text-dark">Kapasitas & Status</h5>
                                <div class="text-muted small">Batas kuota dan status kelas.</div>
                            </div>
                        </div>
                    </div>
                    <div class="card-body p-4">
                        <div class="row g-3">
                            <div class="col-12">
                                <label class="form-label fw-semibold" for="capacity">Kapasitas Mahasiswa <span class="text-danger">*</span></label>
                                <input type="number" id="capacity" class="form-control" min="1" wire:model.defer="courseOfferingForm.capacity" placeholder="Contoh: 40">
                                @error('courseOfferingForm.capacity') <span class="text-danger small mt-1 d-block">{{ $message }}</span> @enderror
                            </div>

                            <div class="col-12">
                                <label class="form-label fw-semibold" for="delivery_mode">Mode Perkuliahan <span class="text-danger">*</span></label>
                                <select id="delivery_mode" class="form-select" wire:model.defer="courseOfferingForm.delivery_mode">
                                    <option value="Offline">Offline</option>
                                    <option value="Online">Online</option>
                                    <option value="Hybrid">Hybrid</option>
                                </select>
                                @error('courseOfferingForm.delivery_mode') <span class="text-danger small mt-1 d-block">{{ $message }}</span> @enderror
                            </div>

                            <div class="col-12">
                                <label class="form-label fw-semibold" for="status">Status Kelas <span class="text-danger">*</span></label>
                                <select id="status" class="form-select" wire:model.defer="courseOfferingForm.status">
                                    <option value="Draft">Draft</option>
                                    <option value="Open">Open</option>
                                    <option value="Closed">Closed</option>
                                    <option value="Cancelled">Cancelled</option>
                                </select>
                                @error('courseOfferingForm.status') <span class="text-danger small mt-1 d-block">{{ $message }}</span> @enderror
                            </div>

                            <div class="col-12 border-top pt-3 mt-3">
                                <div class="form-check form-switch">
                                    <input id="is_required" class="form-check-input" type="checkbox" wire:model.defer="courseOfferingForm.is_required">
                                    <label for="is_required" class="form-check-label fw-semibold">Wajib Diambil Mahasiswa</label>
                                </div>
                                @error('courseOfferingForm.is_required') <span class="text-danger small mt-1 d-block">{{ $message }}</span> @enderror
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
