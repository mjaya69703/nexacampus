<div class="row g-3">
    <div class="col-md-6">
        <label class="form-label fw-semibold">Nama Program Beasiswa <span class="text-danger">*</span></label>
        <input type="text" class="form-control @error('form.name') is-invalid @enderror" wire:model="form.name" placeholder="Contoh: Beasiswa Prestasi Rektor">
        @error('form.name') <div class="invalid-feedback">{{ $message }}</div> @enderror
    </div>

    <div class="col-md-3">
        <label class="form-label fw-semibold">Kategori / Jenis <span class="text-danger">*</span></label>
        <select class="form-select @error('form.type') is-invalid @enderror" wire:model="form.type">
            <option value="full">Pembebasan Penuh (Full)</option>
            <option value="partial">Potongan Sebagian (Partial)</option>
            <option value="merit">Prestasi Akademik (Merit)</option>
            <option value="need_based">Bantuan Ekonomi (Need Based)</option>
        </select>
        @error('form.type') <div class="invalid-feedback">{{ $message }}</div> @enderror
    </div>

    <div class="col-md-3">
        <label class="form-label fw-semibold">Status Program</label>
        <select class="form-select @error('form.is_active') is-invalid @enderror" wire:model="form.is_active">
            <option value="1">Aktif (Tersedia)</option>
            <option value="0">Nonaktif (Arsip)</option>
        </select>
        @error('form.is_active') <div class="invalid-feedback">{{ $message }}</div> @enderror
    </div>

    <div class="col-md-4">
        <label class="form-label fw-semibold">Metode Potongan <span class="text-danger">*</span></label>
        <select class="form-select @error('form.discount_type') is-invalid @enderror" wire:model.live="form.discount_type">
            <option value="percentage">Persentase (%) dari SPP</option>
            <option value="fixed">Nominal Tetap (Rp)</option>
        </select>
        @error('form.discount_type') <div class="invalid-feedback">{{ $message }}</div> @enderror
    </div>

    @if(($form['discount_type'] ?? 'percentage') === 'fixed')
        <div class="col-md-4">
            <label class="form-label fw-semibold">Nominal Potongan Tetap <span class="text-danger">*</span></label>
            <div class="input-group">
                <span class="input-group-text">Rp</span>
                <input type="number" min="0" step="1000" class="form-control @error('form.fixed_amount') is-invalid @enderror" wire:model="form.fixed_amount" placeholder="Contoh: 2500000">
            </div>
            @error('form.fixed_amount') <div class="text-danger small mt-1">{{ $message }}</div> @enderror
        </div>
    @else
        <div class="col-md-4">
            <label class="form-label fw-semibold">Persentase Potongan <span class="text-danger">*</span></label>
            <div class="input-group">
                <input type="number" min="0" max="100" step="0.01" class="form-control @error('form.discount_percentage') is-invalid @enderror" wire:model="form.discount_percentage" placeholder="Contoh: 50">
                <span class="input-group-text">%</span>
            </div>
            @error('form.discount_percentage') <div class="text-danger small mt-1">{{ $message }}</div> @enderror
        </div>
    @endif

    <div class="col-md-4">
        <label class="form-label fw-semibold">Durasi Berlaku (Semester)</label>
        <div class="input-group">
            <input type="number" min="1" max="20" class="form-control @error('form.duration_semesters') is-invalid @enderror" wire:model="form.duration_semesters" placeholder="Contoh: 8">
            <span class="input-group-text">Semester</span>
        </div>
        @error('form.duration_semesters') <div class="text-danger small mt-1">{{ $message }}</div> @enderror
    </div>

    <div class="col-12">
        <label class="form-label fw-semibold">Deskripsi Program</label>
        <textarea class="form-control @error('form.description') is-invalid @enderror" rows="3" wire:model="form.description" placeholder="Penjelasan singkat mengenai program beasiswa dan cakupannya..."></textarea>
        @error('form.description') <div class="invalid-feedback">{{ $message }}</div> @enderror
    </div>

    <div class="col-12">
        <label class="form-label fw-semibold">Persyaratan & Kriteria Penerima</label>
        <textarea class="form-control @error('form.requirements') is-invalid @enderror" rows="3" wire:model="form.requirements" placeholder="Contoh: IPK minimal 3.50, tidak sedang menerima beasiswa lain..."></textarea>
        @error('form.requirements') <div class="invalid-feedback">{{ $message }}</div> @enderror
    </div>
</div>

<div class="alert alert-info rounded-3 mt-4 mb-3 d-flex align-items-center">
    <i class="fas fa-info-circle fs-4 me-3"></i>
    <div>
        <strong>Catatan Sistem:</strong> Beasiswa yang dialokasikan ke mahasiswa aktif akan otomatis memotong tagihan saat proses penerbitan invoice berkala berlangsung.
    </div>
</div>

<div class="d-flex justify-content-end gap-2 pt-3 border-top">
    <a href="{{ route('admin.financial.scholarships.index') }}" class="btn btn-outline-secondary rounded-pill px-4 fw-semibold">
        Batal
    </a>
    <button type="submit" class="btn btn-primary rounded-pill px-4 fw-semibold shadow-sm">
        <i class="fas fa-save me-1"></i> Simpan Program Beasiswa
    </button>
</div>
