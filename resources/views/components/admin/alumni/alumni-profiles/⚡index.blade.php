<?php

use Livewire\Component;

new class extends Component
{
    public function render()
    {
        $data = [
            'menus' => 'Alumni',
            'pages' => 'Daftar Data Alumni',
        ];

        return $this->view()->layout('layouts.app', $data);
    }
};
?>

<div class="w-full" style="width: 100% !important">

    <x-alert />
    <div class="card">
        <div class="card-header d-flex justify-content-between align-items-center">
            <h3 class="card-title">Daftar Data Alumni</h3>
            <div class="card-tools">
                @activecan('alumni-profile.create')
                <a href="{{ route('admin.alumni.profiles.create') }}" class="btn btn-ghost-primary">
                    <i class="fa fa-plus me-2"></i> Tambah Alumni
                </a>
                @endactivecan
            </div>
        </div>
        <div class="card-body">
            <livewire:alumni.alumni-profile-table />
        </div>
    </div>

</div>
