<?php

use App\Models\Alumni\TracerStudyCampaign;
use Livewire\Component;

new class extends Component
{
    public ?int $campaignId = null;
    public string $campaignTitle = '';

    public function mount($id): void
    {
        $campaign = TracerStudyCampaign::findOrFail($id);
        $this->campaignId = (int) $id;
        $this->campaignTitle = $campaign->title;
    }

    public function goBack(): void
    {
        $this->redirectRoute('admin.alumni.tracer-study.show', ['id' => $this->campaignId]);
    }

    public function render()
    {
        return $this->view()->layout('layouts.app', [
            'menus' => 'Alumni',
            'pages' => 'Respon Tracer Study: ' . $this->campaignTitle,
        ]);
    }
};
?>

<div class="w-full" style="width: 100% !important">
    <x-alert />
    <div class="card">
        <div class="card-header d-flex justify-content-between align-items-center">
            <h3 class="card-title">Respon Tracer: {{ $campaignTitle }}</h3>
            <div class="card-tools">
                <button class="btn btn-secondary" wire:click="goBack">
                    <i class="fas fa-arrow-left me-1"></i> Kembali
                </button>
            </div>
        </div>
        <div class="card-body">
            <livewire:alumni.tracer-study-response-table :campaign-id="$campaignId" />
        </div>
    </div>
</div>
