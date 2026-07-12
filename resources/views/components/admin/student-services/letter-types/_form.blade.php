<div class="row g-3">
    <div class="col-md-5">
        <label class="form-label fw-semibold">Nama Jenis Surat <span class="text-danger">*</span></label>
        <input type="text" wire:model="form.name" class="form-control rounded-3" placeholder="Contoh: Surat Keterangan Aktif Kuliah">
        @error('form.name') <div class="text-danger small mt-1 d-block">{{ $message }}</div> @enderror
    </div>
    <div class="col-md-3">
        <label class="form-label fw-semibold">Kode Surat <span class="text-danger">*</span></label>
        <input type="text" wire:model="form.code" class="form-control rounded-3" placeholder="Contoh: ACTIVE_STUDENT">
        @error('form.code') <div class="text-danger small mt-1 d-block">{{ $message }}</div> @enderror
    </div>
    <div class="col-md-4">
        <label class="form-label fw-semibold">Mode Pemenuhan (Fulfillment Mode) <span class="text-danger">*</span></label>
        <select wire:model.live="form.fulfillment_mode" class="form-select rounded-3">
            <option value="auto_generate">Auto Generate (Sistem Cetak PDF)</option>
            <option value="manual_upload">Manual Upload (Admin Unggah File)</option>
            <option value="hybrid">Hybrid (Pilihan Fleksibel)</option>
        </select>
        @error('form.fulfillment_mode') <div class="text-danger small mt-1 d-block">{{ $message }}</div> @enderror
    </div>

    <div class="col-md-4">
        <label class="form-label fw-semibold">Key Template PDF</label>
        <input type="text" wire:model="form.template_key" class="form-control rounded-3" placeholder="Contoh: active_student">
        <small class="text-muted">ID template blade untuk cetak otomatis.</small>
        @error('form.template_key') <div class="text-danger small mt-1 d-block">{{ $message }}</div> @enderror
    </div>
    <div class="col-md-4">
        <label class="form-label fw-semibold">Field Tambahan Wajib</label>
        <input type="text" wire:model="form.required_fields" class="form-control rounded-3" placeholder="Contoh: recipient, purpose, company_name">
        <small class="text-muted">Pisahkan dengan koma.</small>
        @error('form.required_fields') <div class="text-danger small mt-1 d-block">{{ $message }}</div> @enderror
    </div>
    <div class="col-md-4">
        <label class="form-label fw-semibold">Status Layanan <span class="text-danger">*</span></label>
        <select wire:model="form.is_active" class="form-select rounded-3">
            <option value="1">Aktif & Tersedia</option>
            <option value="0">Nonaktif</option>
        </select>
        @error('form.is_active') <div class="text-danger small mt-1 d-block">{{ $message }}</div> @enderror
    </div>

    <div class="col-md-6">
        <label class="form-label fw-semibold">Pejabat Penanda Tangan</label>
        <input type="text" wire:model="form.signer_name" class="form-control rounded-3" placeholder="Contoh: Prof. Dr. Budi Santoso, M.Kom.">
        @error('form.signer_name') <div class="text-danger small mt-1 d-block">{{ $message }}</div> @enderror
    </div>
    <div class="col-md-6">
        <label class="form-label fw-semibold">Jabatan Penanda Tangan</label>
        <input type="text" wire:model="form.signer_position" class="form-control rounded-3" placeholder="Contoh: Wakil Rektor Bidang Akademik">
        @error('form.signer_position') <div class="text-danger small mt-1 d-block">{{ $message }}</div> @enderror
    </div>

    <div class="col-12">
        <div class="border rounded-4 p-3 bg-light bg-opacity-50">
            <div class="fw-semibold mb-3 text-dark"><i class="fa fa-sliders me-2 text-primary"></i>Pengaturan Persyaratan & Lampiran</div>
            <div class="row g-3">
                <div class="col-md-6">
                    <div class="form-check form-switch">
                        <input class="form-check-input" type="checkbox" id="requires_financial_clearance" wire:model.live="form.requires_financial_clearance">
                        <label class="form-check-label fw-semibold" for="requires_financial_clearance">Wajib Bebas Tunggakan Keuangan</label>
                        <div class="text-muted small">Mahasiswa bertunggakan tidak dapat meminta surat ini.</div>
                    </div>
                </div>
                <div class="col-md-6">
                    <label class="form-label fw-semibold small">Tipe Hold Keuangan</label>
                    <select wire:model="form.clearance_hold_type" class="form-select form-select-sm rounded-3" @disabled(! $form['requires_financial_clearance'])>
                        <option value="">Tanpa Hold Spesifik</option>
                        <option value="registration">Registration Hold</option>
                        <option value="study_plan">Study Plan Hold</option>
                        <option value="exam_card">Exam Card Hold</option>
                        <option value="transcript">Transcript Hold</option>
                        <option value="graduation">Graduation Hold</option>
                    </select>
                    @error('form.clearance_hold_type') <div class="text-danger small mt-1 d-block">{{ $message }}</div> @enderror
                </div>
                <div class="col-md-6 border-top pt-3">
                    <div class="form-check form-switch">
                        <input class="form-check-input" type="checkbox" id="requires_attachment" wire:model="form.requires_attachment">
                        <label class="form-check-label fw-semibold" for="requires_attachment">Wajib Unggah Lampiran Pendukung</label>
                        <div class="text-muted small">Mahasiswa harus melampirkan berkas (misal: surat pengantar).</div>
                    </div>
                </div>
                <div class="col-md-3 border-top pt-3">
                    <label class="form-label fw-semibold small">Ekstensi Diizinkan</label>
                    <input type="text" wire:model="form.allowed_extensions" class="form-control form-control-sm rounded-3" placeholder="pdf,jpg,png">
                    @error('form.allowed_extensions') <div class="text-danger small mt-1 d-block">{{ $message }}</div> @enderror
                </div>
                <div class="col-md-3 border-top pt-3">
                    <label class="form-label fw-semibold small">Maksimal File (KB)</label>
                    <input type="number" min="1" wire:model="form.max_file_size_kb" class="form-control form-control-sm rounded-3" placeholder="2048">
                    @error('form.max_file_size_kb') <div class="text-danger small mt-1 d-block">{{ $message }}</div> @enderror
                </div>
            </div>
        </div>
    </div>

    <div class="col-12">
        <label class="form-label fw-semibold">Deskripsi & Instruksi Layanan</label>
        <textarea wire:model="form.description" rows="3" class="form-control rounded-3" placeholder="Jelaskan kegunaan dan syarat pembuatan surat keterangan ini..."></textarea>
        @error('form.description') <div class="text-danger small mt-1 d-block">{{ $message }}</div> @enderror
    </div>

    <div class="col-12 mt-4 d-flex justify-content-end gap-2">
        <a href="{{ route('admin.student-services.letter-types.index') }}" class="btn btn-outline-secondary rounded-pill px-4">
            <i class="fa fa-times me-2"></i> Batal
        </a>
        <button type="button" wire:click="save" class="btn btn-primary rounded-pill px-4">
            <i class="fa fa-save me-2"></i> Simpan Layanan Surat
        </button>
    </div>
</div>
