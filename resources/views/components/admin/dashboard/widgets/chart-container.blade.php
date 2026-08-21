@props([
    'id' => 'chart',
    'title' => 'Chart',
    'subtitle' => '',
    'type' => 'bar',
    'height' => 240,
    'badge' => '',
    'badgeColor' => 'primary',
    'series' => [],
    'categories' => [],
    'labels' => [],
])

@php
    // Chart dirender secara declarative oleh engine global (resources/js/app.js)
    // yang memindai [data-app-chart]. Pendekatan ini kebal terhadap lazy-loading
    // dan dedupe script Livewire (@script hanya jalan sekali per komponen).
    $isCircular = in_array($type, ['donut', 'pie']);

    $chartConfig = ['elId' => $id, 'type' => $type, 'height' => (int) $height];

    if ($isCircular) {
        $chartConfig['labels'] = array_values($labels);
        $chartConfig['series'] = array_values(array_map(fn ($v) => (float) $v, $series));
    } else {
        $chartConfig['categories'] = array_values($categories);
        $chartConfig['series'] = ! empty($series[0]['name'])
            ? array_values($series)
            : [['name' => $title, 'data' => array_map(fn ($v) => (float) $v, $series)]];
    }

    $badgeClasses = match($badgeColor) {
        'primary' => 'bg-primary-lt text-primary',
        'success' => 'bg-success-lt text-success',
        'warning' => 'bg-warning-lt text-warning',
        'danger' => 'bg-danger-lt text-danger',
        'info' => 'bg-info-lt text-info',
        'purple' => 'bg-purple-lt text-purple',
        'teal' => 'bg-teal-lt text-teal',
        default => 'bg-secondary-lt text-secondary',
    };
@endphp

<div class="card border rounded-4 h-100">
    <div class="card-body p-3 p-md-4">
        <div class="d-flex justify-content-between align-items-center mb-3">
            <div>
                <h5 class="fw-bold mb-0">{{ $title }}</h5>
                @if($subtitle)
                    <span class="text-muted small">{{ $subtitle }}</span>
                @endif
            </div>
            @if($badge)
                <span class="badge {{ $badgeClasses }} fw-semibold px-3 py-1.5 rounded-pill">{{ $badge }}</span>
            @endif
        </div>

        <div
            id="{{ $id }}"
            data-app-chart="{{ json_encode($chartConfig) }}"
            style="min-height: {{ $height }}px;"
        ></div>
    </div>
</div>
