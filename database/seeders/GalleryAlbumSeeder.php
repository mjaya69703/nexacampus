<?php

namespace Database\Seeders;

use App\Models\Publication\GalleryAlbum;
use App\Models\Publication\GalleryImage;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class GalleryAlbumSeeder extends Seeder
{
    public function run(): void
    {
        // Album 1
        $album1 = GalleryAlbum::updateOrCreate(
            ['slug' => 'kegiatan-wisuda-periode-ii-2026'],
            [
                'title' => 'Kegiatan Wisuda Periode II 2026',
                'description' => 'Dokumentasi kegiatan wisuda mahasiswa periode II tahun akademik 2025/2026.',
                'cover_image_path' => 'galleries/placeholder-wisuda.jpg',
                'is_published' => true,
                'created_by' => 1,
            ]
        );

        GalleryImage::updateOrCreate(
            ['gallery_album_id' => $album1->id, 'image_path' => 'galleries/placeholder-wisuda.jpg'],
            ['caption' => 'Suasana wisuda mahasiswa', 'sort_order' => 1, 'created_by' => 1]
        );

        GalleryImage::updateOrCreate(
            ['gallery_album_id' => $album1->id, 'image_path' => 'galleries/placeholder-wisuda-2.jpg'],
            ['caption' => 'Prosesi wisuda', 'sort_order' => 2, 'created_by' => 1]
        );

        // Album 2
        $album2 = GalleryAlbum::updateOrCreate(
            ['slug' => 'seminar-nasional-teknologi-pendidikan'],
            [
                'title' => 'Seminar Nasional Teknologi Pendidikan',
                'description' => 'Seminar nasional tentang inovasi teknologi dalam dunia pendidikan.',
                'cover_image_path' => 'galleries/placeholder-seminar.jpg',
                'is_published' => true,
                'created_by' => 1,
            ]
        );

        GalleryImage::updateOrCreate(
            ['gallery_album_id' => $album2->id, 'image_path' => 'galleries/placeholder-seminar.jpg'],
            ['caption' => 'Pembukaan seminar nasional', 'sort_order' => 1, 'created_by' => 1]
        );

        GalleryImage::updateOrCreate(
            ['gallery_album_id' => $album2->id, 'image_path' => 'galleries/placeholder-seminar-2.jpg'],
            ['caption' => 'Sesi presentasi', 'sort_order' => 2, 'created_by' => 1]
        );

        // Album 3
        $album3 = GalleryAlbum::updateOrCreate(
            ['slug' => 'kunjungan-industri-mahasiswa-ti'],
            [
                'title' => 'Kunjungan Industri Mahasiswa TI',
                'description' => 'Kunjungan industri mahasiswa program studi Teknik Informatika ke perusahaan teknologi.',
                'cover_image_path' => 'galleries/placeholder-kunjungan.jpg',
                'is_published' => false,
                'created_by' => 1,
            ]
        );

        GalleryImage::updateOrCreate(
            ['gallery_album_id' => $album3->id, 'image_path' => 'galleries/placeholder-kunjungan.jpg'],
            ['caption' => 'Foto bersama di lokasi kunjungan', 'sort_order' => 1, 'created_by' => 1]
        );

        GalleryImage::updateOrCreate(
            ['gallery_album_id' => $album3->id, 'image_path' => 'galleries/placeholder-kunjungan-2.jpg'],
            ['caption' => 'Sesi diskusi dengan tim perusahaan', 'sort_order' => 2, 'created_by' => 1]
        );
    }
}
