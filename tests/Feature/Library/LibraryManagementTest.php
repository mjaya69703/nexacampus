<?php

use App\Enums\BookCopyStatus;
use App\Enums\BookLoanStatus;
use App\Enums\BookReservationStatus;
use App\Models\Library\Book;
use App\Models\Library\BookCategory;
use App\Models\Library\BookCopy;
use App\Models\Library\BookLoan;
use App\Models\Library\BookReservation;
use App\Models\Settings\System;
use App\Models\User;
use App\Support\Library\LibraryService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Inertia\Testing\AssertableInertia as Assert;
use Spatie\Permission\Models\Permission as SpatiePermission;
use Spatie\Permission\Models\Role as SpatieRole;

uses(RefreshDatabase::class);

/**
 * @return array<string, mixed>
 */
function libraryTestSetup(): array
{
    System::create([
        'app_name' => 'NexaCampus Test',
        'app_version' => '1.0.0',
        'app_description' => 'Test',
        'app_url' => 'http://localhost',
        'app_email' => 'test@localhost',
    ])->forceFill(['is_installed' => true])->save();

    foreach (['operator', 'student', 'lecturer'] as $role) {
        SpatieRole::firstOrCreate(['name' => $role, 'guard_name' => 'web']);
    }

    $operator = User::factory()->create([
        'first_name' => 'Pustaka',
        'last_name' => 'Utama',
        'username' => 'pustaka.utama',
    ]);
    $operator->assignRole('operator');

    $studentUser = User::factory()->create([
        'first_name' => 'Rania',
        'last_name' => 'Putri',
        'username' => 'rania.putri.test',
    ]);
    $studentUser->assignRole('student');

    $otherStudent = User::factory()->create([
        'first_name' => 'Nadia',
        'last_name' => 'Senior',
        'username' => 'nadia.senior.test',
    ]);
    $otherStudent->assignRole('student');

    $lecturer = User::factory()->create([
        'first_name' => 'Budi',
        'last_name' => 'Dosen',
        'username' => 'budi.dosen.test',
    ]);
    $lecturer->assignRole('lecturer');

    foreach (libraryPermissionNames() as $permission) {
        SpatiePermission::firstOrCreate(['name' => $permission, 'guard_name' => 'web']);
    }

    SpatieRole::where('name', 'operator')->firstOrFail()
        ->givePermissionTo(libraryPermissionNames());

    $category = BookCategory::create([
        'name' => 'Teknologi Informasi',
        'code' => 'TI',
        'description' => 'Koleksi TI',
        'is_active' => true,
    ]);

    $book = Book::create([
        'book_category_id' => $category->id,
        'title' => 'Pemrograman Laravel',
        'author' => 'Andi Nugraha',
        'publisher' => 'Informatika',
        'isbn' => '9786020000001',
        'publication_year' => 2024,
        'language' => 'Indonesia',
        'shelf_location' => 'TI-A1',
        'is_active' => true,
    ]);

    $copy = BookCopy::create([
        'book_id' => $book->id,
        'code' => 'BK-0001-01',
        'status' => BookCopyStatus::AVAILABLE->value,
        'acquired_at' => now()->toDateString(),
    ]);

    $secondCopy = BookCopy::create([
        'book_id' => $book->id,
        'code' => 'BK-0001-02',
        'status' => BookCopyStatus::AVAILABLE->value,
        'acquired_at' => now()->toDateString(),
    ]);

    return compact(
        'operator', 'studentUser', 'otherStudent', 'lecturer',
        'category', 'book', 'copy', 'secondCopy',
    );
}

/**
 * @return array<int, string>
 */
function libraryPermissionNames(): array
{
    $resources = ['book-category', 'book', 'book-loan', 'book-reservation'];
    $permissions = [];

    foreach ($resources as $resource) {
        foreach (['viewAny', 'create', 'update', 'delete', 'view'] as $action) {
            $permissions[] = "{$resource}.{$action}";
        }
    }

    return array_values(array_unique($permissions));
}

