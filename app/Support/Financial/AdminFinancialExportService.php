<?php

namespace App\Support\Financial;

use App\Models\Financial\FinancialClearancePolicy;
use App\Models\Financial\FinancialHold;
use App\Models\Financial\InvoiceAdjustment;
use App\Models\Financial\InvoiceInstallmentRequest;
use App\Models\Financial\InvoiceSchedule;
use App\Models\Financial\Payment;
use App\Models\Financial\Scholarship;
use App\Models\Financial\StudentCreditBalance;
use App\Models\Financial\StudentInvoice;
use App\Models\Financial\StudentScholarship;
use App\Models\Financial\TuitionFee;
use Illuminate\Support\Collection;
use RuntimeException;

/**
 * Payload PDF laporan per resource financial — dipakai
 * AdminFinancialExportController, template generik
 * templates.pdf.academic.table-export (kop + tabel + tanda tangan).
 */
class AdminFinancialExportService
{
    /**
     * @return array{title: string, headers: array<int, string>, rows: Collection<int, array<int, scalar|null>>}
     */
    public function payload(string $resource): array
    {
        return match ($resource) {
            'tuition-fees' => $this->tuitionFees(),
            'clearance-policies' => $this->clearancePolicies(),
            'scholarships' => $this->scholarships(),
            'invoice-schedules' => $this->invoiceSchedules(),
            'student-invoices' => $this->studentInvoices(),
            'payments' => $this->payments(),
            'invoice-adjustments' => $this->invoiceAdjustments(),
            'installment-requests' => $this->installmentRequests(),
            'student-scholarships' => $this->studentScholarships(),
            'student-credits' => $this->studentCredits(),
            'financial-holds' => $this->financialHolds(),
            default => throw new RuntimeException('Resource laporan tidak dikenal: '.$resource),
        };
    }

    private function tuitionFees(): array
    {
        $rows = TuitionFee::query()
            ->with(['academicYear', 'studyProgram'])
            ->orderByDesc('id')
            ->limit(1000)
            ->get()
            ->map(fn (TuitionFee $fee) => [
                $fee->academicYear?->name ?? '-',
                $fee->studyProgram?->name ?? '-',
                $fee->semester,
                $this->money($fee->base_fee),
                $this->money($fee->totalAmount()),
                $this->date($fee->payment_deadline),
                $this->yesNo($fee->is_active),
            ]);

        return $this->build('Laporan Tarif Biaya Kuliah',
            ['Tahun', 'Prodi', 'Smt', 'SPP', 'Total', 'Deadline', 'Aktif'], $rows);
    }

    private function clearancePolicies(): array
    {
        $rows = FinancialClearancePolicy::query()
            ->withCount('holds as holds_count')
            ->orderBy('invoice_type')
            ->orderBy('hold_type')
            ->limit(1000)
            ->get()
            ->map(fn (FinancialClearancePolicy $policy) => [
                $policy->invoice_type,
                $policy->hold_type,
                $policy->mode,
                (int) $policy->grace_days.' hari',
                $this->yesNo($policy->is_active),
                (int) $policy->holds_count.' hold',
            ]);

        return $this->build('Laporan Kebijakan Bebas Tanggungan',
            ['Tagihan', 'Target', 'Mode', 'Tenggang', 'Aktif', 'Hold'], $rows);
    }

    private function scholarships(): array
    {
        $rows = Scholarship::query()
            ->withCount('assignments as recipients_count')
            ->orderBy('name')
            ->limit(1000)
            ->get()
            ->map(fn (Scholarship $scholarship) => [
                $scholarship->name,
                $scholarship->type,
                $scholarship->discount_type === 'fixed'
                    ? $this->money($scholarship->fixed_amount)
                    : rtrim(rtrim((string) $scholarship->discount_percentage, '0'), '.').'%',
                $scholarship->duration_semesters.' smt',
                (int) $scholarship->recipients_count.' penerima',
                $this->yesNo($scholarship->is_active),
            ]);

        return $this->build('Laporan Program Beasiswa',
            ['Program', 'Tipe', 'Potongan', 'Durasi', 'Penerima', 'Aktif'], $rows);
    }

    private function invoiceSchedules(): array
    {
        $rows = InvoiceSchedule::query()
            ->with(['academicYear'])
            ->withCount('invoices as invoices_count')
            ->orderByDesc('id')
            ->limit(1000)
            ->get()
            ->map(fn (InvoiceSchedule $schedule) => [
                $schedule->name,
                $schedule->invoice_kind,
                $schedule->generation_mode,
                $schedule->academicYear?->name ?? '-',
                $schedule->status,
                $this->date($schedule->publish_at),
                (int) $schedule->created_count.' terbit',
            ]);

        return $this->build('Laporan Jadwal Penerbitan Tagihan',
            ['Jadwal', 'Jenis', 'Target', 'Tahun', 'Status', 'Terbit', 'Hasil'], $rows);
    }

    private function studentInvoices(): array
    {
        $rows = StudentInvoice::query()
            ->with(['studentProfile.user', 'academicYear'])
            ->orderByDesc('id')
            ->limit(1000)
            ->get()
            ->map(fn (StudentInvoice $invoice) => [
                $invoice->invoice_number,
                $invoice->studentProfile?->user?->name ?? '-',
                $invoice->studentProfile?->nim ?? '-',
                $invoice->invoice_type,
                $this->money($invoice->total_amount),
                $this->money($invoice->outstanding_amount),
                $invoice->status,
                $this->date($invoice->due_date),
            ]);

        return $this->build('Laporan Tagihan Mahasiswa',
            ['Nomor', 'Mahasiswa', 'NIM', 'Jenis', 'Total', 'Sisa', 'Status', 'Jatuh Tempo'], $rows);
    }

