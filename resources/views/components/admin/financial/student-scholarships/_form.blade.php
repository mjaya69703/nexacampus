<div class="row g-3">
    <div class="col-md-6">
        <label class="form-label fw-semibold">Pilih Mahasiswa <span class="text-danger">*</span></label>
        <input type="text" class="form-control mb-2 rounded-pill px-3" wire:model.live.debounce.300ms="studentSearch" placeholder="Cari berdasarkan NIM, Nama, atau Email...">
        @if(property_exists($this, 'selectedStudentProfileIds'))
            <div class="border rounded-3 p-3 bg-light bg-opacity-50" style="max-height: 240px; overflow-y: auto;">
                @forelse($this->studentOptions() as $student)
                    <label class="form-check mb-2 d-block" wire:key="scholarship-student-{{ $student['id'] }}">
                        <input class="form-check-input" type="checkbox" wire:model="selectedStudentProfileIds" value="{{ $student['id'] }}">
                        <span class="form-check-label fw-medium">{{ $student['label'] }}</span>
                    </label>
                @empty
                    <div class="text-secondary small text-center py-2">Mahasiswa tidak ditemukan.</div>
                @endforelse
            </div>
            <div class="small text-secondary mt-1">
                <i class="fas fa-check-circle text-primary me-1"></i> {{ count($selectedStudentProfileIds) }} mahasiswa terpilih. Pencarian menampilkan maksimal 25 kandidat teratas.
            </div>
            @error('selectedStudentProfileIds') <div class="text-danger small mt-1">{{ $message }}</div> @enderror
            @error('selectedStudentProfileIds.*') <div class="text-danger small mt-1">{{ $message }}</div> @enderror
        @else
            <select class="form-select @error('form.student_profile_id') is-invalid @enderror" wire:model="form.student_profile_id">
                <option value="">Pilih Mahasiswa</option>
                @foreach($this->studentOptions() as $student)
                    <option value="{{ $student['id'] }}">{{ $student['label'] }}</option>
                @endforeach
            </select>
            @error('form.student_profile_id') <div class="invalid-feedback">{{ $message }}</div> @enderror
        @endif
    </div>

    <div class="col-md-6">
        <label class="form-label fw-semibold">Program Beasiswa <span class="text-danger">*</span></label>
        <select class="form-select @error('form.scholarship_id') is-invalid @enderror" wire:model="form.scholarship_id">
            <option value="">Pilih Program Beasiswa</option>
            @foreach($scholarships as $scholarship)
                <option value="{{ $scholarship['id'] }}">{{ $scholarship['label'] }}</option>
            @endforeach
        </select>
        @error('form.scholarship_id') <div class="invalid-feedback">{{ $message }}</div> @enderror
    </div>

    <div class="col-md-4">
        <label class="form-label fw-semibold">Tahun Akademik Berlaku</label>
        <select class="form-select @error('form.academic_year_id') is-invalid @enderror" wire:model="form.academic_year_id">
            <option value="">Berlaku Umum (Semua Tahun)</option>
            @foreach($academicYears as $year)
                <option value="{{ $year['id'] }}">{{ $year['label'] }}</option>
            @endforeach
        </select>
        @error('form.academic_year_id') <div class="invalid-feedback">{{ $message }}</div> @enderror
    </div>

    <div class="col-md-4">
        <label class="form-label fw-semibold">Semester Berlaku</label>
        <input type="number" min="1" max="14" class="form-control @error('form.semester') is-invalid @enderror" wire:model="form.semester" placeholder="Semua semester">
        @error('form.semester') <div class="invalid-feedback">{{ $message }}</div> @enderror
    </div>

    <div class="col-md-4">
        <label class="form-label fw-semibold">Status Alokasi <span class="text-danger">*</span></label>
        <select class="form-select @error('form.status') is-invalid @enderror" wire:model="form.status">
            <option value="active">Aktif (Diterapkan)</option>
            <option value="completed">Selesai (Completed)</option>
            <option value="revoked">Dicabut (Revoked)</option>
        </select>
        @error('form.status') <div class="invalid-feedback">{{ $message }}</div> @enderror
    </div>

    <div class="col-md-6">
        <label class="form-label fw-semibold">Tanggal Mulai Berlaku</label>
        <input type="date" class="form-control @error('form.start_date') is-invalid @enderror" wire:model="form.start_date">
        @error('form.start_date') <div class="invalid-feedback">{{ $message }}</div> @enderror
    </div>

    <div class="col-md-6">
        <label class="form-label fw-semibold">Tanggal Selesai / Berakhir</label>
        <input type="date" class="form-control @error('form.end_date') is-invalid @enderror" wire:model="form.end_date">
        @error('form.end_date') <div class="invalid-feedback">{{ $message }}</div> @enderror
    </div>

    <div class="col-12">
        <label class="form-label fw-semibold">Catatan Internal / SK Penetapan</label>
        <textarea class="form-control @error('form.notes') is-invalid @enderror" rows="3" wire:model="form.notes" placeholder="Contoh: SK Penetapan Beasiswa No. 045/SK/2026..."></textarea>
        @error('form.notes') <div class="invalid-feedback">{{ $message }}</div> @enderror
    </div>
</div>

<div class="alert alert-info rounded-3 mt-4 mb-3 d-flex align-items-center">
    <i class="fas fa-info-circle fs-4 me-3"></i>
    <div>
        <strong>Catatan Sistem:</strong> Kosongkan tahun akademik atau semester apabila beasiswa bersifat umum/sepanjang masa studi. Invoice yang sudah terlanjur terbit sebelum beasiswa dialokasikan dapat dipotong secara manual menggunakan fitur penyesuaian (adjustment) pada detail invoice.
    </div>
</div>

<div class="d-flex justify-content-end gap-2 pt-3 border-top">
    <a href="{{ route('admin.financial.student-scholarships.index') }}" class="btn btn-outline-secondary rounded-pill px-4 fw-semibold">
        Batal
    </a>
    <button type="submit" class="btn btn-primary rounded-pill px-4 fw-semibold shadow-sm">
        <i class="fas fa-save me-1"></i> Simpan Alokasi Beasiswa
    </button>
</div>