function actingLibraryAdmin(User $operator)
{
    return test()->actingAs($operator)->withSession(['active_role' => 'operator']);
}

function actingLibraryStudent(User $student)
{
    return test()->actingAs($student)->withSession(['active_role' => 'student']);
}

/**
 * @param  array<string, mixed>  $setup
 * @param  array<string, mixed>  $overrides
 */
function makeLibraryBook(array $setup, array $overrides = []): Book
{
    return Book::create([
        'book_category_id' => $setup['category']->id,
        'title' => $overrides['title'] ?? 'Buku Uji '.fake()->unique()->numerify('###'),
        'author' => 'Penulis Uji',
        'isbn' => $overrides['isbn'] ?? fake()->unique()->isbn13(),
        'language' => 'Indonesia',
        'is_active' => true,
    ]);
}

/**
 * @param  array<string, mixed>  $setup
 */
function makeLibraryLoan(array $setup, User $borrower, ?BookCopy $copy = null, ?Book $book = null): BookLoan
{
    $copy ??= BookCopy::create([
        'book_id' => ($book ?? makeLibraryBook($setup))->id,
        'code' => 'BK-UJI-'.fake()->unique()->numerify('####'),
        'status' => BookCopyStatus::AVAILABLE->value,
        'acquired_at' => now()->toDateString(),
    ]);

    return app(LibraryService::class)->borrow($borrower, $copy, $setup['operator']);
}

test('admin dapat membuka daftar kategori buku', function () {
    $setup = libraryTestSetup();

    actingLibraryAdmin($setup['operator'])
        ->get(route('admin.library.book-categories.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Admin/Library/BookCategories/Index')
            ->has('data.rows', 1)
            ->where('data.rows.0.code', 'TI')
            ->where('can.create', true)
            ->where('stats.books', 1));
});

test('admin dapat menambah kategori buku dan kode dinormalisasi', function () {
    $setup = libraryTestSetup();

    actingLibraryAdmin($setup['operator'])
        ->post(route('admin.library.book-categories.store'), [
            'name' => 'Sains dan Teknologi',
            'code' => 'sains teknologi',
            'description' => 'Koleksi sains',
            'is_active' => true,
        ])
        ->assertRedirect(route('admin.library.book-categories.index'));

    $this->assertDatabaseHas('book_categories', [
        'name' => 'Sains dan Teknologi',
        'code' => 'SAINS_TEKNOLOGI',
        'is_active' => true,
    ]);
});

test('kategori yang masih memiliki buku tidak dapat dihapus', function () {
    $setup = libraryTestSetup();

    actingLibraryAdmin($setup['operator'])
        ->delete(route('admin.library.book-categories.destroy', $setup['category']->id))
        ->assertRedirect();

    $this->assertDatabaseHas('book_categories', ['id' => $setup['category']->id, 'deleted_at' => null]);
});

test('admin dapat menambah buku beserta eksemplar otomatis', function () {
    $setup = libraryTestSetup();

    actingLibraryAdmin($setup['operator'])
        ->post(route('admin.library.books.store'), [
            'book_category_id' => $setup['category']->id,
            'title' => 'Basis Data Relasional',
            'author' => 'Siti Rahma',
            'publisher' => 'Andi Offset',
            'isbn' => '9786020000099',
            'publication_year' => 2023,
            'language' => 'Indonesia',
            'shelf_location' => 'TI-A2',
            'is_active' => true,
            'initial_copies' => 2,
        ])
        ->assertRedirect();

    $book = Book::where('isbn', '9786020000099')->firstOrFail();

    expect($book->copies()->count())->toBe(2)
        ->and($book->copies()->orderBy('code')->first()->code)->toBe('BK-'.str_pad((string) $book->id, 4, '0', STR_PAD_LEFT).'-01');
});

