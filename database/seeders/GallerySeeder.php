<?php

namespace Database\Seeders;

use App\Models\Publication\Gallery;
use Illuminate\Database\Seeder;

class GallerySeeder extends Seeder
{
    public function run(): void
    {
        Gallery::create([
            'title' => 'Kegiatan Wisuda Periode II 2026',
            'description' => 'Dokumentasi kegiatan wisuda mahasiswa periode II tahun akademik 2025/2026.',
            'image_path' => 'galleries/placeholder-wisuda.jpg',
            'image_alt' => 'Suasana wisuda mahasiswa',
            'is_published' => true,
        ]);

        Gallery::create([
            'title' => 'Seminar Nasional Teknologi Pendidikan',
            'description' => 'Seminar nasional tentang inovasi teknologi dalam dunia pendidikan.',
            'image_path' => 'galleries/placeholder-seminar.jpg',
            'image_alt' => 'Seminar nasional teknologi pendidikan',
            'is_published' => true,
        ]);

        Gallery::create([
            'title' => 'Kunjungan Industri Mahasiswa TI',
            'description' => 'Kunjungan industri mahasiswa program studi Teknik Informatika ke perusahaan teknologi.',
            'image_path' => 'galleries/placeholder-kunjungan.jpg',
            'image_alt' => 'Kunjungan industri mahasiswa TI',
            'is_published' => false,
        ]);
    }
}
