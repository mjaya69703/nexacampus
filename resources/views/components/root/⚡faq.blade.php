<?php

use App\Enums\FaqType;
use App\Models\Publication\Faq;
use Livewire\Component;

new class extends Component
{
    public string $search = '';
    public string $selectedType = 'all';
    public ?string $selectedCategory = null;

    public function filterType(string $type): void
    {
        $this->selectedType = $type;
        $this->selectedCategory = null;
    }

    public function filterCategory(?string $category): void
    {
        $this->selectedCategory = $this->selectedCategory === $category ? null : $category;
    }

    public function render()
    {
        $query = Faq::where('is_active', true);

        if ($this->selectedType !== 'all') {
            $query->where('type', $this->selectedType);
        }

        if ($this->selectedCategory) {
            $query->where('category', $this->selectedCategory);
        }

        if (trim($this->search) !== '') {
            $term = '%' . trim($this->search) . '%';
            $query->where(function ($q) use ($term) {
                $q->where('question', 'like', $term)
                  ->orWhere('answer', 'like', $term)
                  ->orWhere('category', 'like', $term);
            });
        }

        $faqs = $query->orderBy('sort_order')->orderBy('id')->get();

        $categories = Faq::where('is_active', true)
            ->when($this->selectedType !== 'all', fn ($q) => $q->where('type', $this->selectedType))
            ->select('category')
            ->distinct()
            ->pluck('category');

        $typeCounts = [
            'all' => Faq::where('is_active', true)->count(),
        ];
        foreach (FaqType::cases() as $typeCase) {
            $typeCounts[$typeCase->value] = Faq::where('is_active', true)->where('type', $typeCase->value)->count();
        }

        return $this->view([
            'faqs' => $faqs,
            'categories' => $categories,
            'typeCounts' => $typeCounts,
        ])->layout('layouts.home', [
            'menus' => 'Publikasi',
            'pages' => 'Pusat Bantuan & FAQ',
        ]);
    }
};
?>