test('halaman detail buku menampilkan eksemplar dan riwayat', function () {
    $setup = libraryTestSetup();
    $loan = makeLibraryLoan($setup, $setup['studentUser'], $setup['copy']);

    actingLibraryAdmin($setup['operator'])
        ->get(route('admin.library.books.show', $setup['book']))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Admin/Library/Books/Show')
            ->where('book.title', 'Pemrograman Laravel')
            ->has('copies', 2)
            ->has('loans', 1)
            ->where('stats.borrowed', 1)
            ->where('can.createLoan', true));
});

test('status eksemplar yang sedang dipinjam tidak dapat diubah manual', function () {
    $setup = libraryTestSetup();
    makeLibraryLoan($setup, $setup['studentUser'], $setup['copy']);

    actingLibraryAdmin($setup['operator'])
        ->post(route('admin.library.books.copies.status', $setup['copy']), [
            'status' => BookCopyStatus::LOST->value,
        ])
        ->assertRedirect();

    expect($setup['copy']->refresh()->status)->toBe(BookCopyStatus::BORROWED->value);
});

test('peminjaman menandai eksemplar borrowed dan batas pinjam mahasiswa ditegakkan', function () {
    $setup = libraryTestSetup();

    actingLibraryAdmin($setup['operator'])
        ->post(route('admin.library.loans.store'), [
            'user_id' => $setup['studentUser']->id,
            'book_copy_id' => $setup['copy']->id,
            'borrowed_at' => now()->toDateString(),
            'due_at' => now()->addDays(14)->toDateString(),
        ])
        ->assertRedirect(route('admin.library.loans.index'));

    expect($setup['copy']->refresh()->status)->toBe(BookCopyStatus::BORROWED->value)
        ->and(BookLoan::where('user_id', $setup['studentUser']->id)->count())->toBe(1);

    foreach (range(1, 2) as $index) {
        $copy = BookCopy::create([
            'book_id' => makeLibraryBook($setup)->id,
            'code' => 'BK-LIMIT-'.$index,
            'status' => BookCopyStatus::AVAILABLE->value,
        ]);

        app(LibraryService::class)->borrow($setup['studentUser'], $copy, $setup['operator']);
    }

    $extraCopy = BookCopy::create([
        'book_id' => makeLibraryBook($setup)->id,
        'code' => 'BK-LIMIT-4',
        'status' => BookCopyStatus::AVAILABLE->value,
    ]);

    expect(fn () => app(LibraryService::class)->borrow($setup['studentUser'], $extraCopy, $setup['operator']))
        ->toThrow(ValidationException::class);
});

test('peminjaman ditolak bila anggota masih memiliki denda belum lunas', function () {
    $setup = libraryTestSetup();

    BookLoan::create([
        'book_copy_id' => $setup['copy']->id,
        'user_id' => $setup['studentUser']->id,
        'borrowed_at' => now()->subDays(20),
        'due_at' => now()->subDays(5),
        'returned_at' => now()->subDays(2),
        'status' => BookLoanStatus::RETURNED->value,
        'fine_amount' => 5000,
        'fine_paid' => false,
    ]);

    $availableCopy = BookCopy::create([
        'book_id' => makeLibraryBook($setup)->id,
        'code' => 'BK-DENDA-01',
        'status' => BookCopyStatus::AVAILABLE->value,
    ]);

    expect(fn () => app(LibraryService::class)->borrow($setup['studentUser'], $availableCopy, $setup['operator']))
        ->toThrow(ValidationException::class);
});

