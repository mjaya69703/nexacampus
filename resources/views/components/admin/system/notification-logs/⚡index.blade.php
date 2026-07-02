<?php

use Livewire\Component;

new class extends Component {
    public function render()
    {
        $data = [
            'menus' => 'System Management',
            'pages' => 'Log Notifikasi',
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
                <h3 class="card-title mb-0">Log Notifikasi</h3>
                <div class="text-secondary small mt-1">
                    Pantau pengiriman WhatsApp dan kanal notifikasi lain dari event akademik, keuangan, penerimaan, dan layanan mahasiswa.
                </div>
            </div>
        </div>

        <div class="card-body">
            <livewire:system.notification-log-table />
        </div>
    </div>
</div>
