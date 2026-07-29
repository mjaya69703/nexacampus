<?php

namespace Database\Seeders;

use App\Models\Publication\News;
use Illuminate\Database\Seeder;

class NewsSeeder extends Seeder
{
    public function run(): void
    {
        News::updateOrCreate(['slug' => 'selamat-datang-tahun-akademik-2026-2027'], [
            'title' => 'Selamat Datang Tahun Akademik 2026/2027',
            'slug' => 'selamat-datang-tahun-akademik-2026-2027',
            'content' => '<p>Kampus kami dengan bangga menyambut tahun akademik baru 2026/2027. Berbagai program dan kegiatan baru telah disiapkan untuk menyambut mahasiswa baru maupun mahasiswa lama. Mari kita sambut semester baru ini dengan semangat dan antusiasme yang tinggi!</p>',
            'excerpt' => 'Kampus menyambut tahun akademik baru 2026/2027 dengan berbagai program dan kegiatan baru.',
            'is_published' => true,
            'published_at' => now(),
            'created_by' => 1,
        ]);

        News::updateOrCreate(['slug' => 'beasiswa-prestasi-2026-dibuka'], [
            'title' => 'Pendaftaran Beasiswa Prestasi 2026 Telah Dibuka',
            'slug' => 'beasiswa-prestasi-2026-dibuka',
            'content' => '<p>Program beasiswa prestasi tahun 2026 resmi dibuka untuk seluruh mahasiswa aktif. Beasiswa ini diperuntukkan bagi mahasiswa yang memiliki prestasi akademik maupun non-akademik. Pendaftaran akan dibuka hingga 30 Agustus 2026.</p>',
            'excerpt' => 'Pendaftaran beasiswa prestasi 2026 dibuka hingga 30 Agustus 2026.',
            'is_published' => true,
            'published_at' => now(),
            'created_by' => 1,
        ]);

        News::updateOrCreate(['slug' => 'workshop-digital-marketing'], [
            'title' => 'Workshop Digital Marketing untuk Mahasiswa',
            'slug' => 'workshop-digital-marketing',
            'content' => '<p>Dalam rangka meningkatkan kompetensi mahasiswa di era digital, kampus menyelenggarakan Workshop Digital Marketing. Acara ini akan menghadirkan praktisi berpengalaman dari industri dan terbuka untuk seluruh mahasiswa. Workshop akan dilaksanakan pada tanggal 15 Agustus 2026.</p>',
            'excerpt' => 'Workshop Digital Marketing untuk meningkatkan kompetensi mahasiswa di era digital.',
            'is_published' => true,
            'published_at' => now()->addDays(1),
            'created_by' => 1,
        ]);

        News::updateOrCreate(['slug' => 'pengumuman-libur-hari-kemerdekaan'], [
            'title' => 'Pengumuman Libur dalam Rangka Hari Kemerdekaan RI ke-81',
            'slug' => 'pengumuman-libur-hari-kemerdekaan',
            'content' => '<p>Sehubungan dengan peringatan Hari Kemerdekaan Republik Indonesia ke-81, seluruh kegiatan akademik dan administratif di lingkungan kampus diliburkan pada tanggal 17 Agustus 2026. Kegiatan akan kembali normal pada tanggal 18 Agustus 2026. Selamat merayakan Hari Kemerdekaan!</p>',
            'excerpt' => 'Libur Hari Kemerdekaan RI ke-81 pada 17 Agustus 2026.',
            'is_published' => false,
            'published_at' => null,
            'created_by' => 1,
        ]);
    }
}
