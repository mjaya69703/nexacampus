<?php

namespace Database\Seeders;

use App\Models\Settings\NotificationTemplate;
use Illuminate\Database\Seeder;

class NotificationTemplateSeeder extends Seeder
{
    public function run(): void
    {
        foreach ($this->templates() as $template) {
            NotificationTemplate::query()->updateOrCreate(
                [
                    'event_key' => $template['event_key'],
                    'channel' => 'whatsapp',
                ],
                [
                    'title' => $template['title'],
                    'body' => $template['body'],
                    'is_active' => true,
                    'variables' => $template['variables'],
                ]
            );
        }
    }

    private function templates(): array
    {
        return [
            [
                'event_key' => 'system.whatsapp_test',
                'title' => 'Test WhatsApp NexaCampus',
                'body' => "Halo {{ recipient_name }}, ini pesan test WhatsApp dari NexaCampus.\nProvider aktif: {{ provider }}.",
                'variables' => ['recipient_name', 'provider'],
            ],
            [
                'event_key' => 'academic.grade_published',
                'title' => 'Nilai dipublikasikan',
                'body' => "Halo {{ student_name }}, nilai {{ course_name }} sudah dipublikasikan.\nNilai: {{ letter_grade }} ({{ final_score }}).\nSilakan cek portal mahasiswa.",
                'variables' => ['student_name', 'course_name', 'letter_grade', 'final_score'],
            ],
            [
                'event_key' => 'academic.study_plan_status_updated',
                'title' => 'Status KRS diperbarui',
                'body' => "Halo {{ student_name }}, status KRS semester {{ semester_no }} berubah menjadi {{ status_label }}.\nCatatan: {{ notes }}",
                'variables' => ['student_name', 'semester_no', 'status_label', 'notes'],
            ],
            [
                'event_key' => 'financial.invoice_issued',
                'title' => 'Tagihan diterbitkan',
                'body' => "Halo {{ student_name }}, tagihan {{ invoice_number }} sebesar {{ total_amount }} telah diterbitkan.\nJatuh tempo: {{ due_date }}.",
                'variables' => ['student_name', 'invoice_number', 'total_amount', 'due_date'],
            ],
            [
                'event_key' => 'financial.invoice_overdue',
                'title' => 'Tagihan jatuh tempo',
                'body' => "Halo {{ student_name }}, tagihan {{ invoice_number }} sudah melewati jatuh tempo.\nSisa tagihan: {{ outstanding_amount }}.",
                'variables' => ['student_name', 'invoice_number', 'outstanding_amount'],
            ],
            [
                'event_key' => 'financial.payment_verified',
                'title' => 'Pembayaran diverifikasi',
                'body' => "Halo {{ student_name }}, pembayaran {{ payment_number }} sebesar {{ amount }} sudah diverifikasi.\nInvoice: {{ invoice_number }}.",
                'variables' => ['student_name', 'payment_number', 'amount', 'invoice_number'],
            ],
            [
                'event_key' => 'financial.payment_rejected',
                'title' => 'Pembayaran ditolak',
                'body' => "Halo {{ student_name }}, pembayaran {{ payment_number }} belum bisa diverifikasi.\nCatatan: {{ notes }}",
                'variables' => ['student_name', 'payment_number', 'notes'],
            ],
            [
                'event_key' => 'admission.status_updated',
                'title' => 'Status pendaftaran diperbarui',
                'body' => "Halo {{ applicant_name }}, status pendaftaran {{ application_number }} berubah menjadi {{ status_label }}.\nSilakan cek portal pendaftaran.",
                'variables' => ['applicant_name', 'application_number', 'status_label'],
            ],
            [
                'event_key' => 'student_service.status_updated',
                'title' => 'Status layanan mahasiswa diperbarui',
                'body' => "Halo {{ student_name }}, {{ request_label }} {{ request_number }} berubah menjadi {{ status_label }}.\nCatatan: {{ notes }}",
                'variables' => ['student_name', 'request_label', 'request_number', 'status_label', 'notes'],
            ],
        ];
    }
}
