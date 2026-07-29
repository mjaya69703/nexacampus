<?php

namespace Database\Seeders;

use App\Models\Publication\Agenda;
use Illuminate\Database\Seeder;

class AgendaSeeder extends Seeder
{
    public function run(): void
    {
        Agenda::updateOrCreate(['slug' => 'wisuda-periode-ii-tahun-2026'], [
            'title' => 'Wisuda Periode II Tahun 2026',
            'slug' => 'wisuda-periode-ii-tahun-2026',
            'description' => 'Wisuda bagi mahasiswa yang telah menyelesaikan studi pada Periode II Tahun Akademik 2025/2026.',
            'location' => 'Gedung Serbaguna Kampus Utama',
            'event_date' => '2026-09-15',
            'event_time' => '08:00:00',
            'event_end_date' => '2026-09-16',
            'is_published' => true,
            'published_at' => now(),
            'created_by' => 1,
        ]);

        Agenda::updateOrCreate(['slug' => 'pengenalan-kehidupan-kampus-mahasiswa-baru-2026'], [
            'title' => 'PKKMB Tahun Akademik 2026/2027',
            'slug' => 'pengenalan-kehidupan-kampus-mahasiswa-baru-2026',
            'description' => 'Kegiatan pengenalan kehidupan kampus bagi mahasiswa baru Tahun Akademik 2026/2027.',
            'location' => 'Kampus Pusat',
            'event_date' => '2026-08-10',
            'event_time' => '07:30:00',
            'event_end_date' => '2026-08-14',
            'is_published' => true,
            'published_at' => now(),
            'created_by' => 1,
        ]);

        Agenda::updateOrCreate(['slug' => 'seminar-nasional-teknologi-pendidikan-2026'], [
            'title' => 'Seminar Nasional Teknologi Pendidikan 2026',
            'slug' => 'seminar-nasional-teknologi-pendidikan-2026',
            'description' => 'Seminar nasional tentang inovasi teknologi dalam dunia pendidikan dengan pembicara dari berbagai perguruan tinggi terkemuka.',
            'location' => 'Aula Fakultas Teknik',
            'event_date' => '2026-10-05',
            'event_time' => '09:00:00',
            'is_published' => false,
            'created_by' => 1,
        ]);

        Agenda::updateOrCreate(['slug' => 'rapat-akreditasi-program-studi'], [
            'title' => 'Rapat Akreditasi Program Studi',
            'slug' => 'rapat-akreditasi-program-studi',
            'description' => 'Rapat koordinasi persiapan akreditasi program studi di lingkungan fakultas.',
            'location' => 'Ruang Rapat Fakultas',
            'event_date' => '2026-07-15',
            'event_time' => '10:00:00',
            'is_published' => true,
            'published_at' => now(),
            'created_by' => 1,
        ]);
    }
}
