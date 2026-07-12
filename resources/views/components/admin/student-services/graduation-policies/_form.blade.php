<div class="row g-3">
    <div class="col-md-6">
        <label class="form-label fw-semibold">Nama Aturan / Policy <span class="text-danger">*</span></label>
        <input type="text" class="form-control rounded-3" wire:model="form.name" placeholder="Contoh: Standar Yudisium S1 2026">
        @error('form.name') <span class="text-danger small mt-1 d-block">{{ $message }}</span> @enderror
    </div>
    <div class="col-md-6">
        <label class="form-label fw-semibold">Lingkup Program Studi</label>
        <select class="form-select rounded-3" wire:model="form.study_program_id">
            <option value="">Global Fallback (Berlaku untuk Semua Program Studi)</option>
            @foreach ($studyPrograms as $program)
                <option value="{{ $program->id }}">{{ $program->name }}</option>
            @endforeach
        </select>
        <small class="text-muted">Aturan spesifik prodi akan diprioritaskan; jika tidak ada, sistem memakai global fallback.</small>
        @error('form.study_program_id') <span class="text-danger small mt-1 d-block">{{ $message }}</span> @enderror
    </div>
    <div class="col-md-4">
        <label class="form-label fw-semibold">Minimal Semester <span class="text-danger">*</span></label>
        <input type="number" min="1" max="20" class="form-control rounded-3" wire:model="form.minimum_semester" placeholder="8">
        @error('form.minimum_semester') <span class="text-danger small mt-1 d-block">{{ $message }}</span> @enderror
    </div>
    <div class="col-md-4">
        <label class="form-label fw-semibold">Minimal SKS Lulus <span class="text-danger">*</span></label>
        <input type="number" min="0" max="300" class="form-control rounded-3" wire:model="form.minimum_passed_credits" placeholder="144">
        @error('form.minimum_passed_credits') <span class="text-danger small mt-1 d-block">{{ $message }}</span> @enderror
    </div>
    <div class="col-md-4">
        <label class="form-label fw-semibold">Minimal IPK <span class="text-danger">*</span></label>
        <input type="number" min="0" max="4" step="0.01" class="form-control rounded-3" wire:model="form.minimum_gpa" placeholder="2.00">
        @error('form.minimum_gpa') <span class="text-danger small mt-1 d-block">{{ $message }}</span> @enderror
    </div>
    <div class="col-12">
        <label class="form-label fw-semibold">Deskripsi Aturan</label>
        <textarea class="form-control rounded-3" rows="3" wire:model="form.description" placeholder="Penjelasan atau dasar hukum aturan yudisium ini..."></textarea>
        @error('form.description') <span class="text-danger small mt-1 d-block">{{ $message }}</span> @enderror
    </div>
    <div class="col-12">
        <div class="border rounded-4 p-3 bg-light bg-opacity-50">
            <div class="fw-semibold mb-3 text-dark"><i class="fa fa-clipboard-check me-2 text-primary"></i>Persyaratan Prasyarat Otomatis</div>
            <div class="row g-3">
                <div class="col-md-6">
                    <div class="form-check form-switch">
                        <input type="checkbox" class="form-check-input" id="require_active_status" wire:model="form.require_active_status">
                        <label class="form-check-label fw-semibold" for="require_active_status">Wajib Status Mahasiswa Aktif</label>
                    </div>
                </div>
                <div class="col-md-6">
                    <div class="form-check form-switch">
                        <input type="checkbox" class="form-check-input" id="require_no_financial_hold" wire:model="form.require_no_financial_hold">
                        <label class="form-check-label fw-semibold" for="require_no_financial_hold">Bebas Tunggakan Keuangan / Hold</label>
                    </div>
                </div>
                <div class="col-md-6">
                    <div class="form-check form-switch">
                        <input type="checkbox" class="form-check-input" id="require_no_incomplete_grade" wire:model="form.require_no_incomplete_grade">
                        <label class="form-check-label fw-semibold" for="require_no_incomplete_grade">Tidak Ada Nilai Belum Lengkap (E/T/K)</label>
                    </div>
                </div>
                <div class="col-md-6">
                    <div class="form-check form-switch">
                        <input type="checkbox" class="form-check-input" id="require_open_yudisium_period" wire:model="form.require_open_yudisium_period">
                        <label class="form-check-label fw-semibold" for="require_open_yudisium_period">Wajib Dalam Masa Pendaftaran Yudisium</label>
                    </div>
                </div>
                <div class="col-12 border-top pt-2 mt-2">
                    <div class="form-check form-switch">
                        <input type="checkbox" class="form-check-input" id="is_active" wire:model="form.is_active">
                        <label class="form-check-label fw-bold text-success" for="is_active">Aktifkan Policy Ini (Hanya boleh 1 policy aktif per scope)</label>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <div class="col-12 mt-4 d-flex justify-content-end gap-2">
        <a href="{{ route('admin.student-services.graduation-policies.index') }}" class="btn btn-outline-secondary rounded-pill px-4">
            <i class="fa fa-times me-2"></i> Batal
        </a>
        <button type="button" wire:click="save" class="btn btn-primary rounded-pill px-4">
            <i class="fa fa-save me-2"></i> Simpan Aturan
        </button>
    </div>
</div>
