<div class="row">
    <div class="col-md-6 mb-3">
        <label class="form-label">Admission Period <span class="text-danger">*</span></label>
        <select class="form-select" wire:model="form.admission_period_id">
            <option value="">Choose Period</option>
            @foreach($periods as $period)
                <option value="{{ $period['id'] }}">{{ $period['label'] }}</option>
            @endforeach
        </select>
        @error('form.admission_period_id') <span class="text-danger">{{ $message }}</span> @enderror
    </div>

    <div class="col-md-6 mb-3">
        <label class="form-label">Faculty</label>
        <select class="form-select" wire:model.live="form.faculty_id">
            <option value="">All Faculties</option>
            @foreach($faculties as $faculty)
                <option value="{{ $faculty['id'] }}">{{ $faculty['label'] }}</option>
            @endforeach
        </select>
        @error('form.faculty_id') <span class="text-danger">{{ $message }}</span> @enderror
    </div>

    <div class="col-md-6 mb-3">
        <label class="form-label">Study Program</label>
        <select class="form-select" wire:model="form.study_program_id">
            <option value="">All Programs</option>
            @foreach($studyPrograms as $program)
                <option value="{{ $program['id'] }}">{{ $program['label'] }}</option>
            @endforeach
        </select>
        @error('form.study_program_id') <span class="text-danger">{{ $message }}</span> @enderror
    </div>

    <div class="col-md-3 mb-3">
        <label class="form-label">Class Type</label>
        <select class="form-select" wire:model="form.class_type">
            <option value="">All Classes</option>
            <option value="regular">Regular</option>
            <option value="evening">Evening</option>
            <option value="weekend">Weekend</option>
        </select>
        @error('form.class_type') <span class="text-danger">{{ $message }}</span> @enderror
    </div>

    <div class="col-md-3 mb-3">
        <label class="form-label">Quota <span class="text-danger">*</span></label>
        <input type="number" min="1" class="form-control" wire:model="form.quota">
        @error('form.quota') <span class="text-danger">{{ $message }}</span> @enderror
    </div>

    <div class="col-12">
        <button type="submit" class="btn btn-primary">
            <i class="fas fa-save me-1"></i> Save
        </button>
        <a href="{{ route('admin.admission.admission-quotas.index') }}" class="btn btn-secondary">Cancel</a>
    </div>
</div>
