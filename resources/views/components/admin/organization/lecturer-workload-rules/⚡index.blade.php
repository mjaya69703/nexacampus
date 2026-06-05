<?php

use Livewire\Component;

new class extends Component
{
    public function render()
    {
        return $this->view()->layout('layouts.app', ['menus' => 'Kepegawaian', 'pages' => 'Aturan SKS BKD']);
    }
};
?>

<div>
    <x-alert />
    <div class="card">
        <div class="card-header d-flex justify-content-between align-items-center">
            <div>
                <h3 class="card-title mb-0">Aturan SKS BKD</h3>
                <small class="text-muted">Konversi aktivitas mengajar, jabatan, dan Tridharma menjadi komponen BKD.</small>
            </div>
            @activecan('lecturer-workload-rule.create')
                <a href="{{ route('admin.organization.lecturer-workload-rules.create') }}" class="btn btn-primary"><i class="fas fa-plus me-2"></i>Tambah Aturan</a>
            @endactivecan
        </div>
        <div class="card-body">
            <livewire:organization.lecturer-workload-rule-table />
        </div>
    </div>
</div>
