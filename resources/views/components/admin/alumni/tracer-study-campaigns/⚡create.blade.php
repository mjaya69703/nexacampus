<?php

use App\Models\Academic\AcademicYear;
use App\Models\Alumni\TracerStudyCampaign;
use Livewire\Component;

new class extends Component
{
    public array $form = [];
    public array $questions = [];
    public $academicYears = [];

    public function cancel(): void
    {
        $this->redirectRoute('admin.alumni.tracer-study.index');
    }

    public function mount(): void
    {
        $this->academicYears = AcademicYear::orderByDesc('start_date')->get();
        $this->form = [
            'title' => '',
            'description' => '',
            'academic_year_id' => '',
            'start_date' => now()->format('Y-m-d'),
            'end_date' => now()->addMonths(3)->format('Y-m-d'),
            'status' => 'draft',
        ];
    }

    public function addQuestion(): void
    {
        $this->questions[] = [
            'id' => 'q_' . uniqid(),
            'type' => 'text',
            'text' => '',
            'options' => '',
        ];
    }

    public function removeQuestion(int $index): void
    {
        unset($this->questions[$index]);
        $this->questions = array_values($this->questions);
    }

    public function createTracerStudyCampaign(): void
    {
        $validated = $this->validate([
            'form.title' => 'required|string|max:255',
            'form.description' => 'nullable|string',
            'form.academic_year_id' => 'nullable|exists:academic_years,id',
            'form.start_date' => 'required|date',
            'form.end_date' => 'required|date|after_or_equal:form.start_date',
            'form.status' => 'required|in:draft,active,closed',
            'questions.*.type' => 'required|in:text,textarea,radio,select',
            'questions.*.text' => 'required|string',
            'questions.*.options' => 'nullable|string',
        ]);

        $questionObjects = collect($validated['questions'])->map(function ($q) {
            return [
                'id' => $q['id'],
                'type' => $q['type'],
                'text' => $q['text'],
                'options' => $q['options'] ? array_map('trim', explode("\n", $q['options'])) : [],
            ];
        })->toArray();

        TracerStudyCampaign::create([
            'title' => $validated['form']['title'],
            'description' => $validated['form']['description'] ?: null,
            'academic_year_id' => $validated['form']['academic_year_id'] ?: null,
            'start_date' => $validated['form']['start_date'],
            'end_date' => $validated['form']['end_date'],
            'status' => $validated['form']['status'],
            'questions' => $questionObjects,
            'created_by' => auth()->id(),
        ]);

        session()->flash('success', 'Kampanye tracer study berhasil ditambahkan.');
        $this->redirectRoute('admin.alumni.tracer-study.index');
    }

    public function render()
    {
        return $this->view()->layout('layouts.app', [
            'menus' => 'Alumni Management',
            'pages' => 'Tambah Kampanye Tracer Study',
        ]);
    }
};
?>

