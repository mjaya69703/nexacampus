<?php

namespace Database\Seeders;

use App\Enums\ThesisDefenseStatus;
use App\Enums\ThesisMilestoneStatus;
use App\Enums\ThesisStatus;
use App\Models\Academic\AcademicYear;
use App\Models\Academic\LecturerProfile;
use App\Models\Academic\StudentProfile;
use App\Models\Academic\Thesis;
use App\Models\User;
use App\Support\Academic\ThesisService;
use Illuminate\Database\Seeder;

/**
 * Data demo Skripsi/Tugas Akhir untuk mahasiswa senior (nadia.senior).
 *
 * Kondisi yang disiapkan:
 * - proposal disetujui dengan dua pembimbing;
 * - dua milestone disetujui, satu menunggu review dosen, dua belum dikerjakan;
 * - satu sidang proposal terjadwal agar portal mahasiswa dan dosen punya contoh.
 */
class ThesisDemoSeeder extends Seeder
{
    public function run(): void
    {
        $student = StudentProfile::query()
            ->whereHas('user', fn ($query) => $query->where('username', 'nadia.senior'))
            ->first();

        if (! $student) {
            $this->command?->warn('ThesisDemoSeeder dilewati: profil mahasiswa nadia.senior belum tersedia.');

            return;
        }

        $admin = User::query()->where('username', 'superuser')->first();
        $year = AcademicYear::query()->where('is_active', true)->latest('start_date')->first();

        if (! $admin || ! $year) {
            $this->command?->warn('ThesisDemoSeeder dilewati: superuser atau tahun akademik aktif belum tersedia.');

            return;
        }

        $primary = $this->lecturerByEmail('lecturer@example.com');
        $secondary = $this->lecturerByEmail('lecturer.data@example.com');

        if (! $primary || ! $secondary) {
            $this->command?->warn('ThesisDemoSeeder dilewati: dosen demo belum tersedia.');

            return;
        }

        $service = app(ThesisService::class);

        $thesis = Thesis::query()
            ->where('student_profile_id', $student->id)
            ->whereNotIn('status', [ThesisStatus::CANCELLED->value])
            ->latest('id')
            ->first();

        if (! $thesis) {
            $thesis = $service->createProposal($student, [
                'academic_year_id' => $year->id,
                'type' => Thesis::TYPE_SKRIPSI,
                'title' => 'Penerapan Metode Design Thinking pada Sistem Monitoring Tugas Akhir',
                'topic' => 'Rekayasa Perangkat Lunak',
                'abstract' => 'Penelitian ini merancang sistem monitoring tugas akhir menggunakan pendekatan design thinking untuk meningkatkan transparansi proses bimbingan.',
                'keywords' => 'design thinking, monitoring, tugas akhir',
            ], $admin);

            $service->approve($thesis, $admin, [$primary->id, $secondary->id], 'Lanjutkan bimbingan sesuai jadwal.');
        }

        $milestones = $thesis->milestones()->orderBy('sequence')->get();

        foreach ($milestones as $milestone) {
            if ($milestone->status === ThesisMilestoneStatus::APPROVED->value) {
                continue;
            }

            if ($milestone->sequence <= 2) {
                $service->reviewMilestone(
                    $milestone,
                    $primary->user ?? $admin,
                    ThesisMilestoneStatus::APPROVED->value,
                    'Progres sesuai target, lanjutkan ke tahap berikutnya.',
                );

                continue;
            }

            if ($milestone->sequence === 3 && $milestone->status === ThesisMilestoneStatus::PENDING->value) {
                $service->submitMilestone($milestone, $student->user, 'Draf Bab IV dan V sudah siap untuk direview.');

                continue;
            }

            break;
        }

        $thesis->refresh();

        if ($thesis->status === ThesisStatus::IN_PROGRESS->value) {
            $defenseExists = $thesis->defenses()
                ->where('type', 'proposal')
                ->where('status', ThesisDefenseStatus::SCHEDULED->value)
                ->exists();

            if (! $defenseExists) {
                $service->scheduleDefense($thesis, [
                    'type' => 'proposal',
                    'scheduled_at' => now()->addDays(5)->setTime(9, 0),
                    'duration_minutes' => 90,
                    'room' => 'Ruang Seminar 401',
                    'notes' => 'Sidang proposal demo.',
                    'examiners' => [
                        ['lecturer_profile_id' => $primary->id, 'role' => 'chair'],
                        ['lecturer_profile_id' => $secondary->id, 'role' => 'examiner'],
                    ],
                ], $admin);
            }
        }

        $this->command?->info('Skripsi demo siap untuk mahasiswa nadia.senior.');
    }

    private function lecturerByEmail(string $email): ?LecturerProfile
    {
        return LecturerProfile::query()
            ->whereHas('user', fn ($query) => $query->where('email', $email))
            ->first();
    }
}