test('pengembalian menghitung denda dan mengubah status eksemplar', function () {
    $setup = libraryTestSetup();

    $loan = BookLoan::create([
        'book_copy_id' => $setup['copy']->id,
        'user_id' => $setup['studentUser']->id,
        'borrowed_at' => now()->subDays(20),
        'due_at' => now()->subDays(5)->startOfDay(),
        'status' => BookLoanStatus::BORROWED->value,
        'processed_by' => $setup['operator']->id,
    ]);

    $setup['copy']->update(['status' => BookCopyStatus::BORROWED->value]);

    actingLibraryAdmin($setup['operator'])
        ->post(route('admin.library.loans.return', $loan), [
            'copy_status' => BookCopyStatus::DAMAGED->value,
            'fine_paid' => true,
            'notes' => 'Sampul rusak.',
        ])
        ->assertRedirect();

    $loan->refresh();

    expect($loan->status)->toBe(BookLoanStatus::RETURNED->value)
        ->and($loan->returned_at)->not->toBeNull()
        ->and((float) $loan->fine_amount)->toBe(5000.0)
        ->and($loan->fine_paid)->toBeTrue()
        ->and($setup['copy']->refresh()->status)->toBe(BookCopyStatus::DAMAGED->value);
});

test('pengembalian eksemplar tersedia mempromosikan reservasi berikutnya', function () {
    $setup = libraryTestSetup();
    $loan = makeLibraryLoan($setup, $setup['studentUser'], $setup['copy']);

    $reservation = app(LibraryService::class)->createReservation(
        $setup['otherStudent'],
        $setup['book'],
        'Menunggu buku dikembalikan.',
    );

    app(LibraryService::class)->returnBook($loan, $setup['operator']);

    $reservation->refresh();

    expect($reservation->status)->toBe(BookReservationStatus::READY->value)
        ->and($reservation->book_copy_id)->toBe($setup['copy']->id)
        ->and($setup['copy']->refresh()->status)->toBe(BookCopyStatus::RESERVED->value)
        ->and($reservation->expires_at)->not->toBeNull();
});

test('mahasiswa dapat mengajukan reservasi dari portal dengan rencana tanggal ambil', function () {
    $setup = libraryTestSetup();

    $pickup = now()->addDays(2)->toDateString();

    actingLibraryStudent($setup['studentUser'])
        ->post(route('student.library.reservations.store'), [
            'book_id' => $setup['book']->id,
            'pickup_planned_at' => $pickup,
            'notes' => 'Untuk tugas kuliah.',
        ])
        ->assertRedirect();

    $reservation = BookReservation::query()
        ->where('book_id', $setup['book']->id)
        ->where('user_id', $setup['studentUser']->id)
        ->firstOrFail();

    expect($reservation->status)->toBe(BookReservationStatus::PENDING->value)
        ->and($reservation->pickup_planned_at?->toDateString())->toBe($pickup);
});

test('admin dapat menyiapkan dan menyerahkan reservasi menjadi peminjaman', function () {
    $setup = libraryTestSetup();

    $reservation = app(LibraryService::class)->createReservation($setup['studentUser'], $setup['book']);

    actingLibraryAdmin($setup['operator'])
        ->post(route('admin.library.reservations.ready', $reservation), [
            'book_copy_id' => $setup['copy']->id,
        ])
        ->assertRedirect();

    $reservation->refresh();

    expect($reservation->status)->toBe(BookReservationStatus::READY->value)
        ->and($setup['copy']->refresh()->status)->toBe(BookCopyStatus::RESERVED->value);

    actingLibraryAdmin($setup['operator'])
        ->post(route('admin.library.reservations.fulfill', $reservation))
        ->assertRedirect();

    $reservation->refresh();

    $loan = BookLoan::where('user_id', $setup['studentUser']->id)
        ->where('book_copy_id', $setup['copy']->id)
        ->firstOrFail();

    expect($reservation->status)->toBe(BookReservationStatus::FULFILLED->value)
        ->and($reservation->book_loan_id)->toBe($loan->id)
        ->and($setup['copy']->refresh()->status)->toBe(BookCopyStatus::BORROWED->value);
});