<div class="w-full" style="width: 100% !important">
    <x-alert />

    <x-admin.alumni.header
        title="Buat Kampanye Tracer Study Baru"
        description="Rancang kuesioner survei pelacakan lulusan berdasarkan sasaran tahun akademik untuk evaluasi karir alumni."
        icon="clipboard-list"
    >
        <a href="{{ route('admin.alumni.tracer-study.index') }}" class="btn btn-sm btn-light text-dark fw-semibold d-inline-flex align-items-center gap-2 shadow-sm rounded-pill px-3 py-2">
            <i class="fa fa-arrow-left"></i> <span>Kembali ke Daftar</span>
        </a>
    </x-admin.alumni.header>

    <div class="row g-4 align-items-start">
        <div class="col-lg-8">
            <div class="card border-0 shadow-sm rounded-4 overflow-hidden mb-4">
                <div class="card-header bg-white border-bottom p-4">
                    <div class="d-flex align-items-center gap-3">
                        <div class="bg-primary bg-opacity-10 text-primary rounded-3 d-flex align-items-center justify-content-center" style="width: 42px; height: 42px;">
                            <i class="fa fa-info-circle fs-5"></i>
                        </div>
                        <div>
                            <h4 class="card-title fw-bold mb-1 text-dark">Informasi Kampanye & Periode</h4>
                            <div class="text-muted small">Tentukan judul survei, sasaran tahun lulusan, serta rentang waktu pelaksanaan.</div>
                        </div>
                    </div>
                </div>
                <div class="card-body p-4">
                    <div class="row g-3">
                        <div class="col-lg-8 col-md-6">
                            <label for="title" class="form-label fw-semibold">Judul Kampanye <span class="text-danger">*</span></label>
                            <input type="text" id="title" class="form-control rounded-3" placeholder="Contoh: Tracer Study Lulusan Tahun 2024 - Tahap 1" wire:model.defer="form.title">
                            @error('form.title') <div class="text-danger small mt-1">{{ $message }}</div> @enderror
                        </div>
                        <div class="col-lg-4 col-md-6">
                            <label for="academic_year_id" class="form-label fw-semibold">Sasaran Tahun Akademik</label>
                            <select id="academic_year_id" class="form-control rounded-3" wire:model.defer="form.academic_year_id">
                                <option value="">-- Semua Lulusan / Umum --</option>
                                @foreach ($academicYears as $ay)
                                    <option value="{{ $ay->id }}">{{ $ay->name }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="col-lg-4 col-md-6">
                            <label for="start_date" class="form-label fw-semibold">Tanggal Mulai Survei <span class="text-danger">*</span></label>
                            <input type="date" id="start_date" class="form-control rounded-3" wire:model.defer="form.start_date">
                            @error('form.start_date') <div class="text-danger small mt-1">{{ $message }}</div> @enderror
                        </div>
                        <div class="col-lg-4 col-md-6">
                            <label for="end_date" class="form-label fw-semibold">Batas Akhir (Deadline) <span class="text-danger">*</span></label>
                            <input type="date" id="end_date" class="form-control rounded-3" wire:model.defer="form.end_date">
                            @error('form.end_date') <div class="text-danger small mt-1">{{ $message }}</div> @enderror
                        </div>
                        <div class="col-lg-4 col-md-6">
                            <label for="status" class="form-label fw-semibold">Status Awal <span class="text-danger">*</span></label>
                            <select id="status" class="form-control rounded-3" wire:model.defer="form.status">
                                <option value="draft">Draft (Belum Dipublikasi)</option>
                                <option value="active">Aktif (Langsung Dibuka)</option>
                                <option value="closed">Ditutup Sementara</option>
                            </select>
                        </div>
                        <div class="col-12">
                            <label for="description" class="form-label fw-semibold">Pengantar / Instruksi Pengisian Kuesioner</label>
                            <textarea id="description" class="form-control rounded-3" rows="3" wire:model.defer="form.description" placeholder="Berikan penjelasan singkat atau salam hangat bagi alumni yang akan mengisi kuesioner ini..."></textarea>
                        </div>
                    </div>
                </div>
            </div>

            <div class="card border-0 shadow-sm rounded-4 overflow-hidden">
                <div class="card-header bg-white border-bottom p-4 d-flex flex-wrap justify-content-between align-items-center gap-2">
                    <div class="d-flex align-items-center gap-3">
                        <div class="bg-success bg-opacity-10 text-success rounded-3 d-flex align-items-center justify-content-center" style="width: 42px; height: 42px;">
                            <i class="fa fa-question-circle fs-5"></i>
                        </div>
                        <div>
                            <h4 class="card-title fw-bold mb-1 text-dark">Daftar Pertanyaan Survei (Kuesioner)</h4>
                            <div class="text-muted small">Tambahkan pertanyaan teks singkat, uraian, pilihan ganda (radio), atau dropdown (select).</div>
                        </div>
                    </div>
                    <button type="button" class="btn btn-sm btn-outline-primary rounded-pill px-3 py-2 fw-semibold d-inline-flex align-items-center gap-2" wire:click="addQuestion">
                        <i class="fa fa-plus"></i> <span>Tambah Pertanyaan</span>
                    </button>
                </div>
                <div class="card-body p-4 bg-light bg-opacity-50">
                    @if (empty($questions))
                        <div class="text-center py-5 border rounded-4 bg-white">
                            <i class="fa fa-clipboard-question fs-1 text-muted opacity-50 mb-3"></i>
                            <h6 class="fw-bold text-dark">Belum Ada Pertanyaan Ditambahkan</h6>
                            <p class="text-muted small mb-3">Klik tombol "Tambah Pertanyaan" di kanan atas untuk menyusun kuesioner.</p>
                            <button type="button" class="btn btn-primary rounded-pill px-4 py-2 fw-semibold" wire:click="addQuestion">
                                <i class="fa fa-plus me-1"></i> Mulai Buat Pertanyaan
                            </button>
                        </div>
                    @else
                        <div class="d-grid gap-3">
                            @foreach ($questions as $index => $q)
                                <div class="card border shadow-sm rounded-4 overflow-hidden bg-white">
                                    <div class="card-header bg-light bg-opacity-75 p-3 d-flex justify-content-between align-items-center">
                                        <div class="fw-bold text-dark d-flex align-items-center gap-2">
                                            <span class="badge bg-primary rounded-pill px-2.5 py-1">#{{ $index + 1 }}</span>
                                            <span>Pertanyaan Survei</span>
                                        </div>
                                        <button type="button" class="btn btn-sm btn-outline-danger rounded-pill px-2.5 py-1" wire:click="removeQuestion({{ $index }})" title="Hapus pertanyaan ini">
                                            <i class="fa fa-trash"></i>
                                        </button>
                                    </div>
                                    <div class="card-body p-4">
                                        <div class="row g-3">
                                            <div class="col-md-4">
                                                <label class="form-label fw-semibold small">Tipe Jawaban</label>
                                                <select class="form-control rounded-3" wire:model.defer="questions.{{ $index }}.type">
                                                    <option value="text">Teks Singkat (Input Text)</option>
                                                    <option value="textarea">Teks Panjang (Textarea)</option>
                                                    <option value="radio">Pilihan Ganda (Radio Button)</option>
                                                    <option value="select">Daftar Pilihan (Dropdown Select)</option>
                                                </select>
                                            </div>
                                            <div class="col-md-8">
                                                <label class="form-label fw-semibold small">Teks Pertanyaan <span class="text-danger">*</span></label>
                                                <input type="text" class="form-control rounded-3" placeholder="Contoh: Berapa bulan waktu yang Anda butuhkan untuk mendapatkan pekerjaan pertama?" wire:model.defer="questions.{{ $index }}.text">
                                                @error("questions.{$index}.text") <div class="text-danger small mt-1">{{ $message }}</div> @enderror
                                            </div>
                                            @if (in_array($q['type'], ['radio', 'select']))
                                                <div class="col-12 mt-3">
                                                    <label class="form-label fw-semibold small text-primary"><i class="fa fa-list-ul me-1"></i> Pilihan Opsi Jawaban (Tulis satu opsi per baris)</label>
                                                    <textarea class="form-control rounded-3 font-monospace small" rows="3" wire:model.defer="questions.{{ $index }}.options" placeholder="Kurang dari 3 bulan&#10;Antara 3 hingga 6 bulan&#10;Lebih dari 6 bulan"></textarea>
                                                </div>
                                            @endif
                                        </div>
                                    </div>
                                </div>
                            @endforeach
                        </div>
                    @endif

                    <div class="d-flex flex-wrap gap-2 justify-content-end mt-4 pt-2">
                        <button type="button" class="btn btn-outline-secondary rounded-pill px-4" wire:click="cancel">
                            <i class="fa fa-times me-2"></i> Batal
                        </button>
                        <button type="button" class="btn btn-primary rounded-pill px-4" wire:click="createTracerStudyCampaign">
                            <i class="fa fa-save me-2"></i> Simpan Kampanye Tracer Study
                        </button>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-lg-4">
            <div class="card border-0 shadow-sm rounded-4 mb-4 overflow-hidden">
                <div class="card-body p-4 bg-light bg-opacity-50">
                    <div class="d-flex align-items-center gap-3 mb-3">
                        <div class="bg-primary bg-opacity-10 text-primary rounded-3 d-flex align-items-center justify-content-center" style="width: 42px; height: 42px;">
                            <i class="fa fa-circle-info fs-5"></i>
                        </div>
                        <div>
                            <h5 class="fw-bold mb-1">Panduan Tracer Study</h5>
                            <div class="text-muted small">Penyusunan instrumen pelacakan karir lulusan kampus.</div>
                        </div>
                    </div>

                    <ul class="list-unstyled mb-0 text-muted small d-grid gap-2.5">
                        <li class="d-flex gap-2"><i class="fa fa-check text-success mt-1"></i><span>Gunakan tipe <strong>Pilihan Ganda (Radio)</strong> atau <strong>Dropdown</strong> untuk memudahkan analisis grafik statistik otomatis.</span></li>
                        <li class="d-flex gap-2"><i class="fa fa-check text-success mt-1"></i><span>Jika sasaran <strong>Tahun Akademik</strong> dikosongkan, kampanye akan terbuka untuk semua angkatan alumni yang terdaftar.</span></li>
                    </ul>
                </div>
            </div>

            <div class="card border-0 shadow-sm rounded-4 overflow-hidden">
                <div class="card-body p-4">
                    <div class="text-muted small mb-1">Pengaturan Status</div>
                    <div class="fw-bold fs-5 text-dark mb-2">Draft (Default)</div>
                    <p class="text-muted small mb-0">Disarankan menyimpan sebagai draft terlebih dahulu untuk menguji susunan kuesioner sebelum diaktifkan ke publik alumni.</p>
                </div>
            </div>
        </div>
    </div>
</div>
