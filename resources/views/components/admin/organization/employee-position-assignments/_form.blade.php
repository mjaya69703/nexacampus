<div class="row">
    <div class="col-12 mt-2">
        <label class="form-label required">Pegawai</label>
        @if ($this->selectedEmployee)
            <div class="border rounded p-3 d-flex justify-content-between align-items-center bg-light">
                <div>
                    <div class="fw-semibold">{{ $this->selectedEmployee->user?->name ?? '-' }}</div>
                    <div class="small text-muted">
                        {{ $this->selectedEmployee->employee_number ?: 'Nomor pegawai belum diisi' }}
                        {{ $this->selectedEmployee->user?->email ? ' - '.$this->selectedEmployee->user?->email : '' }}
                    </div>
                </div>
                <button type="button" class="btn btn-outline-secondary" wire:click="clearEmployee">
                    <i class="fas fa-times me-1"></i> Ganti
                </button>
            </div>
        @else
            <input type="search" class="form-control" wire:model.live.debounce.350ms="employeeSearch" placeholder="Cari nama, email, username, kode, NIK, atau nomor pegawai">
            <div class="border rounded mt-2">
                @forelse ($this->searchableEmployees as $candidate)
                    <button type="button" class="list-group-item list-group-item-action w-100 text-start border-0 border-bottom" wire:click="selectEmployee({{ $candidate->id }})">
                        <span class="fw-semibold d-block">{{ $candidate->user?->name ?? '-' }}</span>
                        <span class="small text-muted">{{ $candidate->employee_number ?: 'Nomor pegawai belum diisi' }}{{ $candidate->user?->email ? ' - '.$candidate->user?->email : '' }}</span>
                    </button>
                @empty
                    <div class="p-3 text-muted">Tidak ada pegawai aktif yang cocok.</div>
                @endforelse
            </div>
        @endif
        @error('selectedEmployeeProfileId') <span class="text-danger">{{ $message }}</span> @enderror
    </div>

    <div class="form-group col-lg-6 mt-3">
        <label class="form-label required">Jabatan</label>
        <select class="form-select" wire:model.live="form.organizational_position_id">
            <option value="">Pilih jabatan</option>
            @foreach ($this->positions as $position)
                <option value="{{ $position->id }}">{{ $position->name }} - {{ $position->code }}</option>
            @endforeach
        </select>
        @error('form.organizational_position_id') <span class="text-danger">{{ $message }}</span> @enderror
    </div>

    <div class="form-group col-lg-6 mt-3">
        <label class="form-label">Scope Jabatan</label>
        <div class="form-control bg-light">
            {{ $this->selectedPosition ? str($this->selectedPosition->scope_type)->replace('_', ' ')->title() : 'Pilih jabatan terlebih dahulu' }}
        </div>
    </div>

    @if ($this->selectedPosition?->scope_type === 'faculty')
        <div class="form-group col-12 mt-3">
            <label class="form-label required">Fakultas</label>
            <select class="form-select" wire:model.defer="form.faculty_id">
                <option value="">Pilih fakultas</option>
                @foreach ($this->faculties as $faculty)
                    <option value="{{ $faculty->id }}">{{ $faculty->name }}{{ $faculty->code ? ' - '.$faculty->code : '' }}</option>
                @endforeach
            </select>
            @error('form.faculty_id') <span class="text-danger">{{ $message }}</span> @enderror
        </div>
    @endif

    @if ($this->selectedPosition?->scope_type === 'study_program')
        <div class="form-group col-12 mt-3">
            <label class="form-label required">Program Studi</label>
            <select class="form-select" wire:model.defer="form.study_program_id">
                <option value="">Pilih program studi</option>
                @foreach ($this->studyPrograms as $program)
                    <option value="{{ $program->id }}">{{ $program->name }}{{ $program->code ? ' - '.$program->code : '' }}</option>
                @endforeach
            </select>
            @error('form.study_program_id') <span class="text-danger">{{ $message }}</span> @enderror
        </div>
    @endif

    @if ($this->selectedPosition?->scope_type === 'work_unit')
        <div class="form-group col-12 mt-3">
            <label class="form-label required">Unit Kerja</label>
            <select class="form-select" wire:model.defer="form.work_unit_id">
                <option value="">Pilih unit kerja</option>
                @foreach ($this->workUnits as $unit)
                    <option value="{{ $unit->id }}">{{ $unit->name }}{{ $unit->code ? ' - '.$unit->code : '' }}</option>
                @endforeach
            </select>
            @error('form.work_unit_id') <span class="text-danger">{{ $message }}</span> @enderror
            <small class="text-muted">Penugasan aktif dengan scope unit kerja akan memastikan pegawai masuk anggota unit kerja.</small>
        </div>
    @endif

    <div class="form-group col-lg-6 mt-3">
        <label class="form-label">Mulai Berlaku</label>
        <input type="date" class="form-control" wire:model.defer="form.starts_at">
        @error('form.starts_at') <span class="text-danger">{{ $message }}</span> @enderror
    </div>

    <div class="form-group col-lg-6 mt-3">
        <label class="form-label">Selesai Berlaku</label>
        <input type="date" class="form-control" wire:model.defer="form.ends_at">
        @error('form.ends_at') <span class="text-danger">{{ $message }}</span> @enderror
    </div>

    <div class="form-group col-12 mt-3">
        <label class="form-label">Catatan</label>
        <textarea class="form-control" rows="3" wire:model.defer="form.notes"></textarea>
        @error('form.notes') <span class="text-danger">{{ $message }}</span> @enderror
    </div>

    <div class="form-group col-lg-6 mt-3">
        <div class="form-check form-switch">
            <input class="form-check-input" type="checkbox" id="assignment_is_primary" wire:model.defer="form.is_primary">
            <label class="form-check-label" for="assignment_is_primary">Jabatan utama</label>
        </div>
        @error('form.is_primary') <span class="text-danger">{{ $message }}</span> @enderror
    </div>

    <div class="form-group col-lg-6 mt-3">
        <div class="form-check form-switch">
            <input class="form-check-input" type="checkbox" id="assignment_is_active" wire:model.defer="form.is_active">
            <label class="form-check-label" for="assignment_is_active">Aktif</label>
        </div>
        @error('form.is_active') <span class="text-danger">{{ $message }}</span> @enderror
    </div>

    <div class="form-group col-12 mt-4 d-flex align-items-center justify-content-end gap-2">
        <button type="button" class="btn btn-primary" wire:click="save">
            <i class="fas fa-save me-2"></i> Simpan
        </button>
        <button type="button" class="btn btn-secondary" wire:click="cancel">
            <i class="fas fa-times me-2"></i> Batal
        </button>
    </div>
</div>
