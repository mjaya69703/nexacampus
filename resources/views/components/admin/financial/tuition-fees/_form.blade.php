<div class="row g-3">
    <div class="col-md-6">
        <label class="form-label fw-semibold">Tahun Akademik <span class="text-danger">*</span></label>
        <select class="form-select @error('form.academic_year_id') is-invalid @enderror" wire:model="form.academic_year_id">
            <option value="">Pilih Tahun Akademik</option>
            @foreach ($academicYears as $academicYear)
                <option value="{{ $academicYear['id'] }}">{{ $academicYear['label'] }}</option>
            @endforeach
        </select>
        @error('form.academic_year_id') <div class="invalid-feedback">{{ $message }}</div> @enderror
    </div>

    <div class="col-md-6">
        <label class="form-label fw-semibold">Program Studi <span class="text-danger">*</span></label>
        <select class="form-select @error('form.study_program_id') is-invalid @enderror" wire:model="form.study_program_id">
            <option value="">Pilih Program Studi</option>
            @foreach ($studyPrograms as $studyProgram)
                <option value="{{ $studyProgram['id'] }}">{{ $studyProgram['label'] }}</option>
            @endforeach
        </select>
        @error('form.study_program_id') <div class="invalid-feedback">{{ $message }}</div> @enderror
    </div>

    <div class="col-md-4">
        <label class="form-label fw-semibold">Semester Ke- <span class="text-danger">*</span></label>
        <input type="number" min="1" max="14" class="form-control @error('form.semester') is-invalid @enderror" wire:model="form.semester" placeholder="Contoh: 1">
        @error('form.semester') <div class="invalid-feedback">{{ $message }}</div> @enderror
    </div>

    <div class="col-md-4">
        <label class="form-label fw-semibold">Tenggat Pembayaran (Deadline) <span class="text-danger">*</span></label>
        <input type="date" class="form-control @error('form.payment_deadline') is-invalid @enderror" wire:model="form.payment_deadline">
        @error('form.payment_deadline') <div class="invalid-feedback">{{ $message }}</div> @enderror
    </div>

    <div class="col-md-4">
        <label class="form-label fw-semibold">Status Tarif <span class="text-danger">*</span></label>
        <select class="form-select @error('form.is_active') is-invalid @enderror" wire:model="form.is_active">
            <option value="1">Aktif (Berlaku)</option>
            <option value="0">Nonaktif (Arsip)</option>
        </select>
        @error('form.is_active') <div class="invalid-feedback">{{ $message }}</div> @enderror
    </div>

    <div class="col-md-3">
        <label class="form-label fw-semibold">Biaya Pokok SPP (Base Fee) <span class="text-danger">*</span></label>
        <div class="input-group">
            <span class="input-group-text">Rp</span>
            <input type="number" min="0" step="1000" class="form-control @error('form.base_fee') is-invalid @enderror" wire:model="form.base_fee">
        </div>
        @error('form.base_fee') <div class="text-danger small mt-1">{{ $message }}</div> @enderror
    </div>

    <div class="col-md-3">
        <label class="form-label fw-semibold">Biaya Praktikum / Lab</label>
        <div class="input-group">
            <span class="input-group-text">Rp</span>
            <input type="number" min="0" step="1000" class="form-control @error('form.lab_fee') is-invalid @enderror" wire:model="form.lab_fee">
        </div>
        @error('form.lab_fee') <div class="text-danger small mt-1">{{ $message }}</div> @enderror
    </div>

    <div class="col-md-3">
        <label class="form-label fw-semibold">Biaya Perpustakaan</label>
        <div class="input-group">
            <span class="input-group-text">Rp</span>
            <input type="number" min="0" step="1000" class="form-control @error('form.library_fee') is-invalid @enderror" wire:model="form.library_fee">
        </div>
        @error('form.library_fee') <div class="text-danger small mt-1">{{ $message }}</div> @enderror
    </div>

    <div class="col-md-3">
        <label class="form-label fw-semibold">Biaya Kegiatan Mahasiswa</label>
        <div class="input-group">
            <span class="input-group-text">Rp</span>
            <input type="number" min="0" step="1000" class="form-control @error('form.activity_fee') is-invalid @enderror" wire:model="form.activity_fee">
        </div>
        @error('form.activity_fee') <div class="text-danger small mt-1">{{ $message }}</div> @enderror
    </div>

    <div class="col-md-4">
        <label class="form-label fw-semibold">Denda Keterlambatan / Hari</label>
        <div class="input-group">
            <span class="input-group-text">Rp</span>
            <input type="number" min="0" step="1000" class="form-control @error('form.late_penalty_per_day') is-invalid @enderror" wire:model="form.late_penalty_per_day">
        </div>
        @error('form.late_penalty_per_day') <div class="text-danger small mt-1">{{ $message }}</div> @enderror
    </div>

    <div class="col-md-8">
        <label class="form-label fw-semibold">Catatan Tambahan (Internal Notes)</label>
        <input type="text" class="form-control @error('form.notes') is-invalid @enderror" wire:model="form.notes" placeholder="Contoh: Tarif SK Rektor No. 12/2026...">
        @error('form.notes') <div class="invalid-feedback">{{ $message }}</div> @enderror
    </div>
</div>

<div class="d-flex justify-content-end gap-2 mt-4 pt-3 border-top">
    <a href="{{ route('admin.financial.tuition-fees.index') }}" class="btn btn-outline-secondary rounded-pill px-4 fw-semibold">
        Batal
    </a>
    <button type="submit" class="btn btn-primary rounded-pill px-4 fw-semibold shadow-sm">
        <i class="fas fa-save me-1"></i> Simpan Tarif SPP
    </button>
</div>
