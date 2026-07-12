@props([
    'label',
    'value',
    'icon' => 'chart-simple',
    'tone' => 'primary',
])

@php
    $tones = [
        'primary' => ['bg' => '#eef2ff', 'color' => '#312e81'],
        'success' => ['bg' => '#eafaf2', 'color' => '#166534'],
        'warning' => ['bg' => '#fff7e6', 'color' => '#92400e'],
        'danger' => ['bg' => '#fff1f2', 'color' => '#be123c'],
        'info' => ['bg' => '#e8f7ff', 'color' => '#0f766e'],
    ];

    $tone = $tones[$tone] ?? $tones['primary'];
@endphp

<div class="card border-0 shadow-sm h-100 rounded-4">
    <div class="card-body p-3">
        <div class="d-flex align-items-center gap-3">
            <div class="d-flex align-items-center justify-content-center rounded-3 flex-shrink-0" style="width: 46px; height: 46px; background: {{ $tone['bg'] }}; color: {{ $tone['color'] }};">
                <i class="fas fa-{{ $icon }}"></i>
            </div>
            <div class="min-w-0">
                <div class="text-secondary small fw-semibold">{{ $label }}</div>
                <div class="fs-3 fw-bold lh-1 mt-1">{{ $value }}</div>
            </div>
        </div>
    </div>
</div>