test('admin dapat membuka daftar reservasi dengan antrean dan rencana ambil', function () {
    $setup = libraryTestSetup();

    app(LibraryService::class)->createReservation(
        $setup['studentUser'],
        $setup['book'],
        'Referensi tugas.',
        now()->addDays(2),
    );

    actingLibraryAdmin($setup['operator'])
        ->get(route('admin.library.reservations.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Admin/Library/Reservations/Index')
            ->has('data.rows', 1)
            ->where('data.rows.0.member', 'Rania Putri')
            ->where('data.rows.0.queuePosition', 1)
            ->where('data.rows.0.pickupPlannedAt', now()->addDays(2)->format('d M Y'))
            ->where('data.rows.0.canReady', true));
});

test('mahasiswa dapat membatalkan reservasinya sendiri tetapi tidak reservasi orang lain', function () {
    $setup = libraryTestSetup();

    $reservation = app(LibraryService::class)->createReservation($setup['studentUser'], $setup['book']);

    actingLibraryStudent($setup['otherStudent'])
        ->post(route('student.library.reservations.cancel', $reservation))
        ->assertForbidden();

    actingLibraryStudent($setup['studentUser'])
        ->post(route('student.library.reservations.cancel', $reservation))
        ->assertRedirect();

    expect($reservation->refresh()->status)->toBe(BookReservationStatus::CANCELLED->value);
});

test('reservasi kedaluwarsa mengembalikan eksemplar menjadi tersedia', function () {
    $setup = libraryTestSetup();

    $reservation = app(LibraryService::class)->createReservation($setup['studentUser'], $setup['book']);
    app(LibraryService::class)->markReservationReady($reservation, $setup['copy'], $setup['operator']);

    $reservation->update(['expires_at' => now()->subDay()]);

    $expired = app(LibraryService::class)->expireReservations();

    expect($expired)->toBe(1)
        ->and($reservation->refresh()->status)->toBe(BookReservationStatus::EXPIRED->value)
        ->and($reservation->expired_at)->not->toBeNull()
        ->and($setup['copy']->refresh()->status)->toBe(BookCopyStatus::AVAILABLE->value);
});

test('portal mahasiswa menampilkan katalog dan pinjaman', function () {
    $setup = libraryTestSetup();
    makeLibraryLoan($setup, $setup['studentUser'], $setup['copy']);

    actingLibraryStudent($setup['studentUser'])
        ->get(route('student.library.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Student/Library/Index')
            ->where('stats.activeLoans', 1)
            ->has('books.rows', 1)
            ->has('myLoans', 1)
            ->where('options.loanDays', 14)
            ->where('options.finePerDay', 1000));
});

test('admin tanpa permission perpustakaan ditolak', function () {
    $setup = libraryTestSetup();

    actingLibraryStudent($setup['studentUser'])
        ->get(route('admin.library.books.index'))
        ->assertForbidden();
});

test('denda belum lunas dapat ditandai lunas oleh admin', function () {
    $setup = libraryTestSetup();

    $loan = BookLoan::create([
        'book_copy_id' => $setup['copy']->id,
        'user_id' => $setup['studentUser']->id,
        'borrowed_at' => now()->subDays(10),
        'due_at' => now()->subDays(3),
        'returned_at' => now()->subDay(),
        'status' => BookLoanStatus::RETURNED->value,
        'fine_amount' => 3000,
        'fine_paid' => false,
    ]);

    actingLibraryAdmin($setup['operator'])
        ->post(route('admin.library.loans.pay-fine', $loan))
        ->assertRedirect();

    expect($loan->refresh()->fine_paid)->toBeTrue()
        ->and($loan->fine_paid_at)->not->toBeNull();
});

