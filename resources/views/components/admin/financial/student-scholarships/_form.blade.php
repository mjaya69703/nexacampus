<div class="row">
    <div class="col-md-6 mb-3">
        <label class="form-label">Mahasiswa <span class="text-danger">*</span></label>
        <input type="text" class="form-control mb-2" wire:model.live.debounce.300ms="studentSearch" placeholder="Cari NIM, nama, atau email...">
        @if(property_exists($this, 'selectedStudentProfileIds'))
            <div class="border rounded p-2" style="max-height: 240px; overflow-y: auto;">
                @foreach($this->studentOptions() as $student)
                    <label class="form-check mb-2" wire:key="scholarship-student-{{ $student['id'] }}">
                        <input class="form-check-input" type="checkbox" wire:model="selectedStudentProfileIds" value="{{ $student['id'] }}">
                        <span class="form-check-label">{{ $student['label'] }}</span>
                    </label>
                @endforeach
            </div>
            <small class="text-muted">{{ count($selectedStudentProfileIds) }} mahasiswa dipilih. Search hanya menampilkan 25 kandidat teratas.</small>
            @error('selectedStudentProfileIds') <span class="text-danger d-block">{{ $message }}</span> @enderror
            @error('selectedStudentProfileIds.*') <span class="text-danger d-block">{{ $message }}</span> @enderror
        @else
            <select class="form-control" wire:model="form.student_profile_id">
                <option value="">Select Student</option>
                @foreach($this->studentOptions() as $student)
                    <option value="{{ $student['id'] }}">{{ $student['label'] }}</option>
                @endforeach
            </select>
            @error('form.student_profile_id') <span class="text-danger">{{ $message }}</span> @enderror
        @endif
    </div>

    <div class="col-md-6 mb-3">
        <label class="form-label">Scholarship <span class="text-danger">*</span></label>
        <select class="form-control" wire:model="form.scholarship_id">
            <option value="">Select Scholarship</option>
            @foreach($scholarships as $scholarship)
                <option value="{{ $scholarship['id'] }}">{{ $scholarship['label'] }}</option>
            @endforeach
        </select>
        @error('form.scholarship_id') <span class="text-danger">{{ $message }}</span> @enderror
    </div>

    <div class="col-md-4 mb-3">
        <label class="form-label">Academic Year</label>
        <select class="form-control" wire:model="form.academic_year_id">
            <option value="">All Years</option>
            @foreach($academicYears as $year)
                <option value="{{ $year['id'] }}">{{ $year['label'] }}</option>
            @endforeach
        </select>
        @error('form.academic_year_id') <span class="text-danger">{{ $message }}</span> @enderror
    </div>

    <div class="col-md-4 mb-3">
        <label class="form-label">Semester</label>
        <input type="number" min="1" max="14" class="form-control" wire:model="form.semester" placeholder="All semesters">
        @error('form.semester') <span class="text-danger">{{ $message }}</span> @enderror
    </div>

    <div class="col-md-4 mb-3">
        <label class="form-label">Status <span class="text-danger">*</span></label>
        <select class="form-control" wire:model="form.status">
            <option value="active">Active</option>
            <option value="completed">Completed</option>
            <option value="revoked">Revoked</option>
        </select>
        @error('form.status') <span class="text-danger">{{ $message }}</span> @enderror
    </div>

    <div class="col-md-6 mb-3">
        <label class="form-label">Start Date</label>
        <input type="date" class="form-control" wire:model="form.start_date">
        @error('form.start_date') <span class="text-danger">{{ $message }}</span> @enderror
    </div>

    <div class="col-md-6 mb-3">
        <label class="form-label">End Date</label>
        <input type="date" class="form-control" wire:model="form.end_date">
        @error('form.end_date') <span class="text-danger">{{ $message }}</span> @enderror
    </div>

    <div class="col-12 mb-3">
        <label class="form-label">Notes</label>
        <textarea class="form-control" rows="3" wire:model="form.notes"></textarea>
        @error('form.notes') <span class="text-danger">{{ $message }}</span> @enderror
    </div>
</div>

<div class="alert alert-info">
    <i class="fas fa-info-circle me-1"></i>
    Kosongkan academic year/semester jika scholarship berlaku umum. Invoice yang sudah terbit bisa diberi scholarship lewat tombol adjustment di detail invoice.
</div>

<div class="form-footer">
    <button type="submit" class="btn btn-primary">
        <i class="fas fa-save me-1"></i> Save Assignment
    </button>
    <a href="{{ route('admin.financial.student-scholarships.index') }}" class="btn btn-secondary">Cancel</a>
</div>
