<?php

use Livewire\Component;

new class extends Component
{
    public function render()
    {
        return $this->view()->layout('layouts.app', ['menus' => 'Kepegawaian', 'pages' => 'Pertanyaan EDOM']);
    }
};
?>

<div>
    <x-alert />
    <div class="card">
        <div class="card-header d-flex justify-content-between align-items-center">
            <div><h3 class="card-title mb-0">Pertanyaan EDOM</h3><small class="text-muted">Kelola pertanyaan skala dan komentar evaluasi dosen.</small></div>
            @activecan('edom-question.create')<a href="{{ route('admin.organization.edom-questions.create') }}" class="btn btn-primary"><i class="fas fa-plus me-2"></i>Tambah Pertanyaan</a>@endactivecan
        </div>
        <div class="card-body"><livewire:organization.edom-question-table /></div>
    </div>
</div>
