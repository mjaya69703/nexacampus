<?php

use Livewire\Component;

new class extends Component
{
    public function render()
    {
        return $this->view()->layout('layouts.app', [
            'menus' => 'Alumni',
            'pages' => 'Daftar Lowongan Kerja',
        ]);
    }
};
?>

<div class="w-full" style="width: 100% !important">
    <x-alert />
    <div class="card">
        <div class="card-header d-flex justify-content-between align-items-center">
            <h3 class="card-title">Daftar Lowongan Kerja</h3>
            <div class="card-tools">
                @activecan('job-posting.create')
                <a href="{{ route('admin.alumni.job-postings.create') }}" class="btn btn-ghost-primary">
                    <i class="fa fa-plus me-2"></i> Tambah Lowongan
                </a>
                @endactivecan
            </div>
        </div>
        <div class="card-body">
            <livewire:alumni.job-posting-table />
        </div>
    </div>
</div>
