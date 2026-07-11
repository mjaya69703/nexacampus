<?php

use Livewire\Component;

new class extends Component
{
    public function render()
    {
        return $this->view()->layout('layouts.app', [
            'menus' => 'Alumni',
            'pages' => 'Daftar Tracer Study',
        ]);
    }
};
?>

<div class="w-full" style="width: 100% !important">
    <x-alert />
    <div class="card">
        <div class="card-header d-flex justify-content-between align-items-center">
            <h3 class="card-title">Daftar Tracer Study</h3>
            <div class="card-tools">
                @activecan('tracer-study-campaign.create')
                <a href="{{ route('admin.alumni.tracer-study.create') }}" class="btn btn-ghost-primary">
                    <i class="fa fa-plus me-2"></i> Tambah Kampanye
                </a>
                @endactivecan
            </div>
        </div>
        <div class="card-body">
            <livewire:alumni.tracer-study-campaign-table />
        </div>
    </div>
</div>