<div class="admission-public">
    <div class="container-xl py-4 py-lg-5">
        <div class="row justify-content-center">
            <div class="col-12">

                {{-- Hero Banner --}}
                <div class="admission-hero mb-5">
                    <div class="row align-items-center g-4">
                        <div class="col-lg-7">
                            <div class="admission-kicker d-flex align-items-center gap-2 mb-2">
                                <span class="badge-pulse"></span>
                                <span>Pusat Bantuan & Informasi Terpadu</span>
                            </div>
                            <h1 class="admission-title mb-3">Pusat Bantuan &<br><span style="opacity:.8">Pertanyaan Umum (FAQ)</span></h1>
                            <p class="admission-subtitle mb-4">
                                Temukan panduan dan jawaban lengkap seputar PMB, Akademik, Keuangan, dan Layanan Mahasiswa NexaCampus.
                            </p>

                            {{-- Search Input Bar --}}
                            <div class="position-relative" style="max-width: 520px;">
                                <i class="fas fa-search position-absolute top-50 start-0 translate-middle-y ms-3 text-white-50"></i>
                                <input type="text"
                                    class="form-control ps-5 pe-4 rounded-pill border-0 text-white placeholder-white-50"
                                    style="background: rgba(255, 255, 255, 0.15); backdrop-filter: blur(8px); font-size: .95rem;"
                                    placeholder="Ketik kata kunci pertanyaan (misal: pendaftaran, UKT, KRS)..."
                                    wire:model.live.debounce.300ms="search">
                                @if($search)
                                    <button class="btn btn-sm btn-link text-white-50 position-absolute top-50 end-0 translate-middle-y me-2" wire:click="$set('search', '')">
                                        <i class="fas fa-times-circle"></i>
                                    </button>
                                @endif
                            </div>
                        </div>

                        <div class="col-lg-5">
                            <div class="admission-hero-panel shadow">
                                <div class="d-flex justify-content-between align-items-start mb-3 pb-2 border-bottom border-light border-opacity-10">
                                    <div>
                                        <div class="text-white-50 small fw-bold text-uppercase">Total FAQ Publik</div>
                                        <div class="h3 text-white mb-0 fw-bolder">{{ $typeCounts['all'] ?? 0 }} Pertanyaan</div>
                                    </div>
                                    <span class="badge bg-white text-primary px-3 py-2 rounded-pill fw-bold">Bantuan 24/7</span>
                                </div>
                                <div class="row g-3">
                                    <div class="col-6">
                                        <div class="hero-mini-stat text-center">
                                            <span class="text-white">{{ $typeCounts['admission'] ?? 0 }}</span>
                                            <small class="text-white-50">FAQ PMB</small>
                                        </div>
                                    </div>
                                    <div class="col-6">
                                        <div class="hero-mini-stat text-center">
                                            <span class="text-white">{{ $typeCounts['academic'] ?? 0 }}</span>
                                            <small class="text-white-50">FAQ Akademik</small>
                                        </div>
                                    </div>
                                </div>
                                <div class="mt-3 pt-2 border-top border-light border-opacity-10">
                                    <div class="d-flex align-items-center gap-2">
                                        <i class="fas fa-circle-question text-white-50"></i>
                                        <small class="text-white-50">Jawaban resmi dari civitas akademika NexaCampus</small>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                {{-- Type Filter Navigation --}}
                <div class="d-flex flex-wrap gap-2 mb-4">
                    <button type="button"
                        class="d-flex align-items-center gap-2 px-3 py-2 rounded-pill text-decoration-none fw-semibold border-0"
                        style="font-size: .85rem; transition: all .2s ease; {{ $selectedType === 'all' ? 'background: #3b82f6; color: #fff;' : 'background: rgba(59, 130, 246, .1); color: #3b82f6;' }}"
                        wire:click="filterType('all')">
                        <i class="fas fa-border-all"></i>
                        <span>Semua Modul</span>
                        <span class="badge rounded-pill ms-1" style="{{ $selectedType === 'all' ? 'background: rgba(255,255,255,.3); color: #fff;' : 'background: #3b82f6; color: #fff;' }}">{{ $typeCounts['all'] ?? 0 }}</span>
                    </button>

                    @foreach(FaqType::cases() as $typeCase)
                        @php
                            $isActiveType = $selectedType === $typeCase->value;
                        @endphp
                        <button type="button"
                            class="d-flex align-items-center gap-2 px-3 py-2 rounded-pill text-decoration-none fw-semibold border-0"
                            style="font-size: .85rem; transition: all .2s ease; {{ $isActiveType ? 'background: #3b82f6; color: #fff;' : 'background: rgba(59, 130, 246, .1); color: #3b82f6;' }}"
                            wire:click="filterType('{{ $typeCase->value }}')">
                            <i class="{{ $typeCase->icon() }}"></i>
                            <span>{{ $typeCase->label() }}</span>
                            <span class="badge rounded-pill ms-1" style="{{ $isActiveType ? 'background: rgba(255,255,255,.3); color: #fff;' : 'background: #3b82f6; color: #fff;' }}">{{ $typeCounts[$typeCase->value] ?? 0 }}</span>
                        </button>
                    @endforeach
                </div>

                {{-- Category Sub-Filter --}}
                @if($categories->count() > 0)
                    <div class="d-flex flex-wrap align-items-center gap-2 mb-4">
                        <small class="fw-bold text-muted me-2"><i class="fas fa-filter me-1"></i>Filter Kategori:</small>
                        @foreach($categories as $cat)
                            <button type="button"
                                class="btn btn-sm rounded-pill px-3 fw-semibold {{ $selectedCategory === $cat ? 'btn-primary' : 'btn-outline-secondary' }}"
                                style="font-size: .8rem;"
                                wire:click="filterCategory('{{ $cat }}')">
                                {{ $cat }}
                            </button>
                        @endforeach
                        @if($selectedCategory || $search)
                            <button type="button" class="btn btn-sm btn-link text-danger text-decoration-none" style="font-size: .8rem;" wire:click="$set('selectedCategory', null); $set('search', '');">
                                <i class="fas fa-times me-1"></i>Reset Filter
                            </button>
                        @endif
                    </div>
                @endif

                {{-- FAQ Content List --}}
                @if($faqs->isEmpty())
                    <div class="admission-card border-0 rounded-4 shadow-sm p-5 text-center my-4">
                        <div style="width:72px;height:72px;border-radius:20px;background:linear-gradient(135deg,#3b82f6,#1d4ed8);margin:0 auto 1.5rem;display:flex;align-items:center;justify-content:center;font-size:1.8rem;color:#fff;">
                            <i class="fas fa-circle-question"></i>
                        </div>
                        <h4 class="fw-bolder text-body mb-2">FAQ Tidak Ditemukan</h4>
                        <p class="text-muted mb-4" style="font-size: .9rem;">Tidak ada pertanyaan yang sesuai dengan kriteria pencarian atau filter yang Anda pilih.</p>
                        <button class="btn btn-primary rounded-pill px-4 fw-bold" wire:click="$set('search', ''); $set('selectedType', 'all'); $set('selectedCategory', null);">
                            Reset Semua Filter
                        </button>
                    </div>
                @else
                    @php
                        $groupedFaqs = $faqs->groupBy('category');
                    @endphp

                    <div class="row g-4 mb-5">
                        @foreach($groupedFaqs as $catName => $items)
                            <div class="col-12" id="faq-cat-{{ Str::slug($catName) }}">
                                <div class="d-flex align-items-center gap-3 mb-3">
                                    <div style="width:38px;height:38px;border-radius:12px;background:#3b82f6;display:flex;align-items:center;justify-content:center;color:#fff;font-size:.95rem;flex-shrink:0;">
                                        <i class="fas fa-folder-open"></i>
                                    </div>
                                    <h3 class="fw-bolder text-body mb-0" style="font-size:1.05rem;">{{ $catName }}</h3>
                                    <div class="flex-fill border-bottom border-2 ms-1 opacity-25"></div>
                                    <span class="badge bg-secondary-lt text-secondary rounded-pill fw-semibold" style="font-size: .75rem;">{{ $items->count() }} Pertanyaan</span>
                                </div>

                                <div class="accordion" id="faqGroup{{ Str::studly($catName) }}">
                                    @foreach($items as $idx => $faq)
                                        <div class="admission-card border-0 rounded-3 shadow-sm mb-2 overflow-hidden">
                                            <div class="accordion-item bg-transparent border-0">
                                                <h4 class="accordion-header m-0">
                                                    <button class="accordion-button bg-transparent fw-semibold text-body {{ $idx !== 0 ? 'collapsed' : '' }} shadow-none py-3 px-4"
                                                        type="button"
                                                        data-bs-toggle="collapse"
                                                        data-bs-target="#faqItemRoot{{ $faq->id }}"
                                                        aria-expanded="{{ $idx === 0 ? 'true' : 'false' }}"
                                                        aria-controls="faqItemRoot{{ $faq->id }}"
                                                        style="font-size: .92rem;">
                                                        <span class="d-flex align-items-center gap-2">
                                                            @if($faq->type instanceof FaqType)
                                                                <span class="badge {{ $faq->type->badgeClass() }}" style="font-size: 0.68rem;">
                                                                    {{ $faq->type->label() }}
                                                                </span>
                                                            @endif
                                                            <i class="fas fa-circle-question me-1 text-primary" style="font-size: .85rem;"></i>
                                                            {{ $faq->question }}
                                                        </span>
                                                    </button>
                                                </h4>
                                                <div id="faqItemRoot{{ $faq->id }}" class="accordion-collapse collapse {{ $idx === 0 ? 'show' : '' }}" data-bs-parent="#faqGroup{{ Str::studly($catName) }}">
                                                    <div class="accordion-body pt-0 pb-4 px-4 text-muted" style="font-size: .88rem; line-height: 1.7; border-top: 1px solid var(--tblr-border-color);">
                                                        <div class="pt-3">{!! $faq->answer !!}</div>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    @endforeach
                                </div>
                            </div>
                        @endforeach
                    </div>
                @endif

                {{-- Contact CTA --}}
                <div class="admission-card rounded-4 p-5 text-center mt-5" style="background: linear-gradient(135deg, #1e293b, #0f172a);">
                    <div style="width:60px;height:60px;border-radius:20px;background:linear-gradient(135deg,#3b82f6,#1d4ed8);margin:0 auto 1.25rem;display:flex;align-items:center;justify-content:center;font-size:1.5rem;color:#fff;box-shadow:0 8px 24px rgba(59,130,246,.4);">
                        <i class="fas fa-headset"></i>
                    </div>
                    <h3 class="fw-bolder text-white mb-2">Masih Memiliki Pertanyaan Lain?</h3>
                    <p class="text-white-50 mb-4" style="max-width: 600px; margin: 0 auto; font-size: .9rem;">
                        Jika Anda memerlukan bantuan tambahan, tim staf dan akademisi NexaCampus siap membantu Anda.
                    </p>
                    <div class="d-flex justify-content-center gap-3 flex-wrap">
                        <a href="{{ route('root.kontak') }}" class="btn btn-primary btn-lg px-5 fw-bold shadow">
                            <i class="fas fa-envelope me-2"></i>Hubungi Kami
                        </a>
                        <a href="{{ route('root.admission.apply') }}" class="btn btn-outline-light btn-lg px-4 fw-bold">
                            <i class="fas fa-paper-plane me-2"></i>Daftar PMB
                        </a>
                    </div>
                </div>

            </div>
        </div>
    </div>
</div>
