<?php

use App\Models\Academic\Faculty;
use App\Models\Academic\StudyProgram;
use Livewire\Component;

new class extends Component
{
    public array $studyProgramForm = [];
    public array $availableFaculties = [];

    public function cancel(): void
    {
        $this->redirectRoute('admin.academic.study-programs.index');
    }

    public function mount(): void
    {
        $this->studyProgramForm = [
            'faculty_id' => null,
            'name' => '',
            'code' => '',
            'short_name' => '',
            'degree' => 'S1',
            'prefix_degree' => '',
            'suffix_degree' => '',
            'is_active' => true,
            'desc' => '',
        ];

        $this->availableFaculties = Faculty::query()
            ->where('is_active', true)
            ->orderBy('name')
            ->get(['id', 'name'])
            ->map(fn (Faculty $faculty) => [
                'id' => $faculty->id,
                'name' => $faculty->name,
            ])
            ->toArray();
    }

    public function createStudyProgram(): void
    {
        $validatedData = $this->validate([
            'studyProgramForm.faculty_id' => 'nullable|exists:faculties,id',
            'studyProgramForm.name' => 'required|string|max:255',
            'studyProgramForm.code' => 'required|string|max:20|unique:study_programs,code',
            'studyProgramForm.short_name' => 'nullable|string|max:50',
            'studyProgramForm.degree' => 'required|in:D3,D4,S1,S2,S3',
            'studyProgramForm.prefix_degree' => 'nullable|string|max:255',
            'studyProgramForm.suffix_degree' => 'nullable|string|max:255',
            'studyProgramForm.is_active' => 'boolean',
            'studyProgramForm.desc' => 'nullable|string',
        ]);

        $faculty = null;

        if (!empty($validatedData['studyProgramForm']['faculty_id'])) {
            $faculty = Faculty::find($validatedData['studyProgramForm']['faculty_id']);
        }

        $isActive = (bool) ($validatedData['studyProgramForm']['is_active'] ?? false);

        if ($isActive && $faculty && !$faculty->is_active) {
            $this->addError('studyProgramForm.faculty_id', 'Program studi aktif tidak boleh terhubung ke fakultas nonaktif.');

            return;
        }

        StudyProgram::create([
            'faculty_id' => $validatedData['studyProgramForm']['faculty_id'] ?: null,
            'name' => $validatedData['studyProgramForm']['name'],
            'code' => $validatedData['studyProgramForm']['code'],
            'short_name' => $validatedData['studyProgramForm']['short_name'] ?: null,
            'degree' => $validatedData['studyProgramForm']['degree'],
            'prefix_degree' => $validatedData['studyProgramForm']['prefix_degree'] ?: null,
            'suffix_degree' => $validatedData['studyProgramForm']['suffix_degree'] ?: null,
            'is_active' => $isActive,
            'desc' => $validatedData['studyProgramForm']['desc'] ?: null,
            'created_by' => auth()->id(),
        ]);

        session()->flash('success', 'Program studi berhasil ditambahkan.');
        $this->redirectRoute('admin.academic.study-programs.index');
    }
    
    public function render()
    {
        $data = [
            'menus' => 'Academic Management',
            'pages' => 'Tambah Program Studi',
        ];

        return $this->view()->layout('layouts.app', $data);
    }
};
?>

