<div class="row g-4">
    <div class="col-md-6">
        <label class="form-label fw-bold text-dark required">Nama Periode</label>
        <input type="text" class="form-control rounded-3 @error('form.name') is-invalid @enderror" wire:model.defer="form.name" placeholder="Contoh: EDOM Semester Ganjil 2025/2026">
        @error('form.name') <div class="invalid-feedback">{{ $message }}</div> @enderror
    </div>

    <div class="col-md-3">
        <label class="form-label fw-bold text-dark required">Kode Periode</label>
        <input type="text" class="form-control rounded-3 @error('form.code') is-invalid @enderror" wire:model.defer="form.code" placeholder="EDOM_20251">
        @error('form.code') <div class="invalid-feedback">{{ $message }}</div> @enderror
    </div>

    <div class="col-md-3">
        <label class="form-label fw-bold text-dark required">Status Periode</label>
        <select class="form-select rounded-3 @error('form.status') is-invalid @enderror" wire:model.defer="form.status">
            <option value="draft">Draf (Belum Aktif)</option>
            <option value="open">Dibuka (Sedang Berjalan)</option>
            <option value="closed">Ditutup (Selesai)</option>
        </select>
        @error('form.status') <div class="invalid-feedback">{{ $message }}</div> @enderror
    </div>

    <div class="col-md-6">
        <label class="form-label fw-bold text-dark">Tahun Akademik</label>
        <select class="form-select rounded-3 @error('form.academic_year_id') is-invalid @enderror" wire:model.defer="form.academic_year_id">
            <option value="">-- Tidak Dibatasi --</option>
            @foreach ($academicYears as $year)
                <option value="{{ $year->id }}">{{ $year->name }}</option>
            @endforeach
        </select>
        @error('form.academic_year_id') <div class="invalid-feedback">{{ $message }}</div> @enderror
    </div>

    <div class="col-md-6">
        <label class="form-label fw-bold text-dark">Periode Akademik (Semester)</label>
        <select class="form-select rounded-3 @error('form.academic_period_id') is-invalid @enderror" wire:model.defer="form.academic_period_id">
            <option value="">-- Tidak Dibatasi --</option>
            @foreach ($academicPeriods as $period)
                <option value="{{ $period->id }}">{{ $period->name }} - {{ $period->type }}</option>
            @endforeach
        </select>
        @error('form.academic_period_id') <div class="invalid-feedback">{{ $message }}</div> @enderror
    </div>

    <div class="col-md-4">
        <label class="form-label fw-bold text-dark">Tanggal Mulai Evaluasi</label>
        <input type="date" class="form-control rounded-3 @error('form.starts_at') is-invalid @enderror" wire:model.defer="form.starts_at">
        @error('form.starts_at') <div class="invalid-feedback">{{ $message }}</div> @enderror
    </div>

    <div class="col-md-4">
        <label class="form-label fw-bold text-dark">Tanggal Selesai Evaluasi</label>
        <input type="date" class="form-control rounded-3 @error('form.ends_at') is-invalid @enderror" wire:model.defer="form.ends_at">
        @error('form.ends_at') <div class="invalid-feedback">{{ $message }}</div> @enderror
    </div>

    <div class="col-md-4">
        <label class="form-label fw-bold text-dark required">Minimal Respon Mahasiswa</label>
        <input type="number" min="1" class="form-control rounded-3 @error('form.minimum_responses') is-invalid @enderror" wire:model.defer="form.minimum_responses" placeholder="Contoh: 3">
        @error('form.minimum_responses') <div class="invalid-feedback">{{ $message }}</div> @enderror
        <div class="form-text small">Batas minimal suara mahasiswa agar nilai EDOM dosen dapat dihitung valid.</div>
    </div>

    <div class="col-12">
        <label class="form-label fw-bold text-dark">Catatan Tambahan</label>
        <textarea class="form-control rounded-3 @error('form.notes') is-invalid @enderror" rows="3" wire:model.defer="form.notes" placeholder="Tuliskan keterangan tambahan untuk periode EDOM ini..."></textarea>
        @error('form.notes') <div class="invalid-feedback">{{ $message }}</div> @enderror
    </div>
</div>