    private function payments(): array
    {
        $rows = Payment::query()
            ->with(['invoice', 'studentProfile.user'])
            ->orderByDesc('id')
            ->limit(1000)
            ->get()
            ->map(fn (Payment $payment) => [
                $payment->payment_number,
                $payment->invoice?->invoice_number ?? '-',
                $payment->studentProfile?->user?->name ?? '-',
                $this->money($payment->amount),
                $payment->payment_method,
                $payment->status,
                $this->date($payment->paid_at),
            ]);

        return $this->build('Laporan Pembayaran Mahasiswa',
            ['Nomor Bayar', 'Invoice', 'Mahasiswa', 'Nominal', 'Metode', 'Status', 'Waktu'], $rows);
    }

    private function invoiceAdjustments(): array
    {
        $rows = InvoiceAdjustment::query()
            ->with(['invoice.studentProfile.user', 'createdBy'])
            ->orderByDesc('id')
            ->limit(1000)
            ->get()
            ->map(fn (InvoiceAdjustment $adjustment) => [
                $adjustment->invoice?->invoice_number ?? '-',
                $adjustment->invoice?->studentProfile?->user?->name ?? '-',
                $adjustment->adjustment_type,
                $this->signedMoney($adjustment->amount),
                $adjustment->reason ?? '-',
                $adjustment->createdBy?->name ?? '-',
                $this->date($adjustment->created_at),
            ]);

        return $this->build('Laporan Penyesuaian Tagihan',
            ['Invoice', 'Mahasiswa', 'Jenis', 'Nominal', 'Alasan', 'Oleh', 'Waktu'], $rows);
    }

    private function installmentRequests(): array
    {
        $rows = InvoiceInstallmentRequest::query()
            ->with(['invoice', 'studentProfile.user'])
            ->orderByDesc('id')
            ->limit(1000)
            ->get()
            ->map(fn (InvoiceInstallmentRequest $request) => [
                $request->invoice?->invoice_number ?? '-',
                $request->studentProfile?->user?->name ?? '-',
                (int) $request->requested_tenor.'x',
                $this->money($request->simulated_total_amount),
                $request->status,
                $this->date($request->created_at),
            ]);

        return $this->build('Laporan Pengajuan Cicilan',
            ['Invoice', 'Mahasiswa', 'Tenor', 'Simulasi', 'Status', 'Diajukan'], $rows);
    }

    private function studentScholarships(): array
    {
        $rows = StudentScholarship::query()
            ->with(['studentProfile.user', 'scholarship', 'academicYear'])
            ->orderByDesc('id')
            ->limit(1000)
            ->get()
            ->map(fn (StudentScholarship $assignment) => [
                $assignment->studentProfile?->user?->name ?? '-',
                $assignment->studentProfile?->nim ?? '-',
                $assignment->scholarship?->name ?? '-',
                $assignment->academicYear?->name ?? 'Semua tahun',
                $assignment->semester ? 'Smt '.$assignment->semester : 'Semua smt',
                $assignment->status,
            ]);

        return $this->build('Laporan Penerima Beasiswa',
            ['Mahasiswa', 'NIM', 'Beasiswa', 'Tahun', 'Semester', 'Status'], $rows);
    }

    private function studentCredits(): array
    {
        $rows = StudentCreditBalance::query()
            ->with(['studentProfile.user', 'studentProfile.studyProgram'])
            ->where('balance', '!=', 0)
            ->orderByDesc('balance')
            ->limit(1000)
            ->get()
            ->map(fn (StudentCreditBalance $balance) => [
                $balance->studentProfile?->user?->name ?? '-',
                $balance->studentProfile?->nim ?? '-',
                $balance->studentProfile?->studyProgram?->name ?? '-',
                $this->money($balance->balance),
                $this->date($balance->updated_at),
            ]);

        return $this->build('Laporan Saldo Deposit Mahasiswa',
            ['Mahasiswa', 'NIM', 'Prodi', 'Saldo', 'Diperbarui'], $rows);
    }

    private function financialHolds(): array
    {
        $rows = FinancialHold::query()
            ->with(['studentProfile.user', 'invoice'])
            ->orderByDesc('id')
            ->limit(1000)
            ->get()
            ->map(fn (FinancialHold $hold) => [
                $hold->studentProfile?->user?->name ?? '-',
                $hold->studentProfile?->nim ?? '-',
                $hold->invoice?->invoice_number ?? '-',
                $hold->hold_type,
                $hold->status,
                $this->date($hold->blocked_at),
                $hold->waived_until ? $this->date($hold->waived_until) : '-',
            ]);

        return $this->build('Laporan Blokir Akademik',
            ['Mahasiswa', 'NIM', 'Invoice', 'Target', 'Status', 'Diblokir', 'Ditangguhkan'], $rows);
    }

    /**
     * @param array<int, string> $headers
     */
    private function build(string $title, array $headers, Collection $rows): array
    {
        return [
            'title' => $title,
            'subtitle' => 'Modul Keuangan',
            'headers' => $headers,
            'rows' => $rows,
        ];
    }

    private function money(float|string|null $amount): string
    {
        return 'Rp '.number_format((float) $amount, 0, ',', '.');
    }

    private function signedMoney(float|string|null $amount): string
    {
        $amount = (float) $amount;

        return ($amount < 0 ? '-Rp ' : 'Rp ').number_format(abs($amount), 0, ',', '.');
    }

    private function yesNo(mixed $value): string
    {
        return $value ? 'Ya' : 'Tidak';
    }

    private function date(mixed $value): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }

        try {
            return \Carbon\Carbon::parse($value)->format('d M Y');
        } catch (\Throwable) {
            return (string) $value;
        }
    }
}