test('peminjaman terlambat otomatis ditandai overdue saat daftar dibuka', function () {
    $setup = libraryTestSetup();

    $loan = BookLoan::create([
        'book_copy_id' => $setup['copy']->id,
        'user_id' => $setup['studentUser']->id,
        'borrowed_at' => now()->subDays(20),
        'due_at' => now()->subDays(2),
        'status' => BookLoanStatus::BORROWED->value,
    ]);

    $setup['copy']->update(['status' => BookCopyStatus::BORROWED->value]);

    actingLibraryAdmin($setup['operator'])
        ->get(route('admin.library.loans.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Admin/Library/Loans/Index')
            ->where('stats.overdue', 1));

    expect($loan->refresh()->status)->toBe(BookLoanStatus::OVERDUE->value);
});

test('mahasiswa dapat membuka halaman detail buku dari portal', function () {
    $setup = libraryTestSetup();
    makeLibraryLoan($setup, $setup['otherStudent'], $setup['copy']);

    $reservation = app(LibraryService::class)->createReservation(
        $setup['studentUser'],
        $setup['book'],
        'Referensi tugas besar.',
        now()->addDays(2),
    );

    actingLibraryStudent($setup['studentUser'])
        ->get(route('student.library.books.show', $setup['book']))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Student/Library/Show')
            ->where('book.title', 'Pemrograman Laravel')
            ->where('book.author', 'Andi Nugraha')
            ->has('copies', 2)
            ->where('stats.borrowed', 1)
            ->where('stats.queue', 1)
            ->where('myReservation.id', $reservation->id)
            ->where('myReservation.queuePosition', 1)
            ->where('myReservation.pickupPlannedAt', now()->addDays(2)->format('d M Y'))
            ->where('myLoan', null));
});

test('posisi antrean reservasi mengikuti urutan pengajuan', function () {
    $setup = libraryTestSetup();

    $first = app(LibraryService::class)->createReservation($setup['studentUser'], $setup['book']);
    $second = app(LibraryService::class)->createReservation($setup['otherStudent'], $setup['book']);

    $service = app(LibraryService::class);

    expect($service->queuePosition($first))->toBe(1)
        ->and($service->queuePosition($second))->toBe(2)
        ->and($service->queueSize($setup['book']))->toBe(2);
});

test('reservasi siap diambil tidak lagi dihitung dalam antrean', function () {
    $setup = libraryTestSetup();

    $reservation = app(LibraryService::class)->createReservation($setup['studentUser'], $setup['book']);
    app(LibraryService::class)->markReservationReady($reservation, $setup['copy'], $setup['operator']);

    $service = app(LibraryService::class);

    expect($service->queuePosition($reservation->refresh()))->toBe(0)
        ->and($service->queueSize($setup['book']))->toBe(0);
});

test('rencana tanggal ambil di luar batas ditolak', function () {
    $setup = libraryTestSetup();

    actingLibraryStudent($setup['studentUser'])
        ->post(route('student.library.reservations.store'), [
            'book_id' => $setup['book']->id,
            'pickup_planned_at' => now()->addDays((int) config('library.pickup_plan_days', 7) + 1)->toDateString(),
        ])
        ->assertSessionHasErrors('pickup_planned_at');

    $this->assertDatabaseCount('book_reservations', 0);
});

test('mahasiswa dapat memperpanjang peminjaman aktifnya', function () {
    $setup = libraryTestSetup();
    $loan = makeLibraryLoan($setup, $setup['studentUser'], $setup['copy']);
    $originalDue = $loan->due_at->copy();
    $renewalDays = (int) config('library.renewal_days', 14);

    actingLibraryStudent($setup['studentUser'])
        ->post(route('student.library.loans.renew', $loan))
        ->assertRedirect();

    $loan->refresh();

    expect($loan->renewal_count)->toBe(1)
        ->and($loan->renewed_at)->not->toBeNull()
        // Kolom DATETIME menyimpan presisi detik; bandingkan tanpa mikrodetik.
        ->and($loan->due_at->toDateTimeString())
        ->toBe($originalDue->copy()->addDays($renewalDays)->endOfDay()->toDateTimeString());
});

