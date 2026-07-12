<?php

use App\Models\Academic\AcademicYear;
use App\Models\Academic\StudentProfile;
use App\Support\Financial\InvoiceGenerationService;
use Illuminate\Support\Collection;
use Livewire\Component;

new class extends Component
{
    public array $academicYears = [];

    public string $studentSearch = '';

    public array $selectedStudentProfileIds = [];

    public array $form = [
        'invoice_kind' => 'tuition',
        'generation_mode' => 'single',
        'student_profile_id' => '',
        'academic_year_id' => '',
        'semester' => 1,
        'due_date' => '',
        'invoice_type' => 'tuition',
        'notes' => '',
        'issue_immediately' => true,
    ];
    public array $items = [
        ['item_type' => 'fee', 'description' => '', 'amount' => null],
    ];

    public function mount(): void
    {
        $this->academicYears = AcademicYear::query()->orderByDesc('start_date')->get(['id', 'name', 'code'])
            ->map(fn (AcademicYear $year) => ['id' => $year->id, 'label' => $year->name.' ('.$year->code.')'])
            ->toArray();
    }

    public function updatedFormInvoiceKind(string $value): void
    {
        $this->form['issue_immediately'] = $value === 'tuition';

        if ($value === 'tuition') {
            $this->form['invoice_type'] = 'tuition';
        } elseif (($this->form['invoice_type'] ?? 'tuition') === 'tuition') {
            $this->form['invoice_type'] = 'custom';
        }
    }

    public function addItem(): void
    {
        $this->items[] = ['item_type' => 'fee', 'description' => '', 'amount' => null];
    }

    public function removeItem(int $index): void
    {
        if (count($this->items) <= 1) {
            return;
        }

        unset($this->items[$index]);
        $this->items = array_values($this->items);
    }

    public function save(InvoiceGenerationService $generationService): void
    {
        $validated = $this->validate($this->rules())['form'];
        $academicYear = $validated['academic_year_id'] ? AcademicYear::find($validated['academic_year_id']) : null;
        $students = $this->studentsForGeneration($validated);
        $created = 0;
        $errors = [];

        if ($students->isEmpty()) {
            session()->flash('error', 'Tidak ada student profile aktif yang cocok dengan target invoice. Invoice hanya dapat diterbitkan untuk mahasiswa yang telah memiliki student profile.');

            return;
        }

        foreach ($students as $student) {
            try {
                if ($validated['invoice_kind'] === 'tuition') {
                    $generationService->generateForStudent(
                        $student,
                        $academicYear,
                        (int) $validated['semester'],
                        $validated['due_date'] ?: null,
                        auth()->id(),
                        (bool) $validated['issue_immediately'],
                    );
                } else {
                    $generationService->createCustomInvoice(
                        $student,
                        collect($this->items)->filter(fn (array $item) => filled($item['description']) && (float) $item['amount'] > 0)->values()->all(),
                        $validated['invoice_type'],
                        $validated['due_date'],
                        $academicYear,
                        filled($validated['semester']) ? (int) $validated['semester'] : null,
                        $validated['notes'] ?: null,
                        auth()->id(),
                        (bool) $validated['issue_immediately'],
                    );
                }

                $created++;
            } catch (\Throwable $exception) {
                $errors[] = ($student->nim ?? $student->id).': '.$exception->getMessage();
            }
        }

        if ($created > 0) {
            session()->flash('success', $created.' invoice tagihan berhasil diterbitkan.');
        }

        if (! empty($errors)) {
            session()->flash('error', implode(' ', array_slice($errors, 0, 5)));
        }

        $this->redirectRoute('admin.financial.student-invoices.index');
    }

    public function render()
    {
        return $this->view()->layout('layouts.app', [
            'menus' => 'Keuangan',
            'pages' => 'Terbitkan Tagihan Mahasiswa',
        ]);
    }

    public function eligibleBulkStudentsCount(): int
    {
        return $this->studentsForGeneration([
            'generation_mode' => 'active_students',
            'academic_year_id' => $this->form['academic_year_id'] ?: null,
            'semester' => $this->form['semester'] ?: null,
        ])->count();
    }

    public function studentOptions(): array
    {
        return StudentProfile::query()
            ->with(['user', 'studyProgram'])
            ->where('is_active', true)
            ->when($this->studentSearch !== '', function ($query) {
                $search = '%'.$this->studentSearch.'%';

                $query->where(function ($query) use ($search) {
                    $query->where('nim', 'like', $search)
                        ->orWhereHas('user', fn ($query) => $query
                            ->where('first_name', 'like', $search)
                            ->orWhere('last_name', 'like', $search)
                            ->orWhere('email', 'like', $search));
                });
            })
            ->orderBy('nim')
            ->limit(25)
            ->get()
            ->map(fn (StudentProfile $student) => [
                'id' => $student->id,
                'label' => ($student->nim ?? '-').' - '.$student->user?->name.' ('.$student->studyProgram?->name.')',
            ])
            ->toArray();
    }

    private function rules(): array
    {
        $isCustomInvoice = ($this->form['invoice_kind'] ?? 'tuition') === 'custom';
        $invoiceType = $isCustomInvoice ? ($this->form['invoice_type'] ?? 'custom') : 'tuition';
        $academicYearRule = in_array($invoiceType, ['tuition', 'registration', 'exam'], true)
            ? 'required|exists:academic_years,id'
            : 'nullable|exists:academic_years,id';

        $rules = [
            'form.invoice_kind' => 'required|in:tuition,custom',
            'form.generation_mode' => 'required|in:single,selected_students,active_students',
            'form.student_profile_id' => 'required_if:form.generation_mode,single|nullable|exists:student_profiles,id',
            'selectedStudentProfileIds' => 'required_if:form.generation_mode,selected_students|array',
            'selectedStudentProfileIds.*' => 'exists:student_profiles,id',
            'form.academic_year_id' => $academicYearRule,
            'form.semester' => 'required_if:form.invoice_kind,tuition|nullable|integer|min:1|max:14',
            'form.due_date' => 'required_if:form.invoice_kind,custom|nullable|date',
            'form.invoice_type' => 'required|in:tuition,custom,admission,registration,leave,transfer,graduation,exam,library_fine,certificate,other',
            'form.notes' => 'nullable|string|max:1000',
            'form.issue_immediately' => 'required|boolean',
        ];

        if ($isCustomInvoice) {
            $rules += [
                'items' => 'required|array|min:1',
                'items.*.item_type' => 'required|in:fee,discount,adjustment,penalty',
                'items.*.description' => 'required|string|max:255',
                'items.*.amount' => 'required|numeric|min:0.01',
            ];
        }

        return $rules;
    }

    private function studentsForGeneration(array $validated): Collection
    {
        if ($validated['generation_mode'] === 'single') {
            return StudentProfile::query()
                ->with(['user', 'studyProgram'])
                ->whereKey($validated['student_profile_id'])
                ->get();
        }

        if ($validated['generation_mode'] === 'selected_students') {
            return StudentProfile::query()
                ->with(['user', 'studyProgram'])
                ->where('is_active', true)
                ->whereKey($this->selectedStudentProfileIds)
                ->get();
        }

        return StudentProfile::query()
            ->with(['user', 'studyProgram'])
            ->where('is_active', true)
            ->when($validated['semester'] ?? null, fn ($query, $semester) => $query->where('current_semester', (int) $semester))
            ->when($validated['academic_year_id'] ?? null, function ($query, $academicYearId) use ($validated) {
                $query->whereHas('registrations', function ($query) use ($academicYearId, $validated) {
                    $query->where('academic_year_id', $academicYearId)
                        ->when($validated['semester'] ?? null, fn ($query, $semester) => $query->where('semester_no', $semester))
                        ->where('registration_status', 'Approved')
                        ->where('is_active', true);
                });
            })
            ->get();
    }
};
?>

