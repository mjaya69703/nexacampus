<div class="row g-4">
    <div class="col-md-4">
        <label class="form-label fw-bold text-dark required">Kode Rubrik</label>
        <input type="text" class="form-control rounded-3 @error('form.code') is-invalid @enderror" wire:model.defer="form.code" placeholder="Contoh: STANDAR-2026">
        @error('form.code') <div class="invalid-feedback">{{ $message }}</div> @enderror
    </div>

    <div class="col-md-8">
        <label class="form-label fw-bold text-dark required">Nama Rubrik Penilaian</label>
        <input type="text" class="form-control rounded-3 @error('form.name') is-invalid @enderror" wire:model.defer="form.name" placeholder="Contoh: Rubrik Evaluasi Kinerja Dosen Reguler">
        @error('form.name') <div class="invalid-feedback">{{ $message }}</div> @enderror
    </div>

    <div class="col-12">
        <h6 class="fw-bold text-dark mb-0 pt-2 border-top"><i class="fas fa-percent text-primary me-2"></i>Komposisi Persentase Bobot Penilaian (Disarankan Total 100%)</h6>
    </div>

    <div class="col-6 col-md-3">
        <label class="form-label fw-bold text-dark required">Bobot EDOM (%)</label>
        <div class="input-group">
            <input type="number" step="0.01" min="0" class="form-control rounded-start-3 @error('form.edom_weight') is-invalid @enderror" wire:model.defer="form.edom_weight">
            <span class="input-group-text bg-light text-secondary rounded-end-3">%</span>
        </div>
        @error('form.edom_weight') <div class="invalid-feedback d-block">{{ $message }}</div> @enderror
        <div class="form-text small">Penilaian mahasiswa.</div>
    </div>

    <div class="col-6 col-md-3">
        <label class="form-label fw-bold text-dark required">Bobot Mengajar (%)</label>
        <div class="input-group">
            <input type="number" step="0.01" min="0" class="form-control rounded-start-3 @error('form.teaching_weight') is-invalid @enderror" wire:model.defer="form.teaching_weight">
            <span class="input-group-text bg-light text-secondary rounded-end-3">%</span>
        </div>
        @error('form.teaching_weight') <div class="invalid-feedback d-block">{{ $message }}</div> @enderror
        <div class="form-text small">Kehadiran perkuliahan.</div>
    </div>

    <div class="col-6 col-md-3">
        <label class="form-label fw-bold text-dark required">Bobot Absensi (%)</label>
        <div class="input-group">
            <input type="number" step="0.01" min="0" class="form-control rounded-start-3 @error('form.attendance_weight') is-invalid @enderror" wire:model.defer="form.attendance_weight">
            <span class="input-group-text bg-light text-secondary rounded-end-3">%</span>
        </div>
        @error('form.attendance_weight') <div class="invalid-feedback d-block">{{ $message }}</div> @enderror
        <div class="form-text small">Kedisiplinan harian.</div>
    </div>

    <div class="col-6 col-md-3">
        <label class="form-label fw-bold text-dark required">Bobot BKD (%)</label>
        <div class="input-group">
            <input type="number" step="0.01" min="0" class="form-control rounded-start-3 @error('form.workload_weight') is-invalid @enderror" wire:model.defer="form.workload_weight">
            <span class="input-group-text bg-light text-secondary rounded-end-3">%</span>
        </div>
        @error('form.workload_weight') <div class="invalid-feedback d-block">{{ $message }}</div> @enderror
        <div class="form-text small">Capaian SKS tri dharma.</div>
    </div>

    <div class="col-12">
        <h6 class="fw-bold text-dark mb-0 pt-2 border-top"><i class="fas fa-sliders-h text-primary me-2"></i>Ambang Batas Minimum & Target Kelayakan</h6>
    </div>

    <div class="col-md-4">
        <label class="form-label fw-bold text-dark required">Minimal Respon EDOM (Mahasiswa)</label>
        <input type="number" min="1" class="form-control rounded-3 @error('form.minimum_responses') is-invalid @enderror" wire:model.defer="form.minimum_responses">
        @error('form.minimum_responses') <div class="invalid-feedback">{{ $message }}</div> @enderror
        <div class="form-text small">Jumlah minimal kuorum mahasiswa pengisi EDOM agar valid.</div>
    </div>

    <div class="col-md-4">
        <label class="form-label fw-bold text-dark required">Target BKD (SKS/Semester)</label>
        <input type="number" step="0.01" min="1" class="form-control rounded-3 @error('form.target_workload_sks') is-invalid @enderror" wire:model.defer="form.target_workload_sks">
        @error('form.target_workload_sks') <div class="invalid-feedback">{{ $message }}</div> @enderror
        <div class="form-text small">Target SKS BKD standar per semester.</div>
    </div>

    <div class="col-md-4 d-flex flex-column justify-content-end pb-1">
        <div class="p-3 bg-light rounded-3 border">
            <div class="form-check form-switch mb-0">
                <input class="form-check-input ms-0 me-3" type="checkbox" id="rubric_is_active" wire:model.defer="form.is_active" style="cursor: pointer;">
                <label class="form-check-label fw-bold text-dark mb-0" for="rubric_is_active" style="cursor: pointer;">Jadikan Rubrik Aktif Utama</label>
            </div>
        </div>
        @error('form.is_active') <div class="text-danger small mt-1">{{ $message }}</div> @enderror
    </div>

    <div class="col-12">
        <label class="form-label fw-bold text-dark">Catatan Tambahan (Opsional)</label>
        <textarea class="form-control rounded-3 @error('form.notes') is-invalid @enderror" rows="2" wire:model.defer="form.notes" placeholder="Keterangan mengenai dasar keputusan penetapan bobot rubrik ini..."></textarea>
        @error('form.notes') <div class="invalid-feedback">{{ $message }}</div> @enderror
    </div>
</div>
