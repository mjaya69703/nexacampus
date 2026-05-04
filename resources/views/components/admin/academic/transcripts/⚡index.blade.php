<?php

use Livewire\Component;

new class extends Component {
    public function render()
    {
        $data = [
            'menus' => 'Academic Management',
            'pages' => 'Transcripts',
        ];

        return $this->view()->layout('layouts.app', $data);
    }
};
?>

<div class="w-full" style="width: 100% !important">
    <x-alert />

    <div class="card">
        <div class="card-header d-flex justify-content-between align-items-center">
            <h3 class="card-title">Transcript Summary per Mahasiswa</h3>
        </div>
        <div class="card-body">
            <livewire:academic.transcript-table />
        </div>
    </div>
</div>
