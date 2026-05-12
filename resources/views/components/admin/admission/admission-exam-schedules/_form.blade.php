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

    <div class="col-md-3 mb-3">
        <label class="form-label">Type <span class="text-danger">*</span></label>
        <select class="form-select" wire:model="form.exam_type">
            <option value="written_test">Written Test</option>
            <option value="interview">Interview</option>
            <option value="practical">Practical</option>
            <option value="portfolio">Portfolio</option>
            <option value="other">Other</option>
        </select>
        @error('form.exam_type') <span class="text-danger">{{ $message }}</span> @enderror
    </div>

    <div class="col-md-3 mb-3">
        <label class="form-label">Quota <span class="text-danger">*</span></label>
        <input type="number" min="0" class="form-control" wire:model="form.quota">
        @error('form.quota') <span class="text-danger">{{ $message }}</span> @enderror
    </div>

    <div class="col-md-12 mb-3">
        <label class="form-label">Title <span class="text-danger">*</span></label>
        <input type="text" class="form-control" wire:model="form.title" placeholder="Interview Session Wave 1">
        @error('form.title') <span class="text-danger">{{ $message }}</span> @enderror
    </div>

    <div class="col-md-3 mb-3">
        <label class="form-label">Date <span class="text-danger">*</span></label>
        <input type="date" class="form-control" wire:model="form.exam_date">
        @error('form.exam_date') <span class="text-danger">{{ $message }}</span> @enderror
    </div>

    <div class="col-md-3 mb-3">
        <label class="form-label">Time <span class="text-danger">*</span></label>
        <input type="time" class="form-control" wire:model="form.exam_time">
        @error('form.exam_time') <span class="text-danger">{{ $message }}</span> @enderror
    </div>

    <div class="col-md-6 mb-3">
        <label class="form-label">Venue</label>
        <input type="text" class="form-control" wire:model="form.venue" placeholder="Auditorium / Online">
        @error('form.venue') <span class="text-danger">{{ $message }}</span> @enderror
    </div>

    <div class="col-md-8 mb-3">
        <label class="form-label">Meeting Link</label>
        <input type="url" class="form-control" wire:model="form.meeting_link" placeholder="https://...">
        @error('form.meeting_link') <span class="text-danger">{{ $message }}</span> @enderror
    </div>

    <div class="col-md-4 mb-3 d-flex align-items-center">
        <label class="form-check mb-0">
            <input class="form-check-input" type="checkbox" wire:model="form.is_active">
            <span class="form-check-label">Active</span>
        </label>
    </div>

    <div class="col-12 mb-3">
        <label class="form-label">Notes</label>
        <textarea rows="3" class="form-control" wire:model="form.notes"></textarea>
        @error('form.notes') <span class="text-danger">{{ $message }}</span> @enderror
    </div>

    <div class="col-12">
        <button type="submit" class="btn btn-primary">
            <i class="fas fa-save me-1"></i> Save
        </button>
        <a href="{{ route('admin.admission.admission-exam-schedules.index') }}" class="btn btn-secondary">
            Cancel
        </a>
    </div>
</div>
