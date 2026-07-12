<div class="row g-3">
    <div class="col-md-4">
        <label class="form-label fw-semibold">Scope Program Studi</label>
        <select wire:model="form.study_program_id" class="form-select rounded-3">
            <option value="">Global (Semua Program Studi)</option>
            @foreach ($studyPrograms as $program)
                <option value="{{ $program->id }}">{{ $program->name }}</option>
            @endforeach
        </select>
        <small class="text-muted">Kosongkan jika syarat berlaku untuk seluruh mahasiswa universitas.</small>
        @error('form.study_program_id') <div class="text-danger small mt-1 d-block">{{ $message }}</div> @enderror
    </div>
    <div class="col-md-4">
        <label class="form-label fw-semibold">Kode Tipe Dokumen <span class="text-danger">*</span></label>
        <input type="text" wire:model="form.document_type" class="form-control rounded-3" placeholder="Contoh: ktp, pas_foto, bebas_pustaka">
        <small class="text-muted">Hanya huruf kecil, angka, underscore/strip tanpa spasi.</small>
        @error('form.document_type') <div class="text-danger small mt-1 d-block">{{ $message }}</div> @enderror
    </div>
    <div class="col-md-4">
        <label class="form-label fw-semibold">Label Tampilan <span class="text-danger">*</span></label>
        <input type="text" wire:model="form.label" class="form-control rounded-3" placeholder="Contoh: Scan KTP Asli">
        <small class="text-muted">Nama dokumen yang akan dibaca oleh mahasiswa di portal.</small>
        @error('form.label') <div class="text-danger small mt-1 d-block">{{ $message }}</div> @enderror
    </div>
    <div class="col-md-4">
        <label class="form-label fw-semibold">Ekstensi Diizinkan</label>
        <input type="text" wire:model="form.allowed_extensions" class="form-control rounded-3" placeholder="Contoh: pdf,jpg,jpeg,png">
        <small class="text-muted">Pisahkan dengan koma (maks. pdf, jpg, jpeg, png).</small>
        @error('form.allowed_extensions') <div class="text-danger small mt-1 d-block">{{ $message }}</div> @enderror
    </div>
    <div class="col-md-4">
        <label class="form-label fw-semibold">Ukuran Maksimal (KB)</label>
        <input type="number" min="1" wire:model="form.max_size_kb" class="form-control rounded-3" placeholder="5120">
        <small class="text-muted">1024 KB = 1 MB. Maksimal disarankan 5120 KB (5 MB).</small>
        @error('form.max_size_kb') <div class="text-danger small mt-1 d-block">{{ $message }}</div> @enderror
    </div>
    <div class="col-md-4">
        <label class="form-label fw-semibold">Nomor Urut Tampilan</label>
        <input type="number" min="0" wire:model="form.sort_order" class="form-control rounded-3" placeholder="0">
        <small class="text-muted">Semakin kecil angkanya, semakin atas posisinya di form.</small>
        @error('form.sort_order') <div class="text-danger small mt-1 d-block">{{ $message }}</div> @enderror
    </div>
    <div class="col-12">
        <label class="form-label fw-semibold">Deskripsi / Petunjuk Pengunggahan</label>
        <textarea wire:model="form.description" rows="3" class="form-control rounded-3" placeholder="Jelaskan ketentuan dokumen ini kepada mahasiswa (misal: berwarna, resolusi jelas, format terbaru)..."></textarea>
        @error('form.description') <div class="text-danger small mt-1 d-block">{{ $message }}</div> @enderror
    </div>
    <div class="col-12">
        <div class="border rounded-4 p-3 bg-light bg-opacity-50 d-flex flex-wrap gap-4">
            <div class="form-check form-switch mb-0">
                <input type="checkbox" class="form-check-input" id="is_required" wire:model="form.is_required">
                <label class="form-check-label fw-semibold" for="is_required">Wajib diunggah (Required)</label>
                <div class="text-muted small">Mahasiswa tidak dapat submit yudisium jika dokumen wajib belum diunggah.</div>
            </div>
            <div class="form-check form-switch mb-0">
                <input type="checkbox" class="form-check-input" id="is_active" wire:model="form.is_active">
                <label class="form-check-label fw-semibold" for="is_active">Persyaratan Aktif</label>
                <div class="text-muted small">Nonaktifkan jika syarat ini sudah tidak diberlakukan lagi.</div>
            </div>
        </div>
    </div>
    <div class="col-12 mt-4 d-flex justify-content-end gap-2">
        <a href="{{ route('admin.student-services.graduation-document-requirements.index') }}" class="btn btn-outline-secondary rounded-pill px-4">
            <i class="fa fa-times me-2"></i> Batal
        </a>
        <button type="button" wire:click="save" class="btn btn-primary rounded-pill px-4">
            <i class="fa fa-save me-2"></i> Simpan Persyaratan
        </button>
    </div>
</div>
