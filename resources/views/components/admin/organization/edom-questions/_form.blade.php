<form wire:submit.prevent="save" class="row g-3">
    <div class="col-md-4"><label class="form-label">Kategori</label><input type="text" class="form-control" wire:model.defer="form.category">@error('form.category') <span class="text-danger">{{ $message }}</span> @enderror</div>
    <div class="col-md-4"><label class="form-label">Tipe Jawaban</label><select class="form-select" wire:model.defer="form.answer_type"><option value="scale">Skala 1-5</option><option value="text">Komentar</option></select></div>
    <div class="col-md-4"><label class="form-label">Urutan</label><input type="number" class="form-control" wire:model.defer="form.sort_order"></div>
    <div class="col-12"><label class="form-label">Pertanyaan</label><textarea rows="3" class="form-control" wire:model.defer="form.question_text"></textarea>@error('form.question_text') <span class="text-danger">{{ $message }}</span> @enderror</div>
    <div class="col-md-3"><label class="form-check"><input class="form-check-input" type="checkbox" wire:model.defer="form.is_required"><span class="form-check-label">Wajib diisi</span></label></div>
    <div class="col-md-3"><label class="form-check"><input class="form-check-input" type="checkbox" wire:model.defer="form.is_active"><span class="form-check-label">Aktif</span></label></div>
    <div class="col-12 d-flex justify-content-end gap-2"><a href="{{ route('admin.organization.edom-questions.index') }}" class="btn btn-secondary"><i class="fas fa-arrow-left me-2"></i>Kembali</a><button type="submit" class="btn btn-primary"><i class="fas fa-save me-2"></i>Simpan</button></div>
</form>
