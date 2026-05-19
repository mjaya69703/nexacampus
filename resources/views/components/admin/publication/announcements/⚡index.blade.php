<?php

use Livewire\Component;

new class extends Component
{
    public function render()
    {
        return $this->view()->layout('layouts.app', [
            'menus' => 'Publication',
            'pages' => 'Daftar Pengumuman',
        ]);
    }
};
?>

<div class="w-full" style="width: 100% !important">
    <x-alert />
    <div class="card">
        <div class="card-header d-flex justify-content-between align-items-center">
            <h3 class="card-title">Daftar Pengumuman</h3>
            <div class="card-tools">
                @activecan('announcement.create')
                    <a href="{{ route('admin.publication.announcements.create') }}" class="btn btn-ghost-primary">
                        <i class="fa fa-plus me-2"></i> Buat Pengumuman
                    </a>
                @endactivecan
            </div>
        </div>
        <div class="card-body">
            <livewire:publication.announcement-table />
        </div>
    </div>
</div>
