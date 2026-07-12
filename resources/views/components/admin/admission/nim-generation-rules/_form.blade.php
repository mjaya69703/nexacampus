<div class="row g-3">
    <div class="col-md-6">
        <label class="form-label fw-bold text-dark fs-7">Nama Aturan Penomoran (Rule Name) <span class="text-danger">*</span></label>
        <input type="text" class="form-control rounded-3 border-secondary border-opacity-25" wire:model.live="form.name" placeholder="Contoh: Aturan NIM S1 Reguler 2026">
        @error('form.name') <span class="text-danger fs-8 mt-1 d-block">{{ $message }}</span> @enderror
    </div>

    <div class="col-md-6">
        <label class="form-label fw-bold text-dark fs-7">Cakupan Reset Nomor Urut (Sequence Scope) <span class="text-danger">*</span></label>
        <select class="form-select rounded-3 border-secondary border-opacity-25" wire:model.live="form.sequence_scope">
            <option value="study_program_year">Per Program Studi + Tahun (Study Program + Year)</option>
            <option value="study_program">Per Program Studi (Study Program)</option>
            <option value="year">Per Tahun Angkatan (Year)</option>
            <option value="period">Per Gelombang / Periode PMB (Period)</option>
            <option value="faculty">Per Fakultas (Faculty)</option>
            <option value="class_type">Per Jenis Kelas (Class Type)</option>
            <option value="global">Global Keseluruhan Kampus (Global)</option>
        </select>
        @error('form.sequence_scope') <span class="text-danger fs-8 mt-1 d-block">{{ $message }}</span> @enderror
    </div>

    <div class="col-md-12">
        <label class="form-label fw-bold text-dark fs-7">Pola Penomoran (Pattern) <span class="text-danger">*</span></label>
        <input type="text" class="form-control rounded-3 border-secondary border-opacity-25 font-monospace" wire:model.live.debounce.300ms="form.pattern" placeholder="{yy}{program_code}{sequence}">
        <div class="mt-2 p-3 bg-light rounded-3 border fs-8 text-secondary">
            <span class="fw-bold text-dark d-block mb-1"><i class="fas fa-info-circle text-primary me-1"></i> Token yang Tersedia:</span>
            <code>{year}</code> (Tahun 4 digit: 2026), <code>{yy}</code> (Tahun 2 digit: 26), <code>{period_code}</code> (Kode Periode), <code>{faculty_code}</code> (Kode Fakultas), <code>{program_code}</code> (Kode Program Studi), <code>{class_type}</code> (Kode Kelas), <code>{sequence}</code> (Nomor Urut Otomatis)
        </div>
        @error('form.pattern') <span class="text-danger fs-8 mt-1 d-block">{{ $message }}</span> @enderror
    </div>

    <div class="col-md-3">
        <label class="form-label fw-bold text-dark fs-7">Panjang Digit Nomor Urut (Padding)</label>
        <input type="number" min="1" max="10" class="form-control rounded-3 border-secondary border-opacity-25" wire:model.live="form.sequence_padding" title="Misal 4 menghasilkan 0001">
        <small class="text-muted fs-8">Contoh: 4 &rarr; <code>0001</code></small>
        @error('form.sequence_padding') <span class="text-danger fs-8 mt-1 d-block">{{ $message }}</span> @enderror
    </div>

    <div class="col-md-3">
        <label class="form-label fw-bold text-dark fs-7">Nomor Awal (Start Number)</label>
        <input type="number" min="1" class="form-control rounded-3 border-secondary border-opacity-25" wire:model.live="form.sequence_start">
        @error('form.sequence_start') <span class="text-danger fs-8 mt-1 d-block">{{ $message }}</span> @enderror
    </div>

    <div class="col-md-3">
        <label class="form-label fw-bold text-dark fs-7">Pratinjau Hasil NIM (Preview)</label>
        <div class="form-control bg-primary bg-opacity-10 text-primary fw-bold font-monospace border-primary border-opacity-25 rounded-3 d-flex align-items-center justify-content-between">
            <span>{{ $preview }}</span>
            <i class="fas fa-eye fs-8"></i>
        </div>
    </div>

    <div class="col-md-3 d-flex align-items-center pt-3">
        <div class="form-check form-switch mt-2">
            <input class="form-check-input" type="checkbox" id="is_active_rule" wire:model="form.is_active">
            <label class="form-check-label fw-bold text-dark" for="is_active_rule">Jadikan Aturan Utama Aktif</label>
        </div>
    </div>

    <div class="col-12">
        <label class="form-label fw-bold text-dark fs-7">Keterangan / Deskripsi</label>
        <textarea rows="3" class="form-control rounded-3 border-secondary border-opacity-25" wire:model="form.description" placeholder="Catatan internal kegunaan atau ruang lingkup penerapan pola NIM ini..."></textarea>
    </div>

    <div class="col-12 d-flex justify-content-end gap-3 mt-4 pt-3 border-top border-light">
        <a href="{{ route('admin.admission.nim-generation-rules.index') }}" class="btn btn-light rounded-pill px-4 py-2 fw-bold text-secondary border shadow-sm">
            <i class="fas fa-times me-1"></i> Batal
        </a>
        <button type="submit" class="btn btn-primary rounded-pill px-5 py-2 fw-bold shadow-sm d-flex align-items-center gap-2">
            <i class="fas fa-save"></i> Simpan Aturan Penomoran
        </button>
    </div>
</div>
