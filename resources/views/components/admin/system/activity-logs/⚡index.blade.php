<?php

use Livewire\Component;

new class extends Component {
    public function render()
    {
        $data = [
            'menus' => 'System Management',
            'pages' => 'Activity Logs',
        ];

        return $this->view()->layout('layouts.app', $data);
    }
};
?>

<div>
    <x-alert />

    <div class="card">
        <div class="card-header d-flex justify-content-between align-items-center">
            <div>
                <h3 class="card-title mb-0">Activity Logs</h3>
                <div class="text-secondary small mt-1">
                    Riwayat aktivitas sistem dan perubahan penting pengguna.
                </div>
            </div>
        </div>

        <div class="card-body">
            <livewire:system.activity-log-table />
        </div>
    </div>
</div>