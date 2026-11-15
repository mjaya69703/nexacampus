<?php

namespace App\Console\Commands;

use App\Enums\BookReservationStatus;
use App\Models\Library\BookLoan;
use App\Models\Library\BookReservation;
use App\Support\Library\LibraryNotificationService;
use App\Support\Library\LibraryService;
use Illuminate\Console\Command;

class SendLibraryDueReminders extends Command
{
    protected $signature = 'library:send-due-reminders {--dry-run : Hitung pengingat tanpa mengirim notifikasi}';

    protected $description = 'Kirim pengingat jatuh tempo, peringatan keterlambatan, dan notifikasi reservasi siap diambil.';

    public function handle(LibraryService $service, LibraryNotificationService $notifier): int
    {
        $dryRun = (bool) $this->option('dry-run');

        $service->refreshOverdueLoans();
        $service->expireReservations();

        $reminderDays = collect(config('library.reminder_days', [3, 1]))
            ->map(fn ($day) => (int) $day)
            ->filter(fn (int $day) => $day > 0)
            ->unique()
            ->sortDesc()
            ->values()
            ->all();

        $dueSent = 0;
        $overdueSent = 0;
        $reservationSent = 0;

        if ($reminderDays !== []) {
            $maxDays = max($reminderDays);

            BookLoan::query()
                ->active()
                ->with(['copy.book', 'user'])
                ->whereNotNull('due_at')
                ->whereBetween('due_at', [now()->startOfDay(), now()->addDays($maxDays)->endOfDay()])
                ->chunkById(100, function ($loans) use ($reminderDays, $dryRun, $notifier, &$dueSent): void {
                    foreach ($loans as $loan) {
                        $daysLeft = (int) now()->startOfDay()->diffInDays($loan->due_at->copy()->startOfDay(), false);

                        if (! in_array($daysLeft, $reminderDays, true)) {
                            continue;
                        }

                        if (! $dryRun && $notifier->loanDueSoon($loan, $daysLeft)) {
                            $dueSent++;
                        }

                        if ($dryRun) {
                            $dueSent++;
                        }
                    }
                });
        }

        $overdueInterval = max(1, (int) config('library.overdue_reminder_days', 3));

        BookLoan::query()
            ->active()
            ->with(['copy.book', 'user'])
            ->where('due_at', '<', now())
            ->where(function ($query) use ($overdueInterval) {
                $query->whereNull('overdue_notified_at')
                    ->orWhere('overdue_notified_at', '<', now()->subDays($overdueInterval));
            })
            ->chunkById(100, function ($loans) use ($dryRun, $notifier, &$overdueSent): void {
                foreach ($loans as $loan) {
                    if ($dryRun || $notifier->loanOverdue($loan)) {
                        $overdueSent++;
                    }
                }
            });

        BookReservation::query()
            ->where('status', BookReservationStatus::READY->value)
            ->whereNull('ready_notified_at')
            ->with(['book', 'copy', 'user'])
            ->chunkById(100, function ($reservations) use ($dryRun, $notifier, &$reservationSent): void {
                foreach ($reservations as $reservation) {
                    if ($dryRun || $notifier->reservationReady($reservation)) {
                        $reservationSent++;
                    }
                }
            });

        $this->info(sprintf(
            '%sPengingat jatuh tempo: %d, peringatan terlambat: %d, reservasi siap: %d.',
            $dryRun ? '[dry-run] ' : '',
            $dueSent,
            $overdueSent,
            $reservationSent,
        ));

        return self::SUCCESS;
    }
}
