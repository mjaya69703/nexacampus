<?php

use App\Support\ActivePermission;
use App\Support\Dashboard\DashboardConfig;
use Livewire\Component;

new class extends Component
{
    public array $widgetConfig = [];
    public bool $hasChanges = false;

    public function mount(): void
    {
        $this->widgetConfig = DashboardConfig::forUser(auth()->user());
    }

    protected function canManage(): bool
    {
        return session('active_role') === 'superuser' || ActivePermission::check('dashboard.manage');
    }

    public function toggleSubWidget(string $module, string $subWidget): void
    {
        if (! $this->canManage()) {
            session()->flash('error', 'Anda tidak memiliki akses untuk mengubah konfigurasi widget.');

            return;
        }

        $current = (bool) ($this->widgetConfig[$module][$subWidget] ?? true);
        $this->widgetConfig[$module][$subWidget] = ! $current;

        // Simpan persisten sekarang, TAPI jangan morph dashboard selagi offcanvas terbuka.
        // (morph saat panel terbuka merusak state Bootstrap -> scroll-lock nyangkut)
        auth()->user()?->forceFill(['dashboard_widget_config' => $this->widgetConfig])->save();
        $this->hasChanges = true;
    }

    public function resetToDefault(): void
    {
        if (! $this->canManage()) {
            return;
        }

        auth()->user()?->forceFill(['dashboard_widget_config' => null])->save();
        $this->widgetConfig = DashboardConfig::defaults();
        $this->hasChanges = true;
    }

    /**
     * Dipanggil dari JS saat offcanvas selesai ditutup:
     * satu kali refresh dashboard untuk SEMUA perubahan yang menumpuk.
     */
    public function applyChanges(): void
    {
        if ($this->hasChanges) {
            $this->hasChanges = false;
            $this->dispatch('dashboard-widgets-updated');
        }
    }

    public function render()
    {
        return $this->view();
    }
};
?>

@php
    $moduleLabels = \App\Support\Dashboard\DashboardConfig::labels();
    $subLabels = \App\Support\Dashboard\DashboardConfig::subWidgetLabels();
    $moduleColors = [
        'admission' => ['primary', 'fa-user-plus'],
        'financial' => ['success', 'fa-wallet'],
        'academic' => ['info', 'fa-graduation-cap'],
        'student_services' => ['warning', 'fa-hands-helping'],
        'organization' => ['purple', 'fa-sitemap'],
        'publication_alumni' => ['teal', 'fa-bullhorn'],
        'system_health' => ['secondary', 'fa-shield-halved'],
    ];
@endphp

<div>
    @if(session('active_role') === 'superuser' || \App\Support\ActivePermission::check('dashboard.manage'))
        <button type="button" class="btn btn-light rounded-pill fw-semibold d-inline-flex align-items-center gap-2 px-3 py-2" data-bs-toggle="offcanvas" data-bs-target="#offcanvasWidgetConfig">
            <i class="fas fa-sliders"></i>
            <span>Konfigurasi Widget</span>
        </button>

        <div class="offcanvas offcanvas-end rounded-start-4" tabindex="-1" id="offcanvasWidgetConfig" aria-labelledby="offcanvasWidgetConfigLabel">
            <div class="offcanvas-header border-bottom p-3 p-md-4">
                <div class="d-flex align-items-center gap-3">
                    <span class="app-module-icon bg-primary-lt text-primary"><i class="fas fa-sliders"></i></span>
                    <div>
                        <h5 class="offcanvas-title fw-bold mb-0" id="offcanvasWidgetConfigLabel">Pengaturan Widget Dashboard</h5>
                        <small class="text-muted">Perubahan otomatis tersimpan &amp; diterapkan saat panel ditutup.</small>
                    </div>
                </div>
                <button type="button" class="btn-close" data-bs-dismiss="offcanvas" aria-label="Close"></button>
            </div>

            <div class="offcanvas-body p-3 p-md-4">
                @foreach($moduleLabels as $module => $moduleLabel)
                    @php
                        [$color, $icon] = $moduleColors[$module] ?? ['secondary', 'fa-th-large'];
                    @endphp
                    <div class="card border rounded-4 mb-3 overflow-hidden">
                        <div class="card-header bg-{{ $color }} bg-opacity-10 p-3 fw-bold text-{{ $color }} d-flex align-items-center justify-content-between">
                            <span><i class="fas {{ $icon }} me-2"></i>{{ $moduleLabel }}</span>
                        </div>
                        <div class="card-body p-3">
                            @foreach($subLabels[$module] ?? [] as $subKey => $subLabel)
                                <div class="form-check form-switch {{ $loop->last ? 'mb-0' : 'mb-2' }}">
                                    <input class="form-check-input" type="checkbox"
                                        id="cfg_{{ $module }}_{{ $subKey }}"
                                        @checked($widgetConfig[$module][$subKey] ?? true)
                                        wire:click="toggleSubWidget('{{ $module }}', '{{ $subKey }}')">
                                    <label class="form-check-label" for="cfg_{{ $module }}_{{ $subKey }}">{{ $subLabel }}</label>
                                </div>
                            @endforeach
                        </div>
                    </div>
                @endforeach

                <div class="mt-4 pt-3 border-top d-flex justify-content-between">
                    <button type="button" class="btn btn-ghost-danger px-3 py-1.5" wire:click="resetToDefault">
                        <i class="fas fa-rotate-left me-1"></i> Reset Default
                    </button>
                    <button type="button" class="btn btn-primary px-4 py-1.5" data-bs-dismiss="offcanvas">
                        Selesai
                    </button>
                </div>
            </div>
        </div>
    @endif
</div>

@script
<script>
    var canvasEl = document.getElementById('offcanvasWidgetConfig');

    if (canvasEl) {
        canvasEl.addEventListener('hidden.bs.offcanvas', function () {
            // Defensif: bersihkan sisa backdrop & body lock agar scroll tidak pernah nyangkut.
            document.querySelectorAll('.offcanvas-backdrop').forEach(function (el) { el.remove(); });
            document.body.classList.remove('modal-open');
            document.body.style.overflow = '';
            document.body.style.paddingRight = '';

            // Satu refresh dashboard untuk semua perubahan yang menumpuk.
            $wire.applyChanges();
        });
    }
</script>
@endscript
