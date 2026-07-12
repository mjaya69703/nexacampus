<div class="row g-3">
    <div class="col-md-6">
        <label class="form-label fw-semibold">Nama Jadwal / Agenda Tagihan <span class="text-danger">*</span></label>
        <input type="text" class="form-control @error('form.name') is-invalid @enderror" wire:model="form.name" placeholder="Contoh: Tagihan SPP Semester Ganjil TA 2026/2027">
        @error('form.name') <div class="invalid-feedback">{{ $message }}</div> @enderror
    </div>
    <div class="col-md-3">
        <label class="form-label fw-semibold">Format Tagihan <span class="text-danger">*</span></label>
        <select class="form-select @error('form.invoice_kind') is-invalid @enderror" wire:model.live="form.invoice_kind">
            <option value="tuition">Template SPP / Kuliah</option>
            <option value="custom">Tagihan Kustom / Non-SPP</option>
        </select>
        @error('form.invoice_kind') <div class="invalid-feedback">{{ $message }}</div> @enderror
    </div>
    <div class="col-md-3">
        <label class="form-label fw-semibold">Target Mahasiswa <span class="text-danger">*</span></label>
        <select class="form-select @error('form.generation_mode') is-invalid @enderror" wire:model.live="form.generation_mode">
            <option value="active_students">Seluruh Mahasiswa Aktif</option>
            <option value="selected_students">Pilih Mahasiswa Tertentu</option>
            <option value="single">Satu Mahasiswa Khusus</option>
        </select>
        @error('form.generation_mode') <div class="invalid-feedback">{{ $message }}</div> @enderror
    </div>

    @if(($form['generation_mode'] ?? 'active_students') === 'single')
        <div class="col-12">
            <label class="form-label fw-semibold">Pilih Satu Mahasiswa <span class="text-danger">*</span></label>
            <input type="search" class="form-control mb-2 rounded-pill px-3" wire:model.live.debounce.400ms="studentSearch" placeholder="Cari berdasarkan NIM, Nama, atau Email...">
            <div class="border rounded-3 p-3 bg-light bg-opacity-50" style="max-height: 220px; overflow-y: auto;">
                @forelse($this->studentOptions() as $student)
                    <label class="form-check py-1 d-block">
                        <input class="form-check-input" type="radio" wire:model="form.student_profile_id" value="{{ $student['id'] }}">
                        <span class="form-check-label fw-medium">{{ $student['label'] }}</span>
                    </label>
                @empty
                    <div class="text-secondary small text-center py-2">Mahasiswa tidak ditemukan.</div>
                @endforelse
            </div>
            @error('form.student_profile_id') <div class="text-danger small mt-1">{{ $message }}</div> @enderror
        </div>
    @elseif(($form['generation_mode'] ?? 'active_students') === 'selected_students')
        <div class="col-12">
            <label class="form-label fw-semibold">Pilih Mahasiswa <span class="text-danger">*</span></label>
            <input type="search" class="form-control mb-2 rounded-pill px-3" wire:model.live.debounce.400ms="studentSearch" placeholder="Cari berdasarkan NIM, Nama, atau Email...">
            <div class="border rounded-3 p-3 bg-light bg-opacity-50" style="max-height: 260px; overflow-y: auto;">
                @forelse($this->studentOptions() as $student)
                    <label class="form-check py-1 d-block">
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
        </div>
    @else
        <div class="col-12">
            <div class="alert alert-info border-0 rounded-3 d-flex align-items-center mb-0">
                <i class="fas fa-users fs-4 me-3"></i>
                <div>
                    <strong>Target Otomatis:</strong> Penjadwalan ini akan menerbitkan tagihan kepada seluruh mahasiswa dengan status akademik <strong>Aktif</strong> yang telah menyelesaikan registrasi pada tahun akademik dan semester terkait.
                </div>
            </div>
        </div>
    @endif

    <div class="col-md-4">
        <label class="form-label fw-semibold">Tahun Akademik @if(($form['invoice_kind'] ?? 'tuition') === 'tuition') <span class="text-danger">*</span> @endif</label>
        <select class="form-select @error('form.academic_year_id') is-invalid @enderror" wire:model="form.academic_year_id">
            <option value="">Pilih Tahun Akademik</option>
            @foreach($academicYears as $year)
                <option value="{{ $year['id'] }}">{{ $year['label'] }}</option>
            @endforeach
        </select>
        @error('form.academic_year_id') <div class="invalid-feedback">{{ $message }}</div> @enderror
    </div>
    <div class="col-md-2">
        <label class="form-label fw-semibold">Semester @if(($form['invoice_kind'] ?? 'tuition') === 'tuition') <span class="text-danger">*</span> @endif</label>
        <input type="number" min="1" max="14" class="form-control @error('form.semester') is-invalid @enderror" wire:model="form.semester">
        @error('form.semester') <div class="invalid-feedback">{{ $message }}</div> @enderror
    </div>
    <div class="col-md-3">
        <label class="form-label fw-semibold">Waktu Eksekusi / Terbit <span class="text-danger">*</span></label>
        <input type="datetime-local" class="form-control @error('form.publish_at') is-invalid @enderror" wire:model="form.publish_at">
        @error('form.publish_at') <div class="invalid-feedback">{{ $message }}</div> @enderror
    </div>
    <div class="col-md-3">
        <label class="form-label fw-semibold">Tanggal Jatuh Tempo <span class="text-danger">*</span></label>
        <input type="date" class="form-control @error('form.due_date') is-invalid @enderror" wire:model="form.due_date">
        @error('form.due_date') <div class="invalid-feedback">{{ $message }}</div> @enderror
    </div>

    @if(($form['invoice_kind'] ?? 'tuition') === 'custom')
        <div class="col-md-4">
            <label class="form-label fw-semibold">Kategori Invoice <span class="text-danger">*</span></label>
            <select class="form-select @error('form.invoice_type') is-invalid @enderror" wire:model="form.invoice_type">
                @foreach(config('financial.invoice_types', []) as $value => $label)
                    <option value="{{ $value }}">{{ $label }}</option>
                @endforeach
            </select>
            @error('form.invoice_type') <div class="invalid-feedback">{{ $message }}</div> @enderror
        </div>
        <div class="col-12">
            <div class="d-flex justify-content-between align-items-center mb-2 mt-2">
                <label class="form-label fw-semibold mb-0">Rincian Komponen Biaya (Items)</label>
                <button type="button" class="btn btn-outline-primary btn-sm rounded-pill px-3" wire:click="addItem">
                    <i class="fas fa-plus me-1"></i> Tambah Komponen
                </button>
            </div>
            @foreach($items as $index => $item)
                <div class="row g-2 mb-2">
                    <div class="col-md-3">
                        <select class="form-select" wire:model="items.{{ $index }}.item_type">
                            <option value="fee">Biaya Pokok (Fee)</option>
                            <option value="discount">Potongan (Discount)</option>
                            <option value="adjustment">Penyesuaian (Adjustment)</option>
                            <option value="penalty">Denda (Penalty)</option>
                        </select>
                    </div>
                    <div class="col-md-5">
                        <input type="text" class="form-control" wire:model="items.{{ $index }}.description" placeholder="Keterangan item biaya">
                    </div>
                    <div class="col-md-3">
                        <input type="number" step="1" min="1" class="form-control" wire:model="items.{{ $index }}.amount" placeholder="Nominal (Rp)">
                    </div>
                    <div class="col-md-1">
                        <button type="button" class="btn btn-outline-danger w-100" wire:click="removeItem({{ $index }})">
                            <i class="fas fa-trash"></i>
                        </button>
                    </div>
                </div>
            @endforeach
            @error('items') <div class="text-danger small mt-1">{{ $message }}</div> @enderror
        </div>
    @endif

    <div class="col-md-6 d-flex align-items-center pt-3">
        <div class="form-check form-switch fs-6">
            <input class="form-check-input" type="checkbox" role="switch" id="issueImmediately" wire:model="form.issue_immediately">
            <label class="form-check-label fw-semibold ms-2" for="issueImmediately">Otomatis Terbitkan Tagihan Resmi (Issued)</label>
            <div class="small text-secondary fw-normal">Jika dimatikan, invoice akan dibuat sebagai draf dan perlu dikonfirmasi manual.</div>
        </div>
    </div>
    <div class="col-md-6 d-flex align-items-center pt-3">
        <div class="form-check form-switch fs-6">
            <input class="form-check-input" type="checkbox" role="switch" id="isActive" wire:model="form.is_active">
            <label class="form-check-label fw-semibold ms-2" for="isActive">Status Jadwal Aktif</label>
            <div class="small text-secondary fw-normal">Jadwal yang aktif akan otomatis dijalankan oleh sistem pada waktu terbit.</div>
        </div>
    </div>
    <div class="col-12 mt-3">
        <label class="form-label fw-semibold">Catatan Administrasi</label>
        <textarea class="form-control @error('form.notes') is-invalid @enderror" rows="3" wire:model="form.notes" placeholder="Catatan internal untuk tim keuangan / referensi dasar hukum penetapan biaya..."></textarea>
        @error('form.notes') <div class="invalid-feedback">{{ $message }}</div> @enderror
    </div>
</div>

<div class="d-flex justify-content-end gap-2 pt-4 mt-4 border-top">
    <a href="{{ route('admin.financial.invoice-schedules.index') }}" class="btn btn-outline-secondary rounded-pill px-4 fw-semibold">
        Batal
    </a>
    <button type="submit" class="btn btn-primary rounded-pill px-4 fw-semibold shadow-sm">
        <i class="fas fa-save me-1"></i> Simpan Jadwal
    </button>
</div>
