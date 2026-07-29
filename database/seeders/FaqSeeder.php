<?php

namespace Database\Seeders;

use App\Enums\FaqType;
use App\Models\Publication\Faq;
use Illuminate\Database\Seeder;

class FaqSeeder extends Seeder
{
    public function run(): void
    {
        $faqs = [
            // PMB / Admission FAQs
            [
                'type' => FaqType::ADMISSION->value,
                'category' => 'Pendaftaran',
                'question' => 'Bagaimana cara melakukan pendaftaran secara online?',
                'answer' => 'Akses menu <strong>Daftar Sekarang</strong> di bagian atas halaman ini. Isi formulir biodata awal, lalu sistem akan mengirimkan <strong>Nomor Pendaftaran</strong> dan password sementara ke email yang Anda daftarkan. Gunakan kredensial tersebut untuk masuk ke Portal Pendaftar dan melengkapi sisa persyaratan secara bertahap.',
                'sort_order' => 1,
                'is_active' => true,
            ],
            [
                'type' => FaqType::ADMISSION->value,
                'category' => 'Pendaftaran',
                'question' => 'Apakah saya bisa mendaftar lebih dari satu program studi?',
                'answer' => 'Ya, setiap calon mahasiswa diperbolehkan memilih <strong>maksimal 3 (tiga) pilihan program studi</strong> sesuai skala prioritas. Proses seleksi dimulai dari pilihan pertama, dan apabila tidak lolos akan dipertimbangkan ke pilihan berikutnya sesuai ketersediaan kuota.',
                'sort_order' => 2,
                'is_active' => true,
            ],
            [
                'type' => FaqType::ADMISSION->value,
                'category' => 'Biaya',
                'question' => 'Berapa biaya pendaftaran PMB NexaCampus?',
                'answer' => 'Biaya pendaftaran adalah sebesar <strong>Rp 250.000,-</strong> untuk semua jalur masuk. Pembayaran dapat dilakukan melalui Virtual Account Bank yang bekerja sama dengan kampus. Biaya pendaftaran <strong>tidak dapat dikembalikan</strong> setelah berhasil dibayar.',
                'sort_order' => 3,
                'is_active' => true,
            ],
            [
                'type' => FaqType::ADMISSION->value,
                'category' => 'Seleksi',
                'question' => 'Kapan pengumuman hasil seleksi diumumkan?',
                'answer' => 'Pengumuman hasil seleksi diumumkan <strong>7 hari kerja</strong> setelah masa pendaftaran gelombang tersebut ditutup, atau <strong>3 hari kerja</strong> pasca pelaksanaan tes mandiri (CBT) di kampus. Anda dapat memantau status pendaftaran secara real-time melalui menu <strong>Cek Status Pendaftaran</strong>.',
                'sort_order' => 4,
                'is_active' => true,
            ],
            [
                'type' => FaqType::ADMISSION->value,
                'category' => 'Dokumen',
                'question' => 'Dokumen apa saja yang wajib disiapkan?',
                'answer' => 'Dokumen umum yang wajib disiapkan oleh semua jalur antara lain: <ul class="mt-2 ps-3"><li>Scan Ijazah/SKL yang dilegalisir</li><li>Scan Nilai Rapor Semester 1–5</li><li>Pas foto berwarna terbaru (3×4, latar merah/biru)</li><li>Fotokopi KTP/Kartu Pelajar</li><li>Scan Kartu Keluarga</li></ul>Persyaratan tambahan bergantung pada jalur masuk yang Anda pilih.',
                'sort_order' => 5,
                'is_active' => true,
            ],
            [
                'type' => FaqType::ADMISSION->value,
                'category' => 'Seleksi',
                'question' => 'Apakah ada tes tulis / tes masuk?',
                'answer' => 'Tergantung jalur masuk yang Anda pilih. Jalur <strong>Prestasi</strong> umumnya tidak memerlukan tes tulis karena seleksi berbasis nilai rapor dan sertifikat prestasi. Jalur <strong>Ujian Tulis</strong> mengharuskan Anda mengikuti Computer Based Test (CBT) di kampus pada jadwal yang ditentukan.',
                'sort_order' => 6,
                'is_active' => true,
            ],
            [
                'type' => FaqType::ADMISSION->value,
                'category' => 'Biaya',
                'question' => 'Apakah ada beasiswa atau keringanan biaya?',
                'answer' => 'Ya! Tersedia beberapa skema bantuan biaya pendidikan, di antaranya: <ul class="mt-2 ps-3"><li><strong>KIP Kuliah</strong> — pembebasan biaya penuh dari pemerintah</li><li><strong>Beasiswa Prestasi</strong> — untuk mahasiswa berprestasi akademik/non-akademik</li><li><strong>Cicilan</strong> — program cicilan pembayaran UKT untuk kesulitan ekonomi</li></ul>',
                'sort_order' => 7,
                'is_active' => true,
            ],
            [
                'type' => FaqType::ADMISSION->value,
                'category' => 'Pendaftaran',
                'question' => 'Berapa lama proses verifikasi berkas berlangsung?',
                'answer' => 'Proses verifikasi berkas umumnya berlangsung <strong>3–5 hari kerja</strong> setelah semua dokumen diterima secara lengkap. Anda akan mendapatkan notifikasi email jika terdapat kekurangan berkas atau jika verifikasi telah selesai.',
                'sort_order' => 8,
                'is_active' => true,
            ],

            // General / Umum FAQs
            [
                'type' => FaqType::GENERAL->value,
                'category' => 'Umum',
                'question' => 'Di mana lokasi kampus utama NexaCampus?',
                'answer' => 'Kampus utama NexaCampus berlokasi di pusat kota dengan akses transportasi publik yang mudah. Informasi alamat lengkap dan peta lokasi dapat dilihat di halaman Kontak dan Profil Kampus.',
                'sort_order' => 1,
                'is_active' => true,
            ],
            [
                'type' => FaqType::GENERAL->value,
                'category' => 'Fasilitas',
                'question' => 'Fasilitas apa saja yang tersedia bagi mahasiswa?',
                'answer' => 'NexaCampus menyediakan fasilitas modern termasuk Perpustakaan Digital, Laboratorium Komputer & Sains, Koneksi Wi-Fi Campus-wide, Ruang Olahraga, Auditorium, Kantin Higienis, serta Co-working Space mahasiswa.',
                'sort_order' => 2,
                'is_active' => true,
            ],

            // Academic FAQs
            [
                'type' => FaqType::ACADEMIC->value,
                'category' => 'Perkuliahan',
                'question' => 'Bagaimana sistem pengisian Kartu Rencana Studi (KRS)?',
                'answer' => 'Pengisian KRS dilakukan secara online melalui Portal Mahasiswa pada jadwal periode KRS yang ditetapkan dalam Kalender Akademik. Mahasiswa juga dapat berkonsultasi dengan Dosen Pembimbing Akademik (DPA).',
                'sort_order' => 1,
                'is_active' => true,
            ],

            // Financial FAQs
            [
                'type' => FaqType::FINANCIAL->value,
                'category' => 'Pembayaran',
                'question' => 'Kapan batas akhir pembayaran UKT/SPP setiap semester?',
                'answer' => 'Jadwal dan batas waktu pembayaran tagihan SPP/UKT selalu dicantumkan pada sistem invoice mahasiswa serta diumumkan di Kalender Akademik sebelum periode KRS dimulai.',
                'sort_order' => 1,
                'is_active' => true,
            ],

            // Student Service FAQs
            [
                'type' => FaqType::STUDENT_SERVICE->value,
                'category' => 'Layanan Surat',
                'question' => 'Bagaimana cara mengajukan Surat Keterangan Mahasiswa Aktif?',
                'answer' => 'Pengajuan surat keterangan dapat dilakukan secara mandiri melalui menu **Layanan Mahasiswa -> Pengajuan Surat** di Portal Mahasiswa. Surat yang disetujui dapat diunduh dengan tanda tangan digital resmi.',
                'sort_order' => 1,
                'is_active' => true,
            ],
        ];

        foreach ($faqs as $faqData) {
            Faq::updateOrCreate(
                [
                    'type' => $faqData['type'],
                    'question' => $faqData['question'],
                ],
                $faqData
            );
        }
    }
}