<div>
    <x-alert />

    <x-admin.academic.header
        title="Tambah Program Studi Baru"
        description="Daftarkan program studi akademik beserta spesifikasi jenjang dan fakultas penanggung jawab."
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
                            <i class="fa fa-graduation-cap fs-5"></i>
                        </div>
                        <div>
                            <h4 class="card-title fw-bold mb-1 text-dark">Formulir Program Studi</h4>
                            <div class="text-muted small">Tentukan fakultas, nama prodi, kode unit, jenjang pendidikan, serta gelar akademik.</div>
                        </div>
                    </div>
                </div>
                <div class="card-body p-4 row g-3">
                    <div class="col-md-6">
                        <label class="form-label fw-semibold" for="faculty_id">Fakultas</label>
                        <select id="faculty_id" class="form-select" wire:model.defer="studyProgramForm.faculty_id">
                            <option value="">Pilih Fakultas</option>
                            @foreach ($availableFaculties as $faculty)
                                <option value="{{ $faculty['id'] }}">{{ $faculty['name'] }}</option>
                            @endforeach
                        </select>
                        @error('studyProgramForm.faculty_id')
                            <span class="text-danger small mt-1 d-block">{{ $message }}</span>
                        @enderror
                    </div>
                    <div class="col-md-6">
                        <label class="form-label fw-semibold" for="name">Nama Program Studi <span class="text-danger">*</span></label>
                        <input type="text" id="name" class="form-control" placeholder="Contoh: Teknik Informatika" wire:model.defer="studyProgramForm.name">
                        @error('studyProgramForm.name')
                            <span class="text-danger small mt-1 d-block">{{ $message }}</span>
                        @enderror
                    </div>
                    <div class="col-md-4">
                        <label class="form-label fw-semibold" for="code">Kode <span class="text-danger">*</span></label>
                        <input type="text" id="code" class="form-control" placeholder="Contoh: TIF" wire:model.defer="studyProgramForm.code">
                        @error('studyProgramForm.code')
                            <span class="text-danger small mt-1 d-block">{{ $message }}</span>
                        @enderror
                    </div>
                    <div class="col-md-4">
                        <label class="form-label fw-semibold" for="short_name">Nama Singkat</label>
                        <input type="text" id="short_name" class="form-control" placeholder="Contoh: Informatika" wire:model.defer="studyProgramForm.short_name">
                        @error('studyProgramForm.short_name')
                            <span class="text-danger small mt-1 d-block">{{ $message }}</span>
                        @enderror
                    </div>
                    <div class="col-md-4">
                        <label class="form-label fw-semibold" for="degree">Jenjang <span class="text-danger">*</span></label>
                        <select id="degree" class="form-select" wire:model.defer="studyProgramForm.degree">
                            <option value="D3">D3</option>
                            <option value="D4">D4</option>
                            <option value="S1">S1</option>
                            <option value="S2">S2</option>
                            <option value="S3">S3</option>
                        </select>
                        @error('studyProgramForm.degree')
                            <span class="text-danger small mt-1 d-block">{{ $message }}</span>
                        @enderror
                    </div>
                    <div class="col-md-6">
                        <label class="form-label fw-semibold" for="prefix_degree">Prefix Gelar</label>
                        <input type="text" id="prefix_degree" class="form-control" placeholder="Contoh: Ir." wire:model.defer="studyProgramForm.prefix_degree">
                        @error('studyProgramForm.prefix_degree')
                            <span class="text-danger small mt-1 d-block">{{ $message }}</span>
                        @enderror
                    </div>
                    <div class="col-md-6">
                        <label class="form-label fw-semibold" for="suffix_degree">Suffix Gelar</label>
                        <input type="text" id="suffix_degree" class="form-control" placeholder="Contoh: S.Kom." wire:model.defer="studyProgramForm.suffix_degree">
                        @error('studyProgramForm.suffix_degree')
                            <span class="text-danger small mt-1 d-block">{{ $message }}</span>
                        @enderror
                    </div>
                    <div class="col-12">
                        <label class="form-label fw-semibold" for="desc">Deskripsi</label>
                        <textarea id="desc" class="form-control" rows="3" wire:model.defer="studyProgramForm.desc" placeholder="Keterangan mengenai program studi (opsional)"></textarea>
                        @error('studyProgramForm.desc')
                            <span class="text-danger small mt-1 d-block">{{ $message }}</span>
                        @enderror
                    </div>
                    <div class="col-12">
                        <div class="form-check form-switch mt-2">
                            <input id="is_active" class="form-check-input" type="checkbox" wire:model.defer="studyProgramForm.is_active">
                            <label for="is_active" class="form-check-label fw-semibold">Aktifkan Program Studi Sekarang</label>
                        </div>
                        @error('studyProgramForm.is_active')
                            <span class="text-danger small mt-1 d-block">{{ $message }}</span>
                        @enderror
                    </div>

                    <div class="col-12 border-top pt-3 mt-4 d-flex align-items-center justify-content-end gap-2">
                        <button class="btn btn-light rounded-pill px-4 py-2" wire:click="cancel" type="button">
                            <i class="fa fa-times me-1"></i> Batal
                        </button>
                        <button class="btn btn-primary rounded-pill px-4 py-2 shadow-sm" wire:click="createStudyProgram" type="button">
                            <i class="fa fa-save me-1"></i> Simpan Program Studi
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
                            <h5 class="fw-bold mb-1">Pedoman Program Studi</h5>
                            <div class="text-muted small">Panduan pendaftaran prodi.</div>
                        </div>
                    </div>

                    <ul class="list-unstyled mb-0 text-muted small d-grid gap-2">
                        <li class="d-flex gap-2"><i class="fa fa-check text-success mt-1"></i><span><strong>Jenjang & Gelar:</strong> Pastikan jenjang pendidikan sesuai dengan gelar lulusan (misal S1 Teknik Informatika dengan suffix <em>S.Kom.</em>).</span></li>
                        <li class="d-flex gap-2"><i class="fa fa-check text-success mt-1"></i><span><strong>Status Fakultas:</strong> Program studi hanya dapat aktif jika Fakultas yang dipilih juga dalam keadaan Aktif.</span></li>
                        <li class="d-flex gap-2"><i class="fa fa-check text-success mt-1"></i><span><strong>Kode Unik:</strong> Kode program studi nantinya akan digunakan sebagai pengenal dalam kurikulum dan mata kuliah.</span></li>
                    </ul>
                </div>
            </div>
        </div>
    </div>
</div>
