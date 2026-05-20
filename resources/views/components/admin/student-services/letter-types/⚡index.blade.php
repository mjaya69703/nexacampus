<?php

use App\Models\StudentService\ServiceLetterType;
use Livewire\Component;

new class extends Component
{
    public function render()
    {
        return $this->view()->layout('layouts.app', [
            'menus' => 'Student Services',
            'pages' => 'Letter Types',
        ]);
    }

    public function stats(): array
    {
        return [
            'total' => ServiceLetterType::count(),
            'active' => ServiceLetterType::where('is_active', true)->count(),
            'auto' => ServiceLetterType::where('fulfillment_mode', 'auto_generate')->count(),
            'clearance' => ServiceLetterType::where('requires_financial_clearance', true)->count(),
        ];
    }
};
?>

<div class="w-full" style="width: 100% !important">
    <x-alert />

    <div class="row g-3 mb-3">
        @foreach ([['label' => 'Letter Types', 'value' => $this->stats()['total']], ['label' => 'Active', 'value' => $this->stats()['active']], ['label' => 'Auto Generate', 'value' => $this->stats()['auto']], ['label' => 'With Clearance', 'value' => $this->stats()['clearance']]] as $card)
            <div class="col-md-3">
                <div class="card">
                    <div class="card-body">
                        <div class="text-muted text-uppercase small">{{ $card['label'] }}</div>
                        <div class="h2 mb-0">{{ $card['value'] }}</div>
                    </div>
                </div>
            </div>
        @endforeach
    </div>

    <div class="card">
        <div class="card-header d-flex justify-content-between align-items-center">
            <div>
                <h3 class="card-title mb-0">Letter Types</h3>
                <small class="text-muted">Template dan aturan layanan surat mahasiswa.</small>
            </div>
            @activecan('service-letter-type.create')
                <a href="{{ route('admin.student-services.letter-types.create') }}" class="btn btn-ghost-primary">
                    <i class="fas fa-plus me-1"></i> Create Type
                </a>
            @endactivecan
        </div>
        <div class="card-body">
            <livewire:student-service.service-letter-type-table />
        </div>
    </div>
</div>
