<?php

use Livewire\Component;

new class extends Component
{
    public function render()
    {
        return $this->view()->layout('layouts.app', [
            'menus' => 'Financial',
            'pages' => 'Student Invoices',
        ]);
    }
};
?>

<div class="w-full" style="width: 100% !important">
    <x-alert />

    <div class="card">
        <div class="card-header d-flex justify-content-between align-items-center">
            <div>
                <h3 class="card-title mb-0">Student Invoices</h3>
                <small class="text-muted">Tagihan mahasiswa berdasarkan tuition fee aktif.</small>
            </div>
            @activecan('student-invoice.create')
                <a href="{{ route('admin.financial.student-invoices.create') }}" class="btn btn-ghost-primary">
                    <i class="fas fa-plus me-1"></i> Generate Invoice
                </a>
            @endactivecan
        </div>
        <div class="card-body">
            <livewire:financial.invoice-table />
        </div>
    </div>
</div>