<div class="w-full" style="width: 100% !important">
    <x-alert />

    <x-admin.financial.header
        title="Terbitkan Tagihan Mahasiswa (Create Invoice)"
        description="Buat tagihan SPP otomatis berdasarkan tarif master atau terbitkan tagihan kustom manual untuk mahasiswa tunggal maupun massal."
        icon="plus-circle"
    >
        <a href="{{ route('admin.financial.student-invoices.index') }}" class="btn btn-sm btn-light text-secondary fw-semibold d-inline-flex align-items-center gap-2 shadow-sm rounded-pill px-3 py-2">
            <i class="fa fa-arrow-left"></i> <span>Kembali ke Daftar</span>
        </a>
    </x-admin.financial.header>

    <div class="row g-4">
        <div class="col-lg-8">
            <div class="card border-0 shadow-sm rounded-4 overflow-hidden">
                <div class="card-header bg-white border-bottom p-3 p-md-4 d-flex flex-column flex-md-row gap-2 justify-content-between align-items-md-center">
                    <div class="d-flex align-items-center gap-3">
                        <div class="bg-primary bg-opacity-10 text-primary rounded-3 d-flex align-items-center justify-content-center" style="width: 40px; height: 40px;">
                            <i class="fa fa-file-invoice-dollar fs-5"></i>
                        </div>
                        <div>
                            <h4 class="card-title fw-bold mb-0 text-dark">Formulir Penerbitan Tagihan</h4>
                            <span class="text-muted small">Pilih mode target mahasiswa dan konfigurasikan rincian biaya studi.</span>
                        </div>
                    </div>
                </div>
                <div class="card-body p-3 p-md-4">
                    <form wire:submit.prevent="save">
                    @if (StudentProfile::where('is_active', true)->doesntExist())
                        <div class="alert alert-warning border-0 rounded-3 mb-4">
                            <div class="d-flex gap-2">
                                <i class="fas fa-triangle-exclamation fs-5 mt-1"></i>
                                <div>
                                    <strong>Belum ada profil mahasiswa (Student Profile) aktif.</strong>
                                    Tagihan keuangan hanya dapat diterbitkan untuk akun mahasiswa yang telah memiliki profil akademik aktif. Silakan verifikasi pendaftaran/registrasi mahasiswa terlebih dahulu.
                                </div>
                            </div>
                        </div>
                    @endif

                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label fw-semibold">Sumber / Format Tagihan</label>
                            <select class="form-select" wire:model.live="form.invoice_kind">
                                <option value="tuition">Template SPP / Kuliah (Otomatis dari Master)</option>
                                <option value="custom">Tagihan Kustom / Non-SPP (Input Manual)</option>
                            </select>
                            @error('form.invoice_kind') <div class="text-danger small mt-1">{{ $message }}</div> @enderror
                        </div>

                        <div class="col-md-6">
                            <label class="form-label fw-semibold">Mode Target Mahasiswa</label>
                            <select class="form-select" wire:model.live="form.generation_mode">
                                <option value="single">Satu Mahasiswa Khusus</option>
                                <option value="selected_students">Pilih Beberapa Mahasiswa</option>
                                <option value="active_students">Seluruh Mahasiswa Aktif (Massal)</option>
                            </select>
                            @error('form.generation_mode') <div class="text-danger small mt-1">{{ $message }}</div> @enderror
                        </div>

                        @if (($form['generation_mode'] ?? 'single') === 'single')
                            <div class="col-12">
                                <label class="form-label fw-semibold">Pilih Satu Mahasiswa <span class="text-danger">*</span></label>
                                <input type="text" class="form-control mb-2 rounded-pill px-3" wire:model.live.debounce.300ms="studentSearch" placeholder="Cari berdasarkan NIM, Nama, atau Email...">
                                <div class="border rounded-3 p-3 bg-light bg-opacity-50" style="max-height: 240px; overflow-y: auto;">
                                    @forelse ($this->studentOptions() as $student)
                                        <label class="form-check py-1 d-block" wire:key="invoice-single-student-{{ $student['id'] }}">
                                            <input class="form-check-input" type="radio" wire:model="form.student_profile_id" value="{{ $student['id'] }}">
                                            <span class="form-check-label fw-medium">{{ $student['label'] }}</span>
                                        </label>
                                    @empty
                                        <div class="text-secondary small text-center py-2">Mahasiswa tidak ditemukan.</div>
                                    @endforelse
                                </div>
                                <div class="small text-secondary mt-1">Pencarian menampilkan maksimal 25 kandidat teratas.</div>
                                @error('form.student_profile_id') <div class="text-danger small mt-1">{{ $message }}</div> @enderror
                            </div>
                        @elseif (($form['generation_mode'] ?? 'single') === 'selected_students')
                            <div class="col-12">
                                <label class="form-label fw-semibold">Pilih Mahasiswa <span class="text-danger">*</span></label>
                                <input type="text" class="form-control mb-2 rounded-pill px-3" wire:model.live.debounce.300ms="studentSearch" placeholder="Cari berdasarkan NIM, Nama, atau Email...">
                                <div class="border rounded-3 p-3 bg-light bg-opacity-50" style="max-height: 240px; overflow-y: auto;">
                                    @forelse ($this->studentOptions() as $student)
                                        <label class="form-check py-1 d-block" wire:key="invoice-selected-student-{{ $student['id'] }}">
                                            <input class="form-check-input" type="checkbox" wire:model="selectedStudentProfileIds" value="{{ $student['id'] }}">
                                            <span class="form-check-label fw-medium">{{ $student['label'] }}</span>
                                        </label>
                                    @empty
                                        <div class="text-secondary small text-center py-2">Mahasiswa tidak ditemukan.</div>
                                    @endforelse
                                </div>
                                <div class="small text-secondary mt-1">
                                    <i class="fas fa-check-circle text-primary me-1"></i> {{ count($selectedStudentProfileIds) }} mahasiswa terpilih.
                                </div>
                                @error('selectedStudentProfileIds') <div class="text-danger small mt-1">{{ $message }}</div> @enderror
                                @error('selectedStudentProfileIds.*') <div class="text-danger small mt-1">{{ $message }}</div> @enderror
                            </div>
                        @else
                            @php($eligibleBulkStudentsCount = $this->eligibleBulkStudentsCount())
                            <div class="col-12">
                                <div class="alert {{ $eligibleBulkStudentsCount > 0 ? 'alert-info' : 'alert-warning' }} border-0 rounded-3 mb-0">
                                    <div class="d-flex gap-3 align-items-center">
                                        <i class="fas {{ $eligibleBulkStudentsCount > 0 ? 'fa-circle-info' : 'fa-triangle-exclamation' }} fs-4"></i>
                                        <div>
                                            <strong>{{ number_format($eligibleBulkStudentsCount) }} mahasiswa memenuhi syarat (eligible).</strong>
                                            @if ($eligibleBulkStudentsCount > 0)
                                                Penerbitan tagihan massal akan menyasar seluruh mahasiswa dengan profil aktif yang terdaftar pada tahun akademik dan semester yang dipilih.
                                            @else
                                                Tidak ada mahasiswa aktif yang cocok dengan parameter filter ini.
                                            @endif
                                        </div>
                                    </div>
                                </div>
                            </div>
                        @endif

                        <div class="col-md-6">
                            <label class="form-label fw-semibold">Kategori Tagihan</label>
                            <select class="form-select" wire:model="form.invoice_type" @if(($form['invoice_kind'] ?? 'tuition') === 'tuition') disabled @endif>
                                <option value="tuition">SPP / Kuliah (Tuition)</option>
                                <option value="custom">Tagihan Kustom (Custom)</option>
                                <option value="admission">Pendaftaran Mahasiswa Baru</option>
                                <option value="registration">Daftar Ulang / Registrasi</option>
                                <option value="leave">Cuti Akademik (Leave)</option>
                                <option value="transfer">Pindah Jalur / Program (Transfer)</option>
                                <option value="graduation">Wisuda / Kelulusan (Graduation)</option>
                                <option value="exam">Ujian Akhir / Skripsi (Exam)</option>
                                <option value="library_fine">Denda Perpustakaan</option>
                                <option value="certificate">Legalisir / Sertifikat</option>
                                <option value="other">Lainnya (Other)</option>
                            </select>
                            @error('form.invoice_type') <div class="text-danger small mt-1">{{ $message }}</div> @enderror
                        </div>

                        <div class="col-md-6">
                            <label class="form-label fw-semibold">Tahun Akademik @if(in_array(($form['invoice_type'] ?? 'custom'), ['tuition', 'registration', 'exam'])) <span class="text-danger">*</span> @endif</label>
                            <select class="form-select" wire:model="form.academic_year_id">
                                <option value="">Pilih Tahun Akademik</option>
                                @foreach ($academicYears as $academicYear)
                                    <option value="{{ $academicYear['id'] }}">{{ $academicYear['label'] }}</option>
                                @endforeach
                            </select>
                            @error('form.academic_year_id') <div class="text-danger small mt-1">{{ $message }}</div> @enderror
                        </div>

                        <div class="col-md-6">
                            <label class="form-label fw-semibold">Semester @if(($form['invoice_kind'] ?? 'tuition') === 'tuition') <span class="text-danger">*</span> @endif</label>
                            <input type="number" min="1" max="14" class="form-control" wire:model="form.semester">
                            @error('form.semester') <div class="text-danger small mt-1">{{ $message }}</div> @enderror
                        </div>

                        <div class="col-md-6">
                            <label class="form-label fw-semibold">Tanggal Jatuh Tempo @if(($form['invoice_kind'] ?? 'tuition') === 'custom') <span class="text-danger">*</span> @endif</label>
                            <input type="date" class="form-control" wire:model="form.due_date">
                            @error('form.due_date') <div class="text-danger small mt-1">{{ $message }}</div> @enderror
                            @if(($form['invoice_kind'] ?? 'tuition') === 'tuition')
                                <div class="small text-secondary mt-1">Kosongkan untuk otomatis menggunakan batas waktu dari template SPP.</div>
                            @endif
                        </div>

                        <div class="col-12">
                            <label class="form-label fw-semibold">Catatan Internal / Referensi</label>
                            <textarea class="form-control" rows="2" wire:model="form.notes" placeholder="Catatan tambahan untuk bagian keuangan atau mahasiswa..."></textarea>
                            @error('form.notes') <div class="text-danger small mt-1">{{ $message }}</div> @enderror
                        </div>
                    </div>

                    @if (($form['invoice_kind'] ?? 'tuition') === 'custom')
                        <div class="border rounded-4 p-4 my-4 bg-light bg-opacity-50">
                            <div class="d-flex justify-content-between align-items-center mb-3">
                                <h5 class="fw-bold mb-0">Rincian Komponen Tagihan (Items)</h5>
                                <button type="button" class="btn btn-sm btn-outline-primary rounded-pill px-3" wire:click="addItem">
                                    <i class="fas fa-plus me-1"></i> Tambah Komponen
                                </button>
                            </div>

                            @foreach ($items as $index => $item)
                                <div class="row align-items-end g-2 mb-2" wire:key="invoice-item-{{ $index }}">
                                    <div class="col-md-3">
                                        <label class="form-label small fw-semibold">Jenis Item</label>
                                        <select class="form-select" wire:model="items.{{ $index }}.item_type">
                                            <option value="fee">Biaya Pokok (Fee)</option>
                                            <option value="discount">Potongan (Discount)</option>
                                            <option value="adjustment">Penyesuaian (Adjustment)</option>
                                            <option value="penalty">Denda (Penalty)</option>
                                        </select>
                                    </div>
                                    <div class="col-md-5">
                                        <label class="form-label small fw-semibold">Keterangan Biaya</label>
                                        <input type="text" class="form-control" wire:model="items.{{ $index }}.description" placeholder="Contoh: Biaya Praktikum Laboratorium">
                                    </div>
                                    <div class="col-md-3">
                                        <label class="form-label small fw-semibold">Nominal (Rp)</label>
                                        <input type="number" min="0" step="1000" class="form-control" wire:model="items.{{ $index }}.amount">
                                    </div>
                                    <div class="col-md-1">
                                        <button type="button" class="btn btn-outline-danger w-100" wire:click="removeItem({{ $index }})">
                                            <i class="fas fa-trash"></i>
                                        </button>
                                    </div>
                                </div>
                            @endforeach
                        </div>
                    @endif

                    <div class="mt-4 pt-3 border-top d-flex justify-content-between align-items-center">
                        <div class="form-check form-switch fs-6">
                            <input class="form-check-input" type="checkbox" role="switch" id="issueImmediately" wire:model="form.issue_immediately">
                            <label class="form-check-label fw-semibold ms-2" for="issueImmediately">Otomatis Terbitkan Tagihan Resmi (Issued)</label>
                            <div class="small text-secondary fw-normal">Jika dimatikan, invoice akan disimpan sebagai draft terlebih dahulu.</div>
                        </div>

                        <div class="d-flex gap-2">
                            <a href="{{ route('admin.financial.student-invoices.index') }}" class="btn btn-outline-secondary rounded-pill px-4 fw-semibold">Batal</a>
                            <button type="submit" class="btn btn-primary rounded-pill px-4 fw-semibold shadow-sm">
                                <i class="fas fa-file-invoice-dollar me-1"></i> Simpan & Terbitkan
                            </button>
                        </div>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <div class="col-lg-4">
        <div class="card shadow-sm border-0 rounded-4">
            <div class="card-header bg-white p-4 border-bottom">
                <h5 class="card-title fw-bold mb-0"><i class="fas fa-circle-info text-primary me-2"></i>Panduan Penerbitan Tagihan</h5>
            </div>
            <div class="card-body p-4 text-secondary small">
                <ul class="mb-0 ps-3 space-y-2">
                    <li class="mb-2"><strong>Format SPP (Tuition):</strong> Komponen biaya dan nominal otomatis ditarik dari pengaturan Master Tuition Fee yang aktif untuk Program Studi dan Angkatan mahasiswa.</li>
                    <li class="mb-2"><strong>Format Kustom:</strong> Gunakan opsi ini untuk tagihan insidental di luar SPP (misal: denda, ganti rugi, biaya kegiatan khusus) dengan rincian manual.</li>
                    <li class="mb-2"><strong>Prasyarat Profil:</strong> Tagihan hanya dapat ditujukan kepada mahasiswa yang telah memiliki <em>Student Profile</em> dan status akademik aktif.</li>
                    <li class="mb-2"><strong>Penerbitan Massal (Bulk):</strong> Sistem akan otomatis memfilter seluruh mahasiswa yang registrasi semesternya telah berstatus <code>Approved</code>.</li>
                    <li><strong>Perubahan Nominal:</strong> Tagihan yang telah terbit dan memiliki riwayat pembayaran tidak dapat diubah langsung, melainkan melalui fitur <em>Invoice Adjustment</em>.</li>
                </ul>
            </div>
        </div>
    </div>
</div>
