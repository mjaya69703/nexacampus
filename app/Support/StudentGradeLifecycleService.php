<?php

namespace App\Support;

use App\Models\Academic\StudentGrade;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Satu pintu untuk semua mutasi nilai mahasiswa.
 *
 * Menutup 5 cacat alur lama:
 *  1. Nilai Published bisa diubah diam-diam (komponen/header) tanpa
 *     resync transkrip — sekarang dikunci, koreksi lewat unpublish
 *     (Published → Finalized) atau workflow keberatan nilai.
 *  2. Finalisasi lolos dengan skor kosong (null dihitung 0 → E diam-diam)
 *     atau tanpa dosen penilai — sekarang wajib komponen lengkap + penilai.
 *  3. Ganti KRS (study_plan_detail) padahal komponen sudah ada — sekarang
 *     dikunci kecuali masih Draft dan belum ada komponen.
 *  4. Hapus / turun-status tidak resync transkrip (KHS basi) — sekarang
 *     selalu resync mahasiswa terkait.
 *  5. graded_at dicap setiap recalc Draft — sekarang hanya saat finalisasi.
 */
class StudentGradeLifecycleService
{
    public function __construct(
        protected StudentGradeCalculator $calculator = new StudentGradeCalculator,
        protected TranscriptSyncService $transcripts = new TranscriptSyncService,
    ) {}

    /**
     * @throws ValidationException saat nilai sudah Published.
     */
    public function assertMutable(StudentGrade $grade): void
    {
        if ($grade->grade_status === 'Published') {
            throw ValidationException::withMessages([
                'grade_status' => 'Nilai sudah Published dan dikunci. Batalkan publish dulu (kembali ke Finalized) sebelum mengubah.',
            ]);
        }
    }

    /**
     * Hitung ulang snapshot tanpa menyentuh graded_at / lifecycle.
     */
    public function recalculate(StudentGrade $grade): StudentGrade
    {
        $grade->loadMissing('components');
        $grade->update($this->calculator->buildSnapshot($grade));

        return $grade->refresh();
    }

    /**
     * Finalisasi: bobot 100% + semua skor terisi + penilai wajib.
     *
     * @throws ValidationException
     */
    public function finalize(StudentGrade $grade, ?int $graderUserId = null, ?int $actorId = null): StudentGrade
    {
        $this->assertMutable($grade);
        $grade->loadMissing('components');

        $components = $grade->components;

        if ($components->isEmpty()) {
            throw ValidationException::withMessages([
                'components' => 'Tambahkan minimal satu komponen nilai sebelum finalisasi.',
            ]);
        }

        $missingScores = $components
            ->filter(fn ($component) => $component->score === null)
            ->values();

        if ($missingScores->isNotEmpty()) {
            $names = $missingScores->map(fn ($component) => $component->name)->implode(', ');
            throw ValidationException::withMessages([
                'components' => "Skor masih kosong pada: {$names}. Finalisasi butuh semua skor terisi agar tidak tercatat 0 diam-diam.",
            ]);
        }

        if (abs($this->calculator->calculateTotalWeight($grade) - 100.0) > 0.0001) {
            throw ValidationException::withMessages([
                'components' => 'Total bobot komponen harus tepat 100% untuk finalisasi nilai.',
            ]);
        }

        $graderUserId ??= $grade->graded_by;

        if (empty($graderUserId)) {
            throw ValidationException::withMessages([
                'graded_by' => 'Dosen penilai wajib diisi sebelum finalisasi.',
            ]);
        }

        return DB::transaction(function () use ($grade, $graderUserId, $actorId) {
            $snapshot = $this->calculator->buildSnapshot($grade);

            $grade->update(array_merge($snapshot, [
                'grade_status' => 'Finalized',
                'graded_at' => now(),
                'graded_by' => $graderUserId,
                'updated_by' => $actorId,
            ]));

            $this->resyncStudent($grade->refresh());

            return $grade;
        });
    }

    /**
     * Batalkan publish: Published → Finalized + resync (nilai hilang dari
     * transkrip mahasiswa sampai dipublish ulang).
     *
     * @throws ValidationException
     */
    public function unpublish(StudentGrade $grade, ?int $actorId = null): StudentGrade
    {
        if ($grade->grade_status !== 'Published') {
            throw ValidationException::withMessages([
                'grade_status' => 'Hanya nilai Published yang bisa dibatalkan publish-nya.',
            ]);
        }

        return DB::transaction(function () use ($grade, $actorId) {
            $grade->update([
                'grade_status' => 'Finalized',
                'updated_by' => $actorId,
            ]);

            $this->resyncStudent($grade->refresh());

            return $grade;
        });
    }

    /**
     * Hapus permanen (paritas: tanpa sampah) + resync agar KHS/transkrip
     * tidak basi.
     */
    public function deleteGrade(StudentGrade $grade, ?int $actorId = null): array
    {
        $grade->loadMissing('studyPlanDetail.studyPlan');
        $studentProfileId = (int) ($grade->studyPlanDetail?->studyPlan?->student_profile_id ?? 0);
        $academicYearId = (int) ($grade->studyPlanDetail?->studyPlan?->academic_year_id ?? 0);

        return DB::transaction(function () use ($grade, $actorId, $studentProfileId, $academicYearId) {
            if ($actorId) {
                $grade->update(['deleted_by' => $actorId]);
            }
            $grade->delete();

            if ($studentProfileId > 0) {
                $this->transcripts->syncStudent($studentProfileId, $academicYearId > 0 ? $academicYearId : null);
            }

            return ['student_profile_id' => $studentProfileId];
        });
    }

    /**
     * Resync mahasiswa pemilik nilai ini (dipakai setelah demote ke Draft,
     * hapus komponen terakhir, dsb).
     */
    public function resyncStudent(StudentGrade $grade): void
    {
        $grade->loadMissing('studyPlanDetail.studyPlan');
        $studentProfileId = (int) ($grade->studyPlanDetail?->studyPlan?->student_profile_id ?? 0);
        $academicYearId = (int) ($grade->studyPlanDetail?->studyPlan?->academic_year_id ?? 0);

        if ($studentProfileId > 0) {
            $this->transcripts->syncStudent($studentProfileId, $academicYearId > 0 ? $academicYearId : null);
        }
    }
}
