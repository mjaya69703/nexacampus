<div class="row">
    <div class="col-md-6 mb-3">
        <label class="form-label">Name <span class="text-danger">*</span></label>
        <input type="text" class="form-control" wire:model="form.name" placeholder="Beasiswa Prestasi">
        @error('form.name') <span class="text-danger">{{ $message }}</span> @enderror
    </div>

    <div class="col-md-3 mb-3">
        <label class="form-label">Type <span class="text-danger">*</span></label>
        <select class="form-control" wire:model="form.type">
            <option value="full">Full</option>
            <option value="partial">Partial</option>
            <option value="merit">Merit</option>
            <option value="need_based">Need Based</option>
        </select>
        @error('form.type') <span class="text-danger">{{ $message }}</span> @enderror
    </div>

    <div class="col-md-3 mb-3">
        <label class="form-label">Status</label>
        <select class="form-control" wire:model="form.is_active">
            <option value="1">Active</option>
            <option value="0">Inactive</option>
        </select>
        @error('form.is_active') <span class="text-danger">{{ $message }}</span> @enderror
    </div>

    <div class="col-md-4 mb-3">
        <label class="form-label">Discount Type <span class="text-danger">*</span></label>
        <select class="form-control" wire:model.live="form.discount_type">
            <option value="percentage">Percentage</option>
            <option value="fixed">Fixed Amount</option>
        </select>
        @error('form.discount_type') <span class="text-danger">{{ $message }}</span> @enderror
    </div>

    @if(($form['discount_type'] ?? 'percentage') === 'fixed')
        <div class="col-md-4 mb-3">
            <label class="form-label">Fixed Amount <span class="text-danger">*</span></label>
            <input type="number" min="0" step="1000" class="form-control" wire:model="form.fixed_amount">
            @error('form.fixed_amount') <span class="text-danger">{{ $message }}</span> @enderror
        </div>
    @else
        <div class="col-md-4 mb-3">
            <label class="form-label">Discount Percentage <span class="text-danger">*</span></label>
            <input type="number" min="0" max="100" step="0.01" class="form-control" wire:model="form.discount_percentage">
            @error('form.discount_percentage') <span class="text-danger">{{ $message }}</span> @enderror
        </div>
    @endif

    <div class="col-md-4 mb-3">
        <label class="form-label">Duration Semesters</label>
        <input type="number" min="1" max="20" class="form-control" wire:model="form.duration_semesters">
        @error('form.duration_semesters') <span class="text-danger">{{ $message }}</span> @enderror
    </div>

    <div class="col-12 mb-3">
        <label class="form-label">Description</label>
        <textarea class="form-control" rows="3" wire:model="form.description"></textarea>
        @error('form.description') <span class="text-danger">{{ $message }}</span> @enderror
    </div>

    <div class="col-12 mb-3">
        <label class="form-label">Requirements</label>
        <textarea class="form-control" rows="3" wire:model="form.requirements"></textarea>
        @error('form.requirements') <span class="text-danger">{{ $message }}</span> @enderror
    </div>
</div>

<div class="alert alert-info">
    <i class="fas fa-info-circle me-1"></i>
    Scholarship assignment yang aktif akan otomatis dipakai saat invoice diterbitkan, atau bisa diterapkan manual sebagai adjustment pada invoice lama.
</div>

<div class="form-footer">
    <button type="submit" class="btn btn-primary">
        <i class="fas fa-save me-1"></i> Save Scholarship
    </button>
    <a href="{{ route('admin.financial.scholarships.index') }}" class="btn btn-secondary">Cancel</a>
</div>
