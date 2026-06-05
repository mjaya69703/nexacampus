<?php

use Livewire\Component;

new class extends Component
{
    public function render()
    {
        return $this->view()->layout('layouts.app', ['menus' => 'Kepegawaian', 'pages' => 'Periode EDOM']);
    }
};
?>

<div>
    <x-alert />
    <div class="card">
        <div class="card-header d-flex justify-content-between align-items-center">
            <div>
                <h3 class="card-title mb-0">Periode EDOM</h3>
                <small class="text-muted">Atur jendela evaluasi dosen oleh mahasiswa.</small>
            </div>
            @activecan('edom-period.create')
                <a href="{{ route('admin.organization.edom-periods.create') }}" class="btn btn-primary"><i class="fas fa-plus me-2"></i>Tambah Periode</a>
            @endactivecan
        </div>
        <div class="card-body"><livewire:organization.edom-period-table /></div>
    </div>
</div>
