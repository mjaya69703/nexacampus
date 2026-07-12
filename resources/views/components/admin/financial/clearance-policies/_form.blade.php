<div class="row g-3">
    <div class="col-md-6">
        <label class="form-label fw-semibold">Jenis Tagihan Pemicu <span class="text-danger">*</span></label>
        <select class="form-select @error('form.invoice_type') is-invalid @enderror" wire:model="form.invoice_type">
            @foreach($invoiceTypes as $type)
                <option value="{{ $type['id'] }}">{{ $type['label'] }}</option>
            @endforeach
        </select>
        @error('form.invoice_type') <div class="invalid-feedback">{{ $message }}</div> @enderror
    </div>

    <div class="col-md-6">
        <label class="form-label fw-semibold">Target Layanan Akademik <span class="text-danger">*</span></label>
        <select class="form-select @error('form.hold_type') is-invalid @enderror" wire:model="form.hold_type">
            @foreach($holdTypes as $type)
                <option value="{{ $type['id'] }}">{{ $type['label'] }}</option>
            @endforeach
        </select>
        @error('form.hold_type') <div class="invalid-feedback">{{ $message }}</div> @enderror
    </div>

    <div class="col-md-4">
        <label class="form-label fw-semibold">Mode Penegakan <span class="text-danger">*</span></label>
        <select class="form-select @error('form.mode') is-invalid @enderror" wire:model="form.mode">
            <option value="warning">Hanya Peringatan (Warning Only)</option>
            <option value="blocking">Pemblokiran Layanan (Blocking)</option>
        </select>
        @error('form.mode') <div class="invalid-feedback">{{ $message }}</div> @enderror
    </div>

    <div class="col-md-4">
        <label class="form-label fw-semibold">Masa Tenggang Keterlambatan (Grace Days) <span class="text-danger">*</span></label>
        <div class="input-group">
            <input type="number" min="0" class="form-control @error('form.grace_days') is-invalid @enderror" wire:model="form.grace_days">
            <span class="input-group-text">Hari</span>
        </div>
        @error('form.grace_days') <div class="text-danger small mt-1">{{ $message }}</div> @enderror
    </div>

    <div class="col-md-4">
        <label class="form-label fw-semibold">Status Kebijakan <span class="text-danger">*</span></label>
        <select class="form-select @error('form.is_active') is-invalid @enderror" wire:model="form.is_active">
            <option value="1">Aktif (Diberlakukan)</option>
            <option value="0">Nonaktif (Arsip)</option>
        </select>
        @error('form.is_active') <div class="invalid-feedback">{{ $message }}</div> @enderror
    </div>

    <div class="col-12">
        <label class="form-label fw-semibold">Deskripsi & Instruksi Kebijakan</label>
        <textarea class="form-control @error('form.description') is-invalid @enderror" rows="3" wire:model="form.description" placeholder="Contoh: Pembatasan pengisian KRS bagi mahasiswa yang belum melunasi SPP setelah 7 hari jatuh tempo..."></textarea>
        @error('form.description') <div class="invalid-feedback">{{ $message }}</div> @enderror
    </div>
</div>

<div class="alert alert-info rounded-3 mt-4 mb-3 d-flex align-items-center">
    <i class="fas fa-info-circle fs-4 me-3"></i>
    <div>
        <strong>Catatan Sistem:</strong> Perubahan kebijakan akan otomatis diterapkan pada evaluasi financial clearance berikutnya. Blokir yang sedang aktif dapat dilepas secara otomatis jika sudah tidak memenuhi kriteria kebijakan yang aktif.
    </div>
</div>

<div class="d-flex justify-content-end gap-2 pt-3 border-top">
    <a href="{{ route('admin.financial.clearance-policies.index') }}" class="btn btn-outline-secondary rounded-pill px-4 fw-semibold">
        Batal
    </a>
    <button type="submit" class="btn btn-primary rounded-pill px-4 fw-semibold shadow-sm">
        <i class="fas fa-save me-1"></i> Simpan Kebijakan
    </button>
</div>
