<?php

namespace App\Support\Library;

use App\Mail\Library\LibraryNotificationMail;
use App\Models\Library\BookLoan;
use App\Models\Library\BookReservation;
use App\Models\User;
use App\Support\Notifications\NotificationDispatchService;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Throwable;

/**
 * Notifikasi sirkulasi perpustakaan: pengingat jatuh tempo, peringatan
 * keterlambatan, reservasi siap diambil, dan status pembayaran denda.
 *
 * Email dikirim langsung, sedangkan WhatsApp & web push didelegasikan ke
 * NotificationDispatchService agar mengikuti pengaturan kanal global.
 */
class LibraryNotificationService
{
    public function loanDueSoon(BookLoan $loan, int $daysLeft): bool
    {
        $column = $this->dueReminderColumn($daysLeft);

        if (! $column || $loan->{$column}) {
            return false;
        }

        $loan->loadMissing(['copy.book', 'user']);

        $user = $loan->user;

        if (! $user) {
            return false;
        }

        $bookTitle = $loan->copy?->book?->title ?? 'Buku perpustakaan';
        $dueDate = $loan->due_at?->format('d M Y') ?? '-';
        $finePerDay = $this->money((int) config('library.fine_per_day', 1000));
        $daysLabel = $daysLeft === 1 ? 'besok' : $daysLeft.' hari lagi';

        $this->send(
            $user,
            'library.loan_due_soon',
            [
                'student_name' => $user->name,
                'book_title' => $bookTitle,
                'due_date' => $dueDate,
                'days_left' => (string) $daysLeft,
                'fine_per_day' => $finePerDay,
                'url' => route('student.library.index'),
            ],
            $loan,
            'Pengingat jatuh tempo: '.$bookTitle,
            'Jatuh tempo '.$daysLabel,
            'Halo '.$user->name.', peminjaman buku "'.$bookTitle.'" akan jatuh tempo pada '.$dueDate.'. '
                .'Kembalikan atau perpanjang melalui portal perpustakaan sebelum tanggal tersebut.',
            [
                ['label' => 'Buku', 'value' => $bookTitle],
                ['label' => 'Jatuh tempo', 'value' => $dueDate],
                ['label' => 'Sisa waktu', 'value' => $daysLeft === 1 ? '1 hari' : $daysLeft.' hari'],
                ['label' => 'Denda keterlambatan', 'value' => $finePerDay.'/hari'],
            ],
        );

        $loan->forceFill([$column => now()])->save();

        return true;
    }

    public function loanOverdue(BookLoan $loan): bool
    {
        $interval = max(1, (int) config('library.overdue_reminder_days', 3));

        if ($loan->overdue_notified_at && $loan->overdue_notified_at->greaterThan(now()->subDays($interval))) {
            return false;
        }

        $loan->loadMissing(['copy.book', 'user']);

        $user = $loan->user;

        if (! $user) {
            return false;
        }

        $bookTitle = $loan->copy?->book?->title ?? 'Buku perpustakaan';
        $dueDate = $loan->due_at?->format('d M Y') ?? '-';
        $daysLate = $loan->daysLate();
        $fineAmount = $this->money($daysLate * (int) config('library.fine_per_day', 1000));

        $this->send(
            $user,
            'library.loan_overdue',
            [
                'student_name' => $user->name,
                'book_title' => $bookTitle,
                'due_date' => $dueDate,
                'days_late' => (string) $daysLate,
                'fine_amount' => $fineAmount,
                'url' => route('student.library.index'),
            ],
            $loan,
            'Peminjaman terlambat: '.$bookTitle,
            'Peminjaman melewati jatuh tempo',
            'Halo '.$user->name.', peminjaman buku "'.$bookTitle.'" sudah melewati jatuh tempo '.$daysLate.' hari. '
                .'Segera kembalikan buku dan selesaikan denda melalui portal perpustakaan.',
            [
                ['label' => 'Buku', 'value' => $bookTitle],
                ['label' => 'Jatuh tempo', 'value' => $dueDate],
                ['label' => 'Keterlambatan', 'value' => $daysLate.' hari'],
                ['label' => 'Denda berjalan', 'value' => $fineAmount],
            ],
        );

        $loan->forceFill(['overdue_notified_at' => now()])->save();

        return true;
    }

    public function reservationReady(BookReservation $reservation): bool
    {
        if ($reservation->ready_notified_at) {
            return false;
        }

        $reservation->loadMissing(['book', 'copy', 'user']);

        $user = $reservation->user;

        if (! $user) {
            return false;
        }

        $bookTitle = $reservation->book?->title ?? 'Buku perpustakaan';
        $copyCode = $reservation->copy?->code ?? '-';
        $expiresAt = $reservation->expires_at?->format('d M Y H:i') ?? '-';
        $holdDays = (int) config('library.reservation_hold_days', 3);

        $this->send(
            $user,
            'library.reservation_ready',
            [
                'student_name' => $user->name,
                'book_title' => $bookTitle,
                'copy_code' => $copyCode,
                'expires_at' => $expiresAt,
                'url' => route('student.library.index'),
            ],
            $reservation,
            'Reservasi siap diambil: '.$bookTitle,
            'Reservasi siap diambil',
            'Halo '.$user->name.', reservasi buku "'.$bookTitle.'" sudah siap diambil di perpustakaan. '
                .'Eksemplar ditahan maksimal '.$holdDays.' hari sampai '.$expiresAt.'.',
            [
                ['label' => 'Buku', 'value' => $bookTitle],
                ['label' => 'Kode eksemplar', 'value' => $copyCode],
                ['label' => 'Batas pengambilan', 'value' => $expiresAt],
                ['label' => 'Masa tahan', 'value' => $holdDays.' hari'],
            ],
        );

        $reservation->forceFill(['ready_notified_at' => now()])->save();

        return true;
    }