test('perpanjangan ditolak bila buku sedang direservasi anggota lain', function () {
    $setup = libraryTestSetup();
    $loan = makeLibraryLoan($setup, $setup['studentUser'], $setup['copy']);

    app(LibraryService::class)->createReservation($setup['otherStudent'], $setup['book']);

    actingLibraryStudent($setup['studentUser'])
        ->post(route('student.library.loans.renew', $loan))
        ->assertSessionHasErrors('loan');

    expect($loan->refresh()->renewal_count)->toBe(0);
});

test('perpanjangan ditolak setelah batas maksimal tercapai', function () {
    $setup = libraryTestSetup();
    $loan = makeLibraryLoan($setup, $setup['studentUser'], $setup['copy']);

    $loan->update(['renewal_count' => (int) config('library.max_renewals', 1)]);

    actingLibraryStudent($setup['studentUser'])
        ->post(route('student.library.loans.renew', $loan))
        ->assertSessionHasErrors('loan');

    expect($loan->refresh()->renewal_count)->toBe((int) config('library.max_renewals', 1));
});

test('mahasiswa tidak dapat memperpanjang peminjaman milik anggota lain', function () {
    $setup = libraryTestSetup();
    $loan = makeLibraryLoan($setup, $setup['studentUser'], $setup['copy']);

    actingLibraryStudent($setup['otherStudent'])
        ->post(route('student.library.loans.renew', $loan))
        ->assertForbidden();
});

test('admin dapat memperpanjang peminjaman anggota', function () {
    $setup = libraryTestSetup();
    $loan = makeLibraryLoan($setup, $setup['studentUser'], $setup['copy']);

    actingLibraryAdmin($setup['operator'])
        ->post(route('admin.library.loans.renew', $loan))
        ->assertRedirect();

    expect($loan->refresh()->renewal_count)->toBe(1);
});

test('role pustakawan hanya menerima izin perpustakaan dari sync permissions', function () {
    $this->artisan('permissions:sync')->assertSuccessful();

    $role = SpatieRole::findByName('librarian', 'web');
    $permissionNames = $role->permissions()->pluck('name')->all();

    expect($permissionNames)
        ->toContain('book.viewAny', 'book-loan.viewAny', 'book-reservation.viewAny')
        ->not->toContain('user.viewAny')
        ->not->toContain('student-invoice.viewAny');
});

test('pustakawan dapat mengakses modul perpustakaan tetapi tidak modul lain', function () {
    $setup = libraryTestSetup();

    $librarianRole = SpatieRole::firstOrCreate(['name' => 'librarian', 'guard_name' => 'web']);
    $librarianRole->syncPermissions(libraryPermissionNames());

    $librarian = User::factory()->create([
        'first_name' => 'Pustaka',
        'last_name' => 'Wan',
        'username' => 'pustaka.wan.test',
    ]);
    $librarian->assignRole('librarian');

    test()->actingAs($librarian)->withSession(['active_role' => 'librarian'])
        ->get(route('admin.library.books.index'))
        ->assertOk();

    test()->actingAs($librarian)->withSession(['active_role' => 'librarian'])
        ->get(route('admin.access.users.index'))
        ->assertForbidden();
});

test('dashboard pustakawan menampilkan section perpustakaan', function () {
    $setup = libraryTestSetup();

    $librarianRole = SpatieRole::firstOrCreate(['name' => 'librarian', 'guard_name' => 'web']);
    $librarianRole->syncPermissions(libraryPermissionNames());

    $librarian = User::factory()->create([
        'first_name' => 'Pustaka',
        'last_name' => 'Wan',
        'username' => 'pustaka.dashboard.test',
    ]);
    $librarian->assignRole('librarian');

    test()->actingAs($librarian)->withSession(['active_role' => 'librarian'])
        ->get('/admin/dashboard')
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Admin/Dashboard')
            ->has('sections', 1)
            ->where('sections.0.key', 'library')
            ->where('sections.0.quickLinks.0.label', 'Peminjaman'));
});
