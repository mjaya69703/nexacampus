<?php

use Livewire\Component;
use App\Models\Settings\Campus;

new class extends Component
{
    public ?Campus $campus = null;

    public function mount(): void
    {
        $this->campus = Campus::first();
    }

    public function render()
    {
        return $this->view()->layout('layouts.home', [
            'menus' => 'Kontak',
            'pages' => 'Hubungi Kami',
        ]);
    }
};
?>

<div class="admission-public">
    <div class="container-xl py-4 py-lg-5">
        <div class="row justify-content-center">
            <div class="col-12">

                {{-- Hero --}}
                <div class="admission-hero mb-5">
                    <div class="row align-items-center g-4">
                        <div class="col-lg-7">
                            <div class="admission-kicker d-flex align-items-center gap-2 mb-2">
                                <span class="badge-pulse"></span>
                                <span>Layanan Informasi Kampus</span>
                            </div>
                            <h1 class="admission-title mb-3">Hubungi<br><span style="opacity:.8">NexaCampus</span></h1>
                            <p class="admission-subtitle mb-0">
                                Kami siap membantu Anda. Jangan ragu untuk menghubungi layanan informasi kami terkait akademik, pendaftaran, kerja sama, maupun layanan kampus lainnya.
                            </p>
                        </div>
                        <div class="col-lg-5">
                            <div class="admission-hero-panel shadow">
                                <div class="mb-3 pb-2 border-bottom border-light border-opacity-10">
                                    <div class="text-white-50 small fw-bold text-uppercase">Jam Operasional Layanan</div>
                                    <div class="h3 text-white mb-0 fw-bolder">Senin - Jumat</div>
                                    <div class="text-white fw-semibold mt-1">08:00 - 16:00 WIB</div>
                                </div>
                                <div class="d-flex align-items-center gap-2 mt-2">
                                    <i class="fas fa-clock text-white-50"></i>
                                    <small class="text-white-50">Kecuali hari libur nasional</small>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="row g-4">
                    <div class="col-lg-5">
                        <div class="d-flex flex-column gap-4 h-100">
                            {{-- Info Box 1 --}}
                            <div class="admission-card border-0 rounded-4 shadow-sm p-4 d-flex align-items-center gap-4" style="transition:transform .2s ease;" onmouseover="this.style.transform='translateY(-3px)'" onmouseout="this.style.transform='none'">
                                <div style="width:64px;height:64px;border-radius:18px;background:linear-gradient(135deg,#3b82f6,#1d4ed8);color:#fff;display:flex;align-items:center;justify-content:center;font-size:1.6rem;flex-shrink:0;box-shadow:0 8px 24px rgba(59,130,246,.3);">
                                    <i class="fas fa-headset"></i>
                                </div>
                                <div>
                                    <div class="fw-bolder text-body mb-1" style="font-size:1.1rem;">Layanan Telepon</div>
                                    <div class="text-muted mb-2" style="font-size:.85rem;">Panggilan resmi pada jam kerja</div>
                                    @if($campus?->phone)<div class="fw-bold text-primary" style="font-size:1.05rem;">{{ $campus->phone }}</div>@else<div class="text-muted fst-italic">-</div>@endif
                                    @if($campus?->faximile)<div class="text-muted mt-1" style="font-size:.8rem;">Fax: {{ $campus->faximile }}</div>@endif
                                </div>
                            </div>
                            
                            {{-- Info Box 2 --}}
                            <div class="admission-card border-0 rounded-4 shadow-sm p-4 d-flex align-items-center gap-4" style="transition:transform .2s ease;" onmouseover="this.style.transform='translateY(-3px)'" onmouseout="this.style.transform='none'">
                                <div style="width:64px;height:64px;border-radius:18px;background:linear-gradient(135deg,#10b981,#059669);color:#fff;display:flex;align-items:center;justify-content:center;font-size:1.8rem;flex-shrink:0;box-shadow:0 8px 24px rgba(16,185,129,.3);">
                                    <i class="fab fa-whatsapp"></i>
                                </div>
                                <div>
                                    <div class="fw-bolder text-body mb-1" style="font-size:1.1rem;">WhatsApp Center</div>
                                    <div class="text-muted mb-2" style="font-size:.85rem;">Pesan cepat khusus info PMB/Mahasiswa</div>
                                    @if($campus?->whatsapp)<a href="https://wa.me/{{ preg_replace('/[^0-9]/', '', $campus->whatsapp) }}" target="_blank" class="fw-bold text-success text-decoration-none" style="font-size:1.05rem;">{{ $campus->whatsapp }} <i class="fas fa-external-link-alt ms-1" style="font-size:.75rem;"></i></a>@else<div class="text-muted fst-italic">-</div>@endif
                                </div>
                            </div>

                            {{-- Info Box 3 --}}
                            <div class="admission-card border-0 rounded-4 shadow-sm p-4 d-flex align-items-center gap-4" style="transition:transform .2s ease;" onmouseover="this.style.transform='translateY(-3px)'" onmouseout="this.style.transform='none'">
                                <div style="width:64px;height:64px;border-radius:18px;background:linear-gradient(135deg,#8b5cf6,#7c3aed);color:#fff;display:flex;align-items:center;justify-content:center;font-size:1.6rem;flex-shrink:0;box-shadow:0 8px 24px rgba(139,92,246,.3);">
                                    <i class="fas fa-envelope-open-text"></i>
                                </div>
                                <div class="flex-fill">
                                    <div class="fw-bolder text-body mb-1" style="font-size:1.1rem;">Email Resmi</div>
                                    <div class="text-muted mb-2" style="font-size:.85rem;">Pertanyaan umum & persuratan</div>
                                    @if($campus?->email_info)<a href="mailto:{{ $campus->email_info }}" class="fw-bold text-decoration-none d-block mb-1" style="color:#8b5cf6;font-size:1rem;">{{ $campus->email_info }}</a>@else<div class="text-muted fst-italic mb-1">-</div>@endif
                                    @if($campus?->email_humas)<a href="mailto:{{ $campus->email_humas }}" class="text-muted text-decoration-none d-block" style="font-size:.85rem;">Humas: {{ $campus->email_humas }}</a>@endif
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="col-lg-7">
                        <div class="admission-card border-0 rounded-4 shadow-sm p-0 h-100 overflow-hidden d-flex flex-column">
                            <div class="p-4 border-bottom">
                                <h3 class="fw-bolder text-body mb-2" style="font-size:1.2rem;"><i class="fas fa-location-dot text-primary me-2"></i>Kunjungi Kampus Kami</h3>
                                <p class="text-muted mb-0" style="font-size:.9rem;">
                                    {{ $campus?->address ?? 'Jl. Pendidikan No. 1' }}, {{ $campus?->city ?? 'Kota Akademik' }}<br>
                                    {{ $campus?->province ?? 'Provinsi' }} {{ $campus?->postal_code ?? '' }}
                                </p>
                            </div>
                            <div class="flex-fill position-relative bg-light" style="min-height:300px;">
                                {{-- Fake map placeholder if no coordinates or map key. Normally this would be a real map/iframe --}}
                                <div class="position-absolute top-0 start-0 w-100 h-100 d-flex flex-column align-items-center justify-content-center text-muted">
                                    <i class="fas fa-map-location-dot mb-3 text-secondary" style="font-size:4rem;opacity:.5;"></i>
                                    <div class="fw-semibold">Google Maps Area</div>
                                    <small class="text-muted">Interactive map will load here</small>
                                </div>
                            </div>
                            <div class="p-4 bg-light border-top">
                                <div class="d-flex align-items-center justify-content-between flex-wrap gap-3">
                                    <div class="fw-bold text-body" style="font-size:.9rem;">Media Sosial Resmi:</div>
                                    <div class="d-flex gap-2">
                                        @if($campus?->instagram)
                                        <a href="{{ $campus->instagram }}" target="_blank" class="btn btn-icon btn-outline-secondary rounded-circle" style="color:#e1306c;border-color:rgba(225,48,108,.2);"><i class="fab fa-instagram"></i></a>
                                        @endif
                                        @if($campus?->facebook)
                                        <a href="{{ $campus->facebook }}" target="_blank" class="btn btn-icon btn-outline-secondary rounded-circle" style="color:#1877f2;border-color:rgba(24,119,242,.2);"><i class="fab fa-facebook-f"></i></a>
                                        @endif
                                        @if($campus?->xtwitter)
                                        <a href="{{ $campus->xtwitter }}" target="_blank" class="btn btn-icon btn-outline-secondary rounded-circle text-body border-opacity-10"><i class="fa-brands fa-x-twitter"></i></a>
                                        @endif
                                        @if($campus?->linkedin)
                                        <a href="{{ $campus->linkedin }}" target="_blank" class="btn btn-icon btn-outline-secondary rounded-circle" style="color:#0a66c2;border-color:rgba(10,102,194,.2);"><i class="fab fa-linkedin-in"></i></a>
                                        @endif
                                        @if($campus?->tiktok)
                                        <a href="{{ $campus->tiktok }}" target="_blank" class="btn btn-icon btn-outline-secondary rounded-circle text-body border-opacity-10"><i class="fab fa-tiktok"></i></a>
                                        @endif
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

            </div>
        </div>
    </div>
</div>
