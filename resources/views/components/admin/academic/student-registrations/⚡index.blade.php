<?php

use Livewire\Component;

new class extends Component {
    public function render()
    {
        $data = [
            'menus' => 'Academic Management',
            'pages' => 'Student Registrations',
        ];

        return $this->view()->layout('layouts.app', $data);
    }
};
?>

<div class="w-full" style="width: 100% !important">

    <x-alert />
    <div class="card">
        <div class="card-header d-flex justify-content-between align-items-center">
            <h3 class="card-title">Daftar Registrasi Mahasiswa</h3>
            @activecan('student-registration.create')
                <a href="{{ route('admin.academic.student-registrations.create') }}" class="btn btn-ghost-primary">
                    <i class="fas fa-plus me-1"></i> Buat Registrasi Baru
                </a>
            @endactivecan
        </div>
        <div class="card-body">
            <livewire:academic.student-registration-table />
        </div>
    </div>

</div>
