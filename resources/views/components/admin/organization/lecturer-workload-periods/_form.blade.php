<form wire:submit.prevent="save" class="row g-4">
    <div class="col-md-6">
        <label class="form-label fw-bold text-dark required">Nama Periode BKD</label>
        <input type="text" class="form-control rounded-3 @error('form.name') is-invalid @enderror" wire:model.defer="form.name" placeholder="Contoh: BKD Semester Ganjil TA 2025/2026">
        @error('form.name') <div class="invalid-feedback">{{ $message }}</div> @enderror
    </div>

    <div class="col-md-3">
        <label class="form-label fw-bold text-dark required">Kode Periode</label>
        <input type="text" class="form-control rounded-3 @error('form.code') is-invalid @enderror" wire:model.defer="form.code" placeholder="Contoh: BKD-20251">
        @error('form.code') <div class="invalid-feedback">{{ $message }}</div> @enderror
    </div>

    <div class="col-md-3">
        <label class="form-label fw-bold text-dark required">Status Siklus</label>
        <select class="form-select rounded-3 @error('form.status') is-invalid @enderror" wire:model.defer="form.status">
            <option value="draft">Draf (Belum Dibuka)</option>
            <option value="open">Dibuka (Masa Pengajuan)</option>
            <option value="review">Masa Review & Asesmen</option>
            <option value="closed">Ditutup / Selesai</option>
        </select>
        @error('form.status') <div class="invalid-feedback">{{ $message }}</div> @enderror
    </div>

    <div class="col-md-6">
        <label class="form-label fw-bold text-dark">Tautan Tahun Akademik (Opsional)</label>
        <select class="form-select rounded-3 @error('form.academic_year_id') is-invalid @enderror" wire:model.defer="form.academic_year_id">
            <option value="">-- Tidak Dibatasi / Umum --</option>
            @foreach ($academicYears as $year)
                <option value="{{ $year->id }}">{{ $year->name }}</option>
            @endforeach
        </select>
        @error('form.academic_year_id') <div class="invalid-feedback">{{ $message }}</div> @enderror
    </div>

    <div class="col-md-6">
        <label class="form-label fw-bold text-dark">Tautan Periode Akademik (Semester)</label>
        <select class="form-select rounded-3 @error('form.academic_period_id') is-invalid @enderror" wire:model.defer="form.academic_period_id">
            <option value="">-- Tidak Dibatasi / Umum --</option>
            @foreach ($academicPeriods as $period)
                <option value="{{ $period->id }}">{{ $period->name }} ({{ strtoupper($period->type) }})</option>
            @endforeach
        </select>
        @error('form.academic_period_id') <div class="invalid-feedback">{{ $message }}</div> @enderror
    </div>

    <div class="col-md-3">
        <label class="form-label fw-bold text-dark">Tanggal Mulai Pengajuan</label>
        <input type="date" class="form-control rounded-3 @error('form.starts_at') is-invalid @enderror" wire:model.defer="form.starts_at">
        @error('form.starts_at') <div class="invalid-feedback">{{ $message }}</div> @enderror
    </div>

    <div class="col-md-3">
        <label class="form-label fw-bold text-dark">Tanggal Batas Akhir (Deadline)</label>
        <input type="date" class="form-control rounded-3 @error('form.ends_at') is-invalid @enderror" wire:model.defer="form.ends_at">
        @error('form.ends_at') <div class="invalid-feedback">{{ $message }}</div> @enderror
    </div>

    <div class="col-md-3">
        <label class="form-label fw-bold text-dark required">Minimum SKS Wajib</label>
        <div class="input-group">
            <input type="number" step="0.01" class="form-control rounded-start-3 @error('form.minimum_sks') is-invalid @enderror" wire:model.defer="form.minimum_sks" placeholder="12.00">
            <span class="input-group-text bg-light text-secondary rounded-end-3">SKS</span>
        </div>
        @error('form.minimum_sks') <div class="invalid-feedback d-block">{{ $message }}</div> @enderror
        <div class="form-text small">Standar UU Guru & Dosen: Min. 12 SKS.</div>
    </div>

    <div class="col-md-3">
        <label class="form-label fw-bold text-dark required">Maksimum SKS Diakui</label>
        <div class="input-group">
            <input type="number" step="0.01" class="form-control rounded-start-3 @error('form.maximum_sks') is-invalid @enderror" wire:model.defer="form.maximum_sks" placeholder="16.00">
            <span class="input-group-text bg-light text-secondary rounded-end-3">SKS</span>
        </div>
        @error('form.maximum_sks') <div class="invalid-feedback d-block">{{ $message }}</div> @enderror
        <div class="form-text small">Standar UU Guru & Dosen: Maks. 16 SKS.</div>
    </div>

    <div class="col-12">
        <label class="form-label fw-bold text-dark">Catatan & Keterangan Periode</label>
        <textarea class="form-control rounded-3 @error('form.notes') is-invalid @enderror" rows="3" wire:model.defer="form.notes" placeholder="Tuliskan keterangan pengingat, regulasi yang berlaku, atau instruksi pengisian untuk para dosen..."></textarea>
        @error('form.notes') <div class="invalid-feedback">{{ $message }}</div> @enderror
    </div>

    <div class="col-12 border-top pt-4 mt-4 d-flex justify-content-end gap-2">
        <a href="{{ route('admin.organization.lecturer-workload-periods.index') }}" class="btn btn-light rounded-pill px-4 fw-medium">
            <i class="fas fa-times me-2"></i> Batal
        </a>
        <button type="submit" class="btn btn-primary rounded-pill shadow-sm px-4 fw-medium">
            <i class="fas fa-save me-2"></i> Simpan Periode BKD
        </button>
    </div>
</form>
