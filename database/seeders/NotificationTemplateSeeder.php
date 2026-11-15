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
            [
                'event_key' => 'academic.thesis_status_updated',
                'title' => 'Status skripsi diperbarui',
                'body' => "Halo {{ student_name }}, status skripsi \"{{ thesis_title }}\" berubah menjadi {{ status_label }}.\nCatatan: {{ notes }}",
                'variables' => ['student_name', 'thesis_title', 'status_label', 'notes'],
            ],
            [
                'event_key' => 'academic.thesis_milestone_submitted',
                'title' => 'Milestone skripsi dikirim',
                'body' => "Halo {{ lecturer_name }}, mahasiswa {{ student_name }} mengirim milestone {{ milestone_title }} untuk skripsi \"{{ thesis_title }}\".\nSilakan review melalui portal dosen.",
                'variables' => ['lecturer_name', 'student_name', 'thesis_title', 'milestone_title'],
            ],
            [
                'event_key' => 'academic.thesis_milestone_reviewed',
                'title' => 'Review milestone skripsi',
                'body' => "Halo {{ student_name }}, milestone {{ milestone_title }} pada skripsi \"{{ thesis_title }}\" telah direview.\nStatus: {{ status_label }}.\nCatatan: {{ feedback }}",
                'variables' => ['student_name', 'thesis_title', 'milestone_title', 'status_label', 'feedback'],
            ],
            [
                'event_key' => 'academic.thesis_defense_scheduled',
                'title' => 'Jadwal sidang skripsi',
                'body' => "Halo {{ student_name }}, {{ defense_type }} skripsi \"{{ thesis_title }}\" dijadwalkan pada {{ scheduled_at }}.\nLokasi: {{ room }}.",
                'variables' => ['student_name', 'thesis_title', 'defense_type', 'scheduled_at', 'room'],
            ],
            [
                'event_key' => 'academic.thesis_plagiarism_updated',
                'title' => 'Hasil cek plagiarisme skripsi',
                'body' => "Halo {{ student_name }}, hasil cek plagiarisme skripsi \"{{ thesis_title }}\" diperbarui.\nStatus: {{ status_label }}.\nSkor kemiripan: {{ score }}.\nAlat: {{ tool }}.\nCatatan: {{ notes }}",
                'variables' => ['student_name', 'thesis_title', 'status_label', 'score', 'tool', 'notes'],
            ],
            [
                'event_key' => 'library.loan_due_soon',
                'title' => 'Pengingat jatuh tempo buku',
                'body' => "Halo {{ student_name }}, buku \"{{ book_title }}\" akan jatuh tempo pada {{ due_date }} ({{ days_left }} hari lagi).\nDenda keterlambatan {{ fine_per_day }}/hari.\nKembalikan atau perpanjang melalui portal perpustakaan.",
                'variables' => ['student_name', 'book_title', 'due_date', 'days_left', 'fine_per_day'],
            ],
            [
                'event_key' => 'library.loan_overdue',
                'title' => 'Peminjaman buku terlambat',
                'body' => "Halo {{ student_name }}, buku \"{{ book_title }}\" sudah melewati jatuh tempo {{ due_date }}.\nKeterlambatan: {{ days_late }} hari.\nDenda berjalan: {{ fine_amount }}.\nSegera kembalikan buku ke perpustakaan.",
                'variables' => ['student_name', 'book_title', 'due_date', 'days_late', 'fine_amount'],
            ],
            [
                'event_key' => 'library.reservation_ready',
                'title' => 'Reservasi buku siap diambil',
                'body' => "Halo {{ student_name }}, reservasi buku \"{{ book_title }}\" sudah siap diambil.\nKode eksemplar: {{ copy_code }}.\nBatas pengambilan: {{ expires_at }}.",
                'variables' => ['student_name', 'book_title', 'copy_code', 'expires_at'],
            ],
            [
                'event_key' => 'library.fine_submitted',
                'title' => 'Bukti denda diterima',
                'body' => "Halo {{ student_name }}, bukti pembayaran denda untuk buku \"{{ book_title }}\" sebesar {{ fine_amount }} sudah diterima.\nStatus: menunggu verifikasi pustakawan.",
                'variables' => ['student_name', 'book_title', 'fine_amount'],
            ],
            [
                'event_key' => 'library.fine_verified',
                'title' => 'Pembayaran denda diverifikasi',
                'body' => "Halo {{ student_name }}, pembayaran denda untuk buku \"{{ book_title }}\" sebesar {{ fine_amount }} sudah diverifikasi.\nStatus: lunas.",
                'variables' => ['student_name', 'book_title', 'fine_amount'],
            ],
            [
                'event_key' => 'library.fine_rejected',
                'title' => 'Bukti pembayaran denda ditolak',
                'body' => "Halo {{ student_name }}, bukti pembayaran denda untuk buku \"{{ book_title }}\" sebesar {{ fine_amount }} belum dapat diverifikasi.\nAlasan: {{ reason }}.\nSilakan unggah ulang bukti pembayaran.",
                'variables' => ['student_name', 'book_title', 'fine_amount', 'reason'],
            ],
        ];
    }
}
