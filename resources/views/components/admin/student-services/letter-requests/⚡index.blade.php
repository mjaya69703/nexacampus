<?php

use App\Models\StudentService\ServiceLetterRequest;
use Livewire\Component;

new class extends Component
{
    public function render()
    {
        return $this->view()->layout('layouts.app', [
            'menus' => 'Student Services',
            'pages' => 'Letter Requests',
        ]);
    }

    public function stats(): array
    {
        return [
            'total' => ServiceLetterRequest::count(),
            'pending' => ServiceLetterRequest::whereIn('status', ['submitted', 'under_review'])->count(),
            'approved' => ServiceLetterRequest::where('status', 'approved')->count(),
            'issued' => ServiceLetterRequest::where('status', 'issued')->count(),
        ];
    }
};
?>

<div class="w-full" style="width: 100% !important">
    <x-alert />

    <div class="row g-3 mb-3">
        @foreach ([['label' => 'Requests', 'value' => $this->stats()['total']], ['label' => 'Need Review', 'value' => $this->stats()['pending']], ['label' => 'Approved', 'value' => $this->stats()['approved']], ['label' => 'Issued', 'value' => $this->stats()['issued']]] as $card)
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
        <div class="card-header">
            <div>
                <h3 class="card-title mb-0">Letter Requests</h3>
                <small class="text-muted">Review, approve, dan terbitkan surat mahasiswa.</small>
            </div>
        </div>
        <div class="card-body">
            <livewire:student-service.service-letter-request-table />
        </div>
    </div>
</div>
