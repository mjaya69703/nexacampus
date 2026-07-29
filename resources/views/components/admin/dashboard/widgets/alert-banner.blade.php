@props([
    'type' => 'info',
    'title' => '',
    'message' => '',
    'icon' => '',
    'items' => [],
])

@php
    $config = match($type) {
        'success' => [
            'bg' => 'bg-success bg-opacity-10',
            'border' => 'border-success border-opacity-20',
            'text' => 'text-success',
            'icon' => $icon ?: 'fas fa-check-circle',
        ],
        'warning' => [
            'bg' => 'bg-warning bg-opacity-10',
            'border' => 'border-warning border-opacity-20',
            'text' => 'text-warning',
            'icon' => $icon ?: 'fas fa-triangle-exclamation',
        ],
        'danger' => [
            'bg' => 'bg-danger bg-opacity-10',
            'border' => 'border-danger border-opacity-20',
            'text' => 'text-danger',
            'icon' => $icon ?: 'fas fa-exclamation-triangle',
        ],
        default => [
            'bg' => 'bg-info bg-opacity-10',
            'border' => 'border-info border-opacity-20',
            'text' => 'text-info',
            'icon' => $icon ?: 'fas fa-info-circle',
        ],
    };
@endphp

<div class="alert {{ $config['bg'] }} {{ $config['border'] }} border rounded-4 p-3">
    @if($title)
        <div class="d-flex align-items-center gap-2 mb-2 fw-bold {{ $config['text'] }}">
            <i class="{{ $config['icon'] }} fs-5"></i>
            <span>{{ $title }}</span>
        </div>
    @elseif($message)
        <div class="d-flex align-items-center gap-2 {{ $config['text'] }}">
            <i class="{{ $config['icon'] }} fs-5"></i>
            <span class="fw-bold small">{{ $message }}</span>
        </div>
    @endif

    @if(count($items) > 0)
        <ul class="mb-0 small {{ $config['text'] }} ps-3">
            @foreach($items as $item)
                <li>{{ is_array($item) ? ($item['description'] ?? $item['name'] ?? json_encode($item)) : $item }}</li>
            @endforeach
        </ul>
    @endif
</div>