    public function fineSubmitted(BookLoan $loan): bool
    {
        $loan->loadMissing(['copy.book', 'user']);

        $user = $loan->user;

        if (! $user) {
            return false;
        }

        $bookTitle = $loan->copy?->book?->title ?? 'Buku perpustakaan';
        $fineAmount = $this->money($loan->fine_amount);

        $this->send(
            $user,
            'library.fine_submitted',
            [
                'student_name' => $user->name,
                'book_title' => $bookTitle,
                'fine_amount' => $fineAmount,
                'url' => route('student.library.index'),
            ],
            $loan,
            'Bukti pembayaran denda diterima',
            'Bukti pembayaran denda diterima',
            'Halo '.$user->name.', bukti pembayaran denda untuk buku "'.$bookTitle.'" sudah kami terima. '
                .'Pustakawan akan memverifikasi bukti tersebut dan statusnya dapat dipantau di portal perpustakaan.',
            [
                ['label' => 'Buku', 'value' => $bookTitle],
                ['label' => 'Nominal denda', 'value' => $fineAmount],
                ['label' => 'Status', 'value' => 'Menunggu verifikasi'],
            ],
        );

        return true;
    }

    public function fineVerified(BookLoan $loan): bool
    {
        $loan->loadMissing(['copy.book', 'user']);

        $user = $loan->user;

        if (! $user) {
            return false;
        }

        $bookTitle = $loan->copy?->book?->title ?? 'Buku perpustakaan';
        $fineAmount = $this->money($loan->fine_amount);

        $this->send(
            $user,
            'library.fine_verified',
            [
                'student_name' => $user->name,
                'book_title' => $bookTitle,
                'fine_amount' => $fineAmount,
                'url' => route('student.library.index'),
            ],
            $loan,
            'Pembayaran denda diverifikasi',
            'Pembayaran denda diverifikasi',
            'Halo '.$user->name.', pembayaran denda untuk buku "'.$bookTitle.'" sudah diverifikasi. '
                .'Tidak ada lagi denda yang menghalangi peminjaman berikutnya.',
            [
                ['label' => 'Buku', 'value' => $bookTitle],
                ['label' => 'Nominal denda', 'value' => $fineAmount],
                ['label' => 'Status', 'value' => 'Lunas'],
            ],
        );

        return true;
    }

    public function fineRejected(BookLoan $loan): bool
    {
        $loan->loadMissing(['copy.book', 'user']);

        $user = $loan->user;

        if (! $user) {
            return false;
        }

        $bookTitle = $loan->copy?->book?->title ?? 'Buku perpustakaan';
        $fineAmount = $this->money($loan->fine_amount);
        $reason = $loan->fine_rejection_reason ?: '-';

        $this->send(
            $user,
            'library.fine_rejected',
            [
                'student_name' => $user->name,
                'book_title' => $bookTitle,
                'fine_amount' => $fineAmount,
                'reason' => $reason,
                'url' => route('student.library.index'),
            ],
            $loan,
            'Bukti pembayaran denda ditolak',
            'Bukti pembayaran denda ditolak',
            'Halo '.$user->name.', bukti pembayaran denda untuk buku "'.$bookTitle.'" belum dapat diverifikasi. '
                .'Silakan periksa alasan penolakan dan unggah ulang bukti pembayaran.',
            [
                ['label' => 'Buku', 'value' => $bookTitle],
                ['label' => 'Nominal denda', 'value' => $fineAmount],
                ['label' => 'Alasan penolakan', 'value' => $reason],
            ],
        );

        return true;
    }

    public function dueReminderColumn(int $daysLeft): ?string
    {
        return match ($daysLeft) {
            3 => 'due_reminder_h3_sent_at',
            1 => 'due_reminder_h1_sent_at',
            default => null,
        };
    }

    /**
     * @param  array<string, mixed>  $data
     * @param  array<int, array{label: string, value: string}>  $details
     */
    private function send(
        User $user,
        string $eventKey,
        array $data,
        Model $source,
        string $subjectLine,
        string $heading,
        string $intro,
        array $details,
    ): void {
        if (filled($user->email)) {
            try {
                Mail::to($user->email)->send(new LibraryNotificationMail(
                    subjectLine: $subjectLine,
                    heading: $heading,
                    intro: $intro,
                    details: $details,
                    actionUrl: $data['url'] ?? null,
                ));
            } catch (Throwable $exception) {
                Log::warning('Library email notification failed.', [
                    'email' => $user->email,
                    'event_key' => $eventKey,
                    'message' => $exception->getMessage(),
                ]);
            }
        }

        try {
            app(NotificationDispatchService::class)->dispatch($user, $eventKey, $data, $source);
        } catch (Throwable $exception) {
            Log::warning('Library WhatsApp/web push notification failed.', [
                'user_id' => $user->id,
                'event_key' => $eventKey,
                'message' => $exception->getMessage(),
            ]);
        }
    }

    private function money(mixed $amount): string
    {
        return 'Rp '.number_format((float) $amount, 0, ',', '.');
    }
}
