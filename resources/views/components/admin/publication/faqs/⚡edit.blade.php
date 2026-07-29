<?php

use App\Enums\FaqType;
use App\Models\Publication\Faq;
use Livewire\Component;

new class extends Component
{
    public Faq $faq;

    public string $type = 'general';
    public string $category = 'Umum';
    public string $question = '';
    public string $answer = '';
    public int $sortOrder = 0;
    public bool $isActive = true;

    public function mount(int $id): void
    {
        $this->faq = Faq::findOrFail($id);
        $this->type = $this->faq->type instanceof FaqType ? $this->faq->type->value : $this->faq->type;
        $this->category = $this->faq->category;
        $this->question = $this->faq->question;
        $this->answer = $this->faq->answer;
        $this->sortOrder = $this->faq->sort_order;
        $this->isActive = (bool) $this->faq->is_active;
    }

    public function save(): void
    {
        $this->validate([
            'type' => 'required|string|in:' . implode(',', array_column(FaqType::cases(), 'value')),
            'category' => 'required|string|max:100',
            'question' => 'required|string|max:1000',
            'answer' => 'required|string',
            'sortOrder' => 'required|integer|min:0',
            'isActive' => 'boolean',
        ], [], [
            'type' => 'Tipe FAQ',
            'category' => 'Kategori',
            'question' => 'Pertanyaan',
            'answer' => 'Jawaban',
            'sortOrder' => 'Urutan',
            'isActive' => 'Status Aktif',
        ]);

        $this->faq->update([
            'type' => $this->type,
            'category' => $this->category,
            'question' => $this->question,
            'answer' => $this->answer,
            'sort_order' => $this->sortOrder,
            'is_active' => $this->isActive,
            'updated_by' => auth()->id(),
        ]);

        session()->flash('success', 'FAQ berhasil diperbarui!');
        $this->redirectRoute('admin.publication.faqs.index');
    }

    public function render()
    {
        return $this->view()->layout('layouts.app', [
            'menus' => 'Publication',
            'pages' => 'Edit FAQ',
        ]);
    }
};
?>

