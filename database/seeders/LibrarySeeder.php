<?php

namespace Database\Seeders;

use App\Models\Library\Book;
use App\Models\Library\BookCategory;
use App\Models\Library\BookCopy;
use App\Models\Library\BookLoan;
use App\Models\Library\BookReservation;
use App\Models\User;
use App\Support\Library\LibraryService;
use Illuminate\Database\Seeder;
use Illuminate\Validation\ValidationException;

class LibrarySeeder extends Seeder
{
    public function run(): void
    {
        $admin = User::query()->where('username', 'superuser')->first();

        $categories = [
            ['name' => 'Umum', 'code' => 'UMUM', 'description' => 'Koleksi umum dan referensi lintas disiplin.'],
            ['name' => 'Teknologi Informasi', 'code' => 'TI', 'description' => 'Pemrograman, basis data, jaringan, dan sistem informasi.'],
            ['name' => 'Ekonomi & Bisnis', 'code' => 'EKBI', 'description' => 'Manajemen, akuntansi, keuangan, dan kewirausahaan.'],
            ['name' => 'Sastra & Bahasa', 'code' => 'SASTRA', 'description' => 'Sastra, linguistik, dan bahasa.'],
            ['name' => 'Sains', 'code' => 'SAINS', 'description' => 'Matematika, fisika, statistika, dan ilmu alam.'],
        ];

        foreach ($categories as $category) {
            BookCategory::updateOrCreate(
                ['code' => $category['code']],
                [
                    ...$category,
                    'is_active' => true,
                    'created_by' => $admin?->id,
                    'updated_by' => $admin?->id,
                ],
            );
        }

        $categoryIds = BookCategory::query()->pluck('id', 'code');

        $books = [
            ['isbn' => '9786020000001', 'title' => 'Pemrograman Laravel untuk Pemula', 'author' => 'Andi Nugraha', 'publisher' => 'Informatika', 'publication_year' => 2024, 'category' => 'TI', 'shelf' => 'TI-A1'],
            ['isbn' => '9786020000002', 'title' => 'Basis Data Relasional: Teori dan Praktik', 'author' => 'Siti Rahma', 'publisher' => 'Andi Offset', 'publication_year' => 2023, 'category' => 'TI', 'shelf' => 'TI-A2'],
            ['isbn' => '9786020000003', 'title' => 'Desain Pengalaman Pengguna', 'author' => 'Doni Pratama', 'publisher' => 'Elex Media Komputindo', 'publication_year' => 2022, 'category' => 'TI', 'shelf' => 'TI-A3'],
            ['isbn' => '9786020000004', 'title' => 'Manajemen Keuangan Perguruan Tinggi', 'author' => 'Rina Wulandari', 'publisher' => 'Salemba Empat', 'publication_year' => 2021, 'category' => 'EKBI', 'shelf' => 'EK-B1'],
            ['isbn' => '9786020000005', 'title' => 'Metodologi Penelitian Kualitatif', 'author' => 'Ahmad Fauzi', 'publisher' => 'Prenada Media', 'publication_year' => 2020, 'category' => 'UMUM', 'shelf' => 'UM-C1'],
            ['isbn' => '9786020000006', 'title' => 'Sastra Indonesia Modern', 'author' => 'Maya Sari', 'publisher' => 'Balai Pustaka', 'publication_year' => 2019, 'category' => 'SASTRA', 'shelf' => 'SA-D1'],
            ['isbn' => '9786020000007', 'title' => 'Fisika Dasar untuk Teknik', 'author' => 'Budi Santoso', 'publisher' => 'Erlangga', 'publication_year' => 2022, 'category' => 'SAINS', 'shelf' => 'SA-E1'],
            ['isbn' => '9786020000008', 'title' => 'Kecerdasan Buatan: Konsep dan Implementasi', 'author' => 'Eko Prasetyo', 'publisher' => 'Informatika', 'publication_year' => 2024, 'category' => 'TI', 'shelf' => 'TI-A4'],
            ['isbn' => '9786020000009', 'title' => 'Kewirausahaan Digital', 'author' => 'Lestari Dewi', 'publisher' => 'Gramedia Pustaka Utama', 'publication_year' => 2023, 'category' => 'EKBI', 'shelf' => 'EK-B2'],
            ['isbn' => '9786020000010', 'title' => 'Statistika Terapan dengan Python', 'author' => 'Fajar Hidayat', 'publisher' => 'Andi Offset', 'publication_year' => 2025, 'category' => 'SAINS', 'shelf' => 'SA-E2'],
        ];

        foreach ($books as $row) {
            $book = Book::updateOrCreate(
                ['isbn' => $row['isbn']],
                [
                    'book_category_id' => $categoryIds[$row['category']] ?? $categoryIds->first(),
                    'title' => $row['title'],
                    'author' => $row['author'],
                    'publisher' => $row['publisher'],
                    'publication_year' => $row['publication_year'],
                    'language' => 'Indonesia',
                    'description' => 'Koleksi perpustakaan kampus untuk mendukung pembelajaran dan penelitian.',
                    'shelf_location' => $row['shelf'],
                    'is_active' => true,
                    'created_by' => $admin?->id,
                    'updated_by' => $admin?->id,
                ],
            );

            for ($i = 1; $i <= 2; $i++) {
                $code = 'BK-'.str_pad((string) $book->id, 4, '0', STR_PAD_LEFT).'-'.str_pad((string) $i, 2, '0', STR_PAD_LEFT);

                BookCopy::firstOrCreate(
                    ['code' => $code],
                    [
                        'book_id' => $book->id,
                        'status' => 'available',
                        'acquired_at' => now()->subMonths(6)->toDateString(),
                        'created_by' => $admin?->id,
                        'updated_by' => $admin?->id,
                    ],
                );
            }
        }

        $this->seedDemoTransactions($admin);
    }

    private function seedDemoTransactions(?User $admin): void
    {
        $student = User::query()->where('username', 'rania.putri')->first();
        $senior = User::query()->where('username', 'nadia.senior')->first();

        if ($student && ! BookLoan::query()->where('user_id', $student->id)->active()->exists()) {
            $copy = BookCopy::query()
                ->where('status', 'available')
                ->whereHas('book', fn ($query) => $query->where('is_active', true))
                ->orderBy('id')
                ->first();

            if ($copy) {
                try {
                    app(LibraryService::class)->borrow($student, $copy, $admin ?? $student, [
                        'borrowed_at' => now()->subDays(3),
                        'due_at' => now()->addDays(11)->endOfDay(),
                        'notes' => 'Peminjaman demo perpustakaan.',
                    ]);
                } catch (ValidationException) {
                    // Lewati bila aturan sirkulasi belum terpenuhi (mis. data demo sudah ada).
                }
            }
        }

        if ($senior && ! BookReservation::query()->where('user_id', $senior->id)->active()->exists()) {
            $book = Book::query()
                ->where('is_active', true)
                ->orderBy('id')
                ->skip(3)
                ->first();

            if ($book) {
                try {
                    app(LibraryService::class)->createReservation($senior, $book, 'Referensi untuk tugas akhir.');
                } catch (ValidationException) {
                    // Lewati bila mahasiswa sudah meminjam buku yang sama.
                }
            }
        }
    }
}
