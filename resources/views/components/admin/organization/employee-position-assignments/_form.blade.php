<div class="row g-4">
    <div class="col-12">
        <label class="form-label fw-bold text-dark required">Pilih Pegawai</label>
        @if ($this->selectedEmployee)
            <div class="border border-primary rounded-4 p-3 bg-primary bg-opacity-10 d-flex justify-content-between align-items-center">
                <div class="d-flex align-items-center gap-3">
                    <div class="bg-primary text-white rounded-circle d-flex align-items-center justify-content-center" style="width: 48px; height: 48px;">
                        <i class="fas fa-user-check fs-5"></i>
                    </div>
                    <div>
                        <h6 class="fw-bold text-dark mb-0 fs-6">{{ $this->selectedEmployee->user?->name ?? '-' }}</h6>
                        <span class="small text-muted">{{ $this->selectedEmployee->employee_number ?: 'Nomor pegawai belum diisi' }} &bull; {{ $this->selectedEmployee->user?->email }}</span>
                    </div>
                </div>
                <button type="button" class="btn btn-sm btn-outline-danger rounded-pill px-3 fw-medium" wire:click="clearEmployee">
                    <i class="fas fa-sync-alt me-1"></i> Ganti Pegawai
                </button>
            </div>
        @else
            <div class="input-group mb-2">
                <span class="input-group-text bg-light text-secondary rounded-start-3"><i class="fas fa-search"></i></span>
                <input type="search" class="form-control rounded-end-3" wire:model.live.debounce.350ms="employeeSearch" placeholder="Cari nama, email, username, kode, NIK, atau nomor pegawai...">
            </div>
            <div class="list-group list-group-flush border rounded-3 overflow-hidden shadow-sm">
                @forelse ($this->searchableEmployees as $candidate)
                    <button type="button" class="list-group-item list-group-item-action py-3 px-4 d-flex justify-content-between align-items-center" wire:click="selectEmployee({{ $candidate->id }})">
                        <div>
                            <span class="fw-bold text-dark d-block">{{ $candidate->user?->name ?? '-' }}</span>
                            <span class="small text-muted">{{ $candidate->employee_number ?: 'No. Pegawai belum diisi' }}{{ $candidate->user?->email ? ' - '.$candidate->user?->email : '' }}</span>
                        </div>
                        <span class="badge bg-light text-primary rounded-pill px-3 py-1">Pilih <i class="fas fa-chevron-right ms-1"></i></span>
                    </button>
                @empty
                    <div class="list-group-item text-muted text-center py-4 small">
                        Tidak ada pegawai aktif yang cocok dengan pencarian di atas.
                    </div>
                @endforelse
            </div>
        @endif
        @error('selectedEmployeeProfileId') <div class="text-danger small mt-1">{{ $message }}</div> @enderror
    </div>

    <div class="col-lg-6">
        <label class="form-label fw-bold text-dark required">Jabatan Struktural / Fungsional</label>
        <select class="form-select rounded-3 @error('form.organizational_position_id') is-invalid @enderror" wire:model.live="form.organizational_position_id">
            <option value="">-- Pilih Jabatan --</option>
            @foreach ($this->positions as $position)
                <option value="{{ $position->id }}">{{ $position->name }} ({{ $position->code }})</option>
            @endforeach
        </select>
        @error('form.organizational_position_id') <div class="invalid-feedback">{{ $message }}</div> @enderror
    </div>

    <div class="col-lg-6">
        <label class="form-label fw-bold text-dark">Lingkup Cakupan (Scope Jabatan)</label>
        <div class="form-control rounded-3 bg-light text-dark fw-medium border">
            <i class="fas fa-layer-group text-primary me-2"></i>{{ $this->selectedPosition ? str($this->selectedPosition->scope_type)->replace('_', ' ')->title() : 'Pilih jabatan terlebih dahulu untuk melihat scope' }}
        </div>
    </div>

    @if ($this->selectedPosition?->scope_type === 'faculty')
        <div class="col-12">
            <label class="form-label fw-bold text-dark required">Pilih Fakultas Scope</label>
            <select class="form-select rounded-3 @error('form.faculty_id') is-invalid @enderror" wire:model.defer="form.faculty_id">
                <option value="">-- Pilih Fakultas yang Dipimpin/Ditangani --</option>
                @foreach ($this->faculties as $faculty)
                    <option value="{{ $faculty->id }}">{{ $faculty->name }}{{ $faculty->code ? ' ('.$faculty->code.')' : '' }}</option>
                @endforeach
            </select>
            @error('form.faculty_id') <div class="invalid-feedback">{{ $message }}</div> @enderror
        </div>
    @endif

    @if ($this->selectedPosition?->scope_type === 'study_program')
        <div class="col-12">
            <label class="form-label fw-bold text-dark required">Pilih Program Studi Scope</label>
            <select class="form-select rounded-3 @error('form.study_program_id') is-invalid @enderror" wire:model.defer="form.study_program_id">
                <option value="">-- Pilih Program Studi yang Dipimpin/Ditangani --</option>
                @foreach ($this->studyPrograms as $program)
                    <option value="{{ $program->id }}">{{ $program->name }}{{ $program->code ? ' ('.$program->code.')' : '' }}</option>
                @endforeach
            </select>
            @error('form.study_program_id') <div class="invalid-feedback">{{ $message }}</div> @enderror
        </div>
    @endif

    @if ($this->selectedPosition?->scope_type === 'work_unit')
        <div class="col-12">
            <label class="form-label fw-bold text-dark required">Pilih Unit Kerja Scope</label>
            <select class="form-select rounded-3 @error('form.work_unit_id') is-invalid @enderror" wire:model.defer="form.work_unit_id">
                <option value="">-- Pilih Unit Kerja yang Dipimpin/Ditangani --</option>
                @foreach ($this->workUnits as $unit)
                    <option value="{{ $unit->id }}">{{ $unit->name }}{{ $unit->code ? ' ('.$unit->code.')' : '' }}</option>
                @endforeach
            </select>
            @error('form.work_unit_id') <div class="invalid-feedback">{{ $message }}</div> @enderror
            <div class="form-text small text-muted"><i class="fas fa-info-circle text-info me-1"></i> Penugasan aktif dengan scope unit kerja akan otomatis mendaftarkan pegawai sebagai anggota di unit kerja tersebut.</div>
        </div>
    @endif

    <div class="col-lg-6">
        <label class="form-label fw-bold text-dark">Tanggal Mulai Berlaku SK / Penugasan</label>
        <input type="date" class="form-control rounded-3 @error('form.starts_at') is-invalid @enderror" wire:model.defer="form.starts_at">
        @error('form.starts_at') <div class="invalid-feedback">{{ $message }}</div> @enderror
    </div>

    <div class="col-lg-6">
        <label class="form-label fw-bold text-dark">Tanggal Selesai Berlaku (Opsional)</label>
        <input type="date" class="form-control rounded-3 @error('form.ends_at') is-invalid @enderror" wire:model.defer="form.ends_at">
        @error('form.ends_at') <div class="invalid-feedback">{{ $message }}</div> @enderror
        <div class="form-text small">Kosongkan apabila masa penugasan tidak memiliki batas akhir (seumur hidup/tetap).</div>
    </div>

    <div class="col-12">
        <label class="form-label fw-bold text-dark">Catatan & Nomor SK Penugasan</label>
        <textarea class="form-control rounded-3 @error('form.notes') is-invalid @enderror" rows="3" wire:model.defer="form.notes" placeholder="Tuliskan nomor SK, landasan keputusan, atau keterangan lain..."></textarea>
        @error('form.notes') <div class="invalid-feedback">{{ $message }}</div> @enderror
    </div>

    <div class="col-12">
        <div class="d-flex flex-wrap gap-4 p-3 bg-light rounded-3 border">
            <div class="form-check form-switch mb-0">
                <input class="form-check-input ms-0 me-3" type="checkbox" id="assignment_is_primary" wire:model.defer="form.is_primary" style="cursor: pointer;">
                <label class="form-check-label fw-bold text-dark mb-0" for="assignment_is_primary" style="cursor: pointer;">Jadikan Sebagai Jabatan Utama</label>
            </div>
            <div class="form-check form-switch mb-0">
                <input class="form-check-input ms-0 me-3" type="checkbox" id="assignment_is_active" wire:model.defer="form.is_active" style="cursor: pointer;">
                <label class="form-check-label fw-bold text-dark mb-0" for="assignment_is_active" style="cursor: pointer;">Status Penugasan Aktif</label>
            </div>
        </div>
        @error('form.is_primary') <span class="text-danger small d-block mt-1">{{ $message }}</span> @enderror
        @error('form.is_active') <span class="text-danger small d-block mt-1">{{ $message }}</span> @enderror
    </div>

    <div class="col-12 border-top pt-4 mt-4 d-flex justify-content-end gap-2">
        <button type="button" class="btn btn-light rounded-pill px-4 fw-medium" wire:click="cancel">
            <i class="fas fa-times me-2"></i> Batal
        </button>
        <button type="button" class="btn btn-primary rounded-pill shadow-sm px-4 fw-medium" wire:click="save">
            <i class="fas fa-save me-2"></i> Simpan Penugasan
        </button>
    </div>
</div>
