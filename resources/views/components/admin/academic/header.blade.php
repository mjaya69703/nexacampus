@props([
    'title' => 'Modul Manajemen Akademik',
    'description' => 'Kelola tahun akademik, kurikulum, mata kuliah, jadwal perkuliahan, registrasi mahasiswa, hingga penilaian dan transkrip nilai.',
    'icon' => 'graduation-cap',
    'stats' => null,
])

<div class="card border-0 shadow-sm rounded-4 mb-4 overflow-hidden" style="background: linear-gradient(135deg, #0284c7 0%, #2563eb 55%, #1e3a8a 100%); position: relative;">
    <div style="position: absolute; top: -48px; right: -48px; width: 220px; height: 220px; background: rgba(255, 255, 255, 0.08); border-radius: 50%; pointer-events: none;"></div>
    <div style="position: absolute; bottom: -60px; right: 120px; width: 160px; height: 160px; background: rgba(255, 255, 255, 0.05); border-radius: 50%; pointer-events: none;"></div>

    <div class="card-body p-4 p-md-4 text-white" style="position: relative; z-index: 2;">
        <div class="d-flex flex-column flex-lg-row align-items-start align-items-lg-center justify-content-between gap-3">
            <div class="d-flex align-items-center gap-3">
                <div class="d-flex align-items-center justify-content-center rounded-3 flex-shrink-0 shadow-sm" style="width: 56px; height: 56px; background: rgba(255, 255, 255, 0.18); backdrop-filter: blur(8px); border: 1px solid rgba(255, 255, 255, 0.25);">
                    <i class="fa fa-{{ $icon }} fs-2 text-white"></i>
                </div>
                <div>
                    <h2 class="card-title text-white fw-bold fs-3 mb-1 tracking-tight">{{ $title }}</h2>
                    <p class="text-white text-opacity-85 mb-0 fs-6" style="max-width: 700px; line-height: 1.45;">{{ $description }}</p>
                </div>
            </div>

            @if(isset($slot) && $slot->isNotEmpty())
                <div class="d-flex flex-wrap align-items-center gap-2 mt-2 mt-lg-0 w-100 w-lg-auto justify-content-start justify-content-lg-end">
                    {{ $slot }}
                </div>
            @endif
        </div>

        @if(isset($stats) && $stats->isNotEmpty())
            <div class="border-top border-white border-opacity-15 mt-4 pt-3 d-flex flex-wrap gap-3 align-items-center">
                {{ $stats }}
            </div>
        @endif
    </div>
</div>