<div class="w-full" style="width: 100% !important">
    <x-alert />

    <x-admin.publication.header
        title="Edit FAQ #{{ $faq->id }}"
        description="Perbarui pertanyaan, jawaban, tipe modul, kategori, urutan penayangan, dan status aktif FAQ."
        icon="circle-question"
    >
        <a href="{{ route('admin.publication.faqs.index') }}" class="btn btn-sm btn-light text-dark fw-semibold d-inline-flex align-items-center gap-2 shadow-sm rounded-pill px-3 py-2">
            <i class="fa fa-arrow-left"></i>
            <span>Kembali ke daftar</span>
        </a>
    </x-admin.publication.header>

    <div class="row g-4 align-items-start">
        <div class="col-lg-8">
            <div class="card border-0 shadow-sm rounded-4 overflow-hidden mb-4">
                <div class="card-header bg-white border-bottom p-4">
                    <div class="d-flex align-items-center gap-3">
                        <div class="bg-warning bg-opacity-10 text-warning rounded-3 d-flex align-items-center justify-content-center" style="width: 42px; height: 42px;">
                            <i class="fa fa-pencil fs-5"></i>
                        </div>
                        <div>
                            <h4 class="card-title fw-bold mb-1 text-dark">Form Edit FAQ</h4>
                            <div class="text-muted small">Perbarui data pertanyaan dan jawaban FAQ.</div>
                        </div>
                    </div>
                </div>
                <div class="card-body p-4">
                    <div class="mb-4">
                        <label class="form-label fw-semibold">Pertanyaan <span class="text-danger">*</span></label>
                        <textarea class="form-control rounded-3" rows="2" placeholder="Tuliskan pertanyaan singkat dan jelas..." wire:model.defer="question"></textarea>
                        @error('question')
                            <div class="text-danger small mt-1">{{ $message }}</div>
                        @enderror
                    </div>

                    <div class="mb-0">
                        <label class="form-label fw-semibold">Jawaban Lengkap <span class="text-danger">*</span></label>
                        <livewire:jodit-text-editor wire:model.live="answer" identifier="faq-edit-answer" :height="350" />
                        @error('answer')
                            <div class="text-danger small mt-1">{{ $message }}</div>
                        @enderror
                    </div>
                </div>
            </div>
        </div>

        <div class="col-lg-4">
            <div class="card border-0 shadow-sm rounded-4 overflow-hidden mb-4">
                <div class="card-header bg-white border-bottom p-4">
                    <div class="d-flex align-items-center gap-3">
                        <div class="bg-primary bg-opacity-10 text-primary rounded-3 d-flex align-items-center justify-content-center" style="width: 42px; height: 42px;">
                            <i class="fa fa-layer-group fs-5"></i>
                        </div>
                        <div>
                            <h4 class="card-title fw-bold mb-1 text-dark">Tipe & Kategori</h4>
                            <div class="text-muted small">Tentukan asal modul dan pengelompokan FAQ.</div>
                        </div>
                    </div>
                </div>
                <div class="card-body p-4">
                    <div class="mb-3">
                        <label class="form-label fw-semibold">Tipe Modul / FAQ <span class="text-danger">*</span></label>
                        <select class="form-select rounded-3" wire:model="type">
                            @foreach(FaqType::cases() as $typeCase)
                                <option value="{{ $typeCase->value }}">{{ $typeCase->label() }}</option>
                            @endforeach
                        </select>
                        @error('type')
                            <div class="text-danger small mt-1">{{ $message }}</div>
                        @enderror
                    </div>

                    <div class="mb-0">
                        <label class="form-label fw-semibold">Kategori <span class="text-danger">*</span></label>
                        <input type="text" class="form-control rounded-3" placeholder="Contoh: Pendaftaran, Biaya, Dokumen" wire:model.defer="category">
                        @error('category')
                            <div class="text-danger small mt-1">{{ $message }}</div>
                        @enderror
                    </div>
                </div>
            </div>

            <div class="card border-0 shadow-sm rounded-4 overflow-hidden mb-4">
                <div class="card-header bg-white border-bottom p-4">
                    <div class="d-flex align-items-center gap-3">
                        <div class="bg-info bg-opacity-10 text-info rounded-3 d-flex align-items-center justify-content-center" style="width: 42px; height: 42px;">
                            <i class="fa fa-sliders fs-5"></i>
                        </div>
                        <div>
                            <h4 class="card-title fw-bold mb-1 text-dark">Pengaturan Penayangan</h4>
                            <div class="text-muted small">Atur urutan prioritas dan status aktif.</div>
                        </div>
                    </div>
                </div>
                <div class="card-body p-4">
                    <div class="mb-3">
                        <label class="form-label fw-semibold">Urutan Tampil (Sort Order)</label>
                        <input type="number" class="form-control rounded-3" wire:model.defer="sortOrder" min="0">
                        <div class="text-muted small mt-1">Urutan terkecil (0, 1, 2) tampil lebih atas.</div>
                        @error('sortOrder')
                            <div class="text-danger small mt-1">{{ $message }}</div>
                        @enderror
                    </div>

                    <div class="border rounded-4 p-3 bg-light bg-opacity-50">
                        <div class="form-check form-switch mb-0">
                            <input class="form-check-input" type="checkbox" id="isActive" wire:model="isActive">
                            <label class="form-check-label fw-semibold" for="isActive">
                                <i class="fa fa-check-circle me-1 text-success"></i>Aktifkan FAQ (Publik)
                            </label>
                        </div>
                    </div>
                </div>
            </div>

            <div class="d-flex flex-column gap-2">
                <button type="button" class="btn btn-primary rounded-pill px-4 w-100 d-inline-flex align-items-center justify-content-center gap-2" wire:click="save">
                    <i class="fa fa-save"></i>
                    <span>Perbarui FAQ</span>
                </button>
                <a href="{{ route('admin.publication.faqs.index') }}" class="btn btn-outline-secondary rounded-pill px-4 w-100 d-inline-flex align-items-center justify-content-center gap-2">
                    <i class="fa fa-times"></i>
                    <span>Batal</span>
                </a>
            </div>
        </div>
    </div>
</div>
