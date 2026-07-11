<?php

use Livewire\Component;

new class extends Component
{
    public function render()
    {
        $data = [
            'menus' => 'Alumni',
            'pages' => 'Daftar Mitra Perusahaan',
        ];

        return $this->view()->layout('layouts.app', $data);
    }
};
?>

<div class="w-full" style="width: 100% !important">

    <x-alert />
    <div class="card">
        <div class="card-header d-flex justify-content-between align-items-center">
            <h3 class="card-title">Daftar Mitra Perusahaan</h3>
            <div class="card-tools">
                @activecan('employer-partner.create')
                <a href="{{ route('admin.alumni.employer-partners.create') }}" class="btn btn-ghost-primary">
                    <i class="fa fa-plus me-2"></i> Tambah Mitra
                </a>
                @endactivecan
            </div>
        </div>
        <div class="card-body">
            <livewire:alumni.employer-partner-table />
        </div>
    </div>

</div>
