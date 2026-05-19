<div class="row">
    <div class="col-md-6 mb-3">
        <label class="form-label">Academic Year <span class="text-danger">*</span></label>
        <select class="form-control" wire:model="form.academic_year_id">
            <option value="">Select Academic Year</option>
            @foreach ($academicYears as $academicYear)
                <option value="{{ $academicYear['id'] }}">{{ $academicYear['label'] }}</option>
            @endforeach
        </select>
        @error('form.academic_year_id') <span class="text-danger">{{ $message }}</span> @enderror
    </div>

    <div class="col-md-6 mb-3">
        <label class="form-label">Study Program <span class="text-danger">*</span></label>
        <select class="form-control" wire:model="form.study_program_id">
            <option value="">Select Study Program</option>
            @foreach ($studyPrograms as $studyProgram)
                <option value="{{ $studyProgram['id'] }}">{{ $studyProgram['label'] }}</option>
            @endforeach
        </select>
        @error('form.study_program_id') <span class="text-danger">{{ $message }}</span> @enderror
    </div>

    <div class="col-md-4 mb-3">
        <label class="form-label">Semester <span class="text-danger">*</span></label>
        <input type="number" min="1" max="14" class="form-control" wire:model="form.semester">
        @error('form.semester') <span class="text-danger">{{ $message }}</span> @enderror
    </div>

    <div class="col-md-4 mb-3">
        <label class="form-label">Payment Deadline <span class="text-danger">*</span></label>
        <input type="date" class="form-control" wire:model="form.payment_deadline">
        @error('form.payment_deadline') <span class="text-danger">{{ $message }}</span> @enderror
    </div>

    <div class="col-md-4 mb-3">
        <label class="form-label">Status</label>
        <select class="form-control" wire:model="form.is_active">
            <option value="1">Active</option>
            <option value="0">Inactive</option>
        </select>
        @error('form.is_active') <span class="text-danger">{{ $message }}</span> @enderror
    </div>

    <div class="col-md-3 mb-3">
        <label class="form-label">Base Fee <span class="text-danger">*</span></label>
        <input type="number" min="0" step="1000" class="form-control" wire:model="form.base_fee">
        @error('form.base_fee') <span class="text-danger">{{ $message }}</span> @enderror
    </div>

    <div class="col-md-3 mb-3">
        <label class="form-label">Lab Fee</label>
        <input type="number" min="0" step="1000" class="form-control" wire:model="form.lab_fee">
        @error('form.lab_fee') <span class="text-danger">{{ $message }}</span> @enderror
    </div>

    <div class="col-md-3 mb-3">
        <label class="form-label">Library Fee</label>
        <input type="number" min="0" step="1000" class="form-control" wire:model="form.library_fee">
        @error('form.library_fee') <span class="text-danger">{{ $message }}</span> @enderror
    </div>

    <div class="col-md-3 mb-3">
        <label class="form-label">Activity Fee</label>
        <input type="number" min="0" step="1000" class="form-control" wire:model="form.activity_fee">
        @error('form.activity_fee') <span class="text-danger">{{ $message }}</span> @enderror
    </div>

    <div class="col-md-4 mb-3">
        <label class="form-label">Late Penalty / Day</label>
        <input type="number" min="0" step="1000" class="form-control" wire:model="form.late_penalty_per_day">
        @error('form.late_penalty_per_day') <span class="text-danger">{{ $message }}</span> @enderror
    </div>

    <div class="col-md-8 mb-3">
        <label class="form-label">Notes</label>
        <input type="text" class="form-control" wire:model="form.notes" placeholder="Optional notes">
        @error('form.notes') <span class="text-danger">{{ $message }}</span> @enderror
    </div>
</div>

<div class="form-footer">
    <button type="submit" class="btn btn-primary">
        <i class="fas fa-save me-1"></i> Save
    </button>
    <a href="{{ route('admin.financial.tuition-fees.index') }}" class="btn btn-secondary">
        Cancel
    </a>
</div>
