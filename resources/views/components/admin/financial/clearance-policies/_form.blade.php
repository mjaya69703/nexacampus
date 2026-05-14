<div class="row">
    <div class="col-md-6 mb-3">
        <label class="form-label">Invoice Type <span class="text-danger">*</span></label>
        <select class="form-control" wire:model="form.invoice_type">
            @foreach($invoiceTypes as $type)
                <option value="{{ $type['id'] }}">{{ $type['label'] }}</option>
            @endforeach
        </select>
        @error('form.invoice_type') <span class="text-danger">{{ $message }}</span> @enderror
    </div>

    <div class="col-md-6 mb-3">
        <label class="form-label">Hold Target <span class="text-danger">*</span></label>
        <select class="form-control" wire:model="form.hold_type">
            @foreach($holdTypes as $type)
                <option value="{{ $type['id'] }}">{{ $type['label'] }}</option>
            @endforeach
        </select>
        @error('form.hold_type') <span class="text-danger">{{ $message }}</span> @enderror
    </div>

    <div class="col-md-4 mb-3">
        <label class="form-label">Mode <span class="text-danger">*</span></label>
        <select class="form-control" wire:model="form.mode">
            <option value="warning">Warning Only</option>
            <option value="blocking">Blocking</option>
        </select>
        @error('form.mode') <span class="text-danger">{{ $message }}</span> @enderror
    </div>

    <div class="col-md-4 mb-3">
        <label class="form-label">Grace Days <span class="text-danger">*</span></label>
        <input type="number" min="0" class="form-control" wire:model="form.grace_days">
        @error('form.grace_days') <span class="text-danger">{{ $message }}</span> @enderror
    </div>

    <div class="col-md-4 mb-3">
        <label class="form-label">Status <span class="text-danger">*</span></label>
        <select class="form-control" wire:model="form.is_active">
            <option value="1">Active</option>
            <option value="0">Inactive</option>
        </select>
        @error('form.is_active') <span class="text-danger">{{ $message }}</span> @enderror
    </div>

    <div class="col-12 mb-3">
        <label class="form-label">Description</label>
        <textarea class="form-control" rows="3" wire:model="form.description" placeholder="Policy notes for finance/admin"></textarea>
        @error('form.description') <span class="text-danger">{{ $message }}</span> @enderror
    </div>
</div>

<div class="alert alert-info">
    <i class="fas fa-info-circle me-1"></i>
    Policy changes are applied on the next financial clearance evaluation. Existing unresolved holds can be auto-released when they no longer match an active overdue policy.
</div>

<div class="form-footer">
    <button type="submit" class="btn btn-primary">
        <i class="fas fa-save me-1"></i> Save Policy
    </button>
    <a href="{{ route('admin.financial.clearance-policies.index') }}" class="btn btn-secondary">Cancel</a>
</div>
