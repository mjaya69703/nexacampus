<?php

namespace App\Support\Financial;

use App\Models\Academic\AcademicYear;
use App\Models\Academic\StudentProfile;
use App\Models\Financial\InvoiceItem;
use App\Models\Financial\InvoiceSchedule;
use App\Models\Financial\StudentInvoice;
use App\Models\Financial\TuitionFee;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class InvoiceGenerationService
{
    public function generateForStudent(
        StudentProfile $studentProfile,
        AcademicYear $academicYear,
        int $semester,
        ?string $dueDate = null,
        ?int $createdBy = null,
        bool $issueImmediately = true,
        ?InvoiceSchedule $schedule = null,
    ): StudentInvoice {
        $studentProfile->loadMissing(['user', 'studyProgram']);

        if (! $studentProfile->study_program_id) {
            throw new RuntimeException('Student belum memiliki program studi.');
        }

        $tuitionFee = TuitionFee::query()
            ->where('academic_year_id', $academicYear->id)
            ->where('study_program_id', $studentProfile->study_program_id)
            ->where('semester', $semester)
            ->where('is_active', true)
            ->first();

        if (! $tuitionFee) {
            throw new RuntimeException('Tuition fee untuk student ini belum dikonfigurasi.');
        }

        $existing = StudentInvoice::query()
            ->where('student_profile_id', $studentProfile->id)
            ->where('academic_year_id', $academicYear->id)
            ->where('semester', $semester)
            ->where('invoice_type', 'tuition')
            ->where('status', '!=', 'cancelled')
            ->first();

        if ($existing) {
            throw new RuntimeException('Invoice untuk student, tahun akademik, dan semester ini sudah ada.');
        }

        return DB::transaction(function () use ($studentProfile, $academicYear, $semester, $dueDate, $createdBy, $tuitionFee, $issueImmediately, $schedule) {
            $invoice = StudentInvoice::create([
                'invoice_number' => app(InvoiceNumberService::class)->generate(),
                'student_profile_id' => $studentProfile->id,
                'academic_year_id' => $academicYear->id,
                'semester' => $semester,
                'invoice_type' => 'tuition',
                'source_type' => $tuitionFee::class,
                'source_id' => $tuitionFee->id,
                'invoice_schedule_id' => $schedule?->id,
                'total_amount' => 0,
                'paid_amount' => 0,
                'outstanding_amount' => 0,
                'status' => 'draft',
                'due_date' => $dueDate ?: $tuitionFee->payment_deadline,
                'created_by' => $createdBy,
            ]);

            $this->createItems($invoice, $tuitionFee, $academicYear, $semester);

            if ($issueImmediately) {
                $invoice = app(InvoicePublishingService::class)->issue($invoice, $createdBy);
                app(ScholarshipApplicationService::class)->applyMatchingScholarships($invoice, $createdBy);

                return app(InvoiceStatusService::class)->refresh($invoice);
            }

            return app(InvoiceStatusService::class)->refresh($invoice);
        });
    }

    public function createCustomInvoice(
        StudentProfile $studentProfile,
        array $items,
        string $invoiceType,
        string $dueDate,
        ?AcademicYear $academicYear = null,
        ?int $semester = null,
        ?string $notes = null,
        ?int $createdBy = null,
        bool $issueImmediately = false,
        ?InvoiceSchedule $schedule = null,
    ): StudentInvoice {
        if (empty($items)) {
            throw new RuntimeException('Invoice wajib memiliki minimal satu item.');
        }

        return DB::transaction(function () use ($studentProfile, $items, $invoiceType, $dueDate, $academicYear, $semester, $notes, $createdBy, $issueImmediately, $schedule) {
            $invoice = StudentInvoice::create([
                'invoice_number' => app(InvoiceNumberService::class)->generate(),
                'student_profile_id' => $studentProfile->id,
                'academic_year_id' => $academicYear?->id,
                'semester' => $semester,
                'invoice_type' => $invoiceType,
                'invoice_schedule_id' => $schedule?->id,
                'total_amount' => 0,
                'paid_amount' => 0,
                'outstanding_amount' => 0,
                'status' => 'draft',
                'due_date' => $dueDate,
                'notes' => $notes,
                'created_by' => $createdBy,
            ]);

            foreach (array_values($items) as $index => $item) {
                $amount = (float) $item['amount'];

                if (($item['item_type'] ?? 'fee') === 'discount') {
                    $amount = -abs($amount);
                }

                InvoiceItem::create([
                    'student_invoice_id' => $invoice->id,
                    'item_type' => $item['item_type'] ?? 'fee',
                    'description' => $item['description'],
                    'amount' => $amount,
                    'sort_order' => $index + 1,
                ]);
            }

            if ($issueImmediately) {
                $invoice = app(InvoicePublishingService::class)->issue($invoice, $createdBy);
                app(ScholarshipApplicationService::class)->applyMatchingScholarships($invoice, $createdBy);

                return app(InvoiceStatusService::class)->refresh($invoice);
            }

            return app(InvoiceStatusService::class)->refresh($invoice);
        });
    }

    private function createItems(StudentInvoice $invoice, TuitionFee $tuitionFee, AcademicYear $academicYear, int $semester): void
    {
        $items = [
            ['base_fee', 'SPP/UKT '.$academicYear->name.' - Semester '.$semester, $tuitionFee->base_fee],
            ['lab_fee', 'Biaya Laboratorium', $tuitionFee->lab_fee],
            ['library_fee', 'Biaya Perpustakaan', $tuitionFee->library_fee],
            ['activity_fee', 'Biaya Kegiatan', $tuitionFee->activity_fee],
        ];

        foreach ($items as $index => [$type, $description, $amount]) {
            if ((float) $amount <= 0) {
                continue;
            }

            $invoice->items()->create([
                'item_type' => $type,
                'description' => $description,
                'amount' => $amount,
                'sort_order' => $index + 1,
            ]);
        }
    }
}
