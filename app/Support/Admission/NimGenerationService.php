<?php

namespace App\Support\Admission;

use App\Models\Academic\StudentProfile;
use App\Models\Admission\AdmissionApplication;
use App\Models\Admission\NimGenerationRule;
use App\Models\Admission\NimSequenceCounter;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class NimGenerationService
{
    public function activeRule(): ?NimGenerationRule
    {
        return NimGenerationRule::query()
            ->where('is_active', true)
            ->latest('id')
            ->first();
    }

    public function preview(AdmissionApplication $application, ?NimGenerationRule $rule = null): string
    {
        $rule ??= $this->activeRule();

        if (! $rule) {
            throw new RuntimeException('Belum ada NIM generation rule yang aktif.');
        }

        $scopeKey = $this->scopeKey($application, $rule);
        $counter = NimSequenceCounter::query()
            ->where('nim_generation_rule_id', $rule->id)
            ->where('scope_key', $scopeKey)
            ->first();

        $next = $counter ? $counter->last_number + 1 : $rule->sequence_start;

        return $this->render($application, $rule, $next);
    }

    public function generate(AdmissionApplication $application, ?NimGenerationRule $rule = null): string
    {
        $rule ??= $this->activeRule();

        if (! $rule) {
            throw new RuntimeException('Belum ada NIM generation rule yang aktif.');
        }

        return DB::transaction(function () use ($application, $rule) {
            $scopeKey = $this->scopeKey($application, $rule);

            $counter = NimSequenceCounter::query()
                ->where('nim_generation_rule_id', $rule->id)
                ->where('scope_key', $scopeKey)
                ->lockForUpdate()
                ->first();

            if (! $counter) {
                $counter = NimSequenceCounter::create([
                    'nim_generation_rule_id' => $rule->id,
                    'scope_key' => $scopeKey,
                    'last_number' => $rule->sequence_start - 1,
                ]);
            }

            do {
                $next = $counter->last_number + 1;
                $nim = $this->render($application, $rule, $next);
                $counter->last_number = $next;
            } while (StudentProfile::withTrashed()->where('nim', $nim)->exists());

            $counter->save();

            return $nim;
        });
    }

    public function scopeKey(AdmissionApplication $application, NimGenerationRule $rule): string
    {
        $application->loadMissing(['period', 'faculty', 'studyProgram']);

        return match ($rule->sequence_scope) {
            'global' => 'global',
            'year' => 'year:'.$this->year($application),
            'period' => 'period:'.$application->admission_period_id,
            'faculty' => 'faculty:'.($application->faculty_id ?: 'none'),
            'study_program' => 'program:'.($application->study_program_id ?: 'none'),
            'class_type' => 'class:'.($application->class_type ?: 'none'),
            default => implode('|', [
                'year:'.$this->year($application),
                'program:'.($application->study_program_id ?: 'none'),
            ]),
        };
    }

    private function render(AdmissionApplication $application, NimGenerationRule $rule, int $sequence): string
    {
        $application->loadMissing(['period.academicYear', 'faculty', 'studyProgram']);

        $tokens = [
            '{year}' => (string) $this->year($application),
            '{yy}' => substr((string) $this->year($application), -2),
            '{period_code}' => $this->cleanToken($application->period?->code),
            '{faculty_code}' => $this->cleanToken($application->faculty?->code),
            '{program_code}' => $this->cleanToken($application->studyProgram?->code),
            '{class_type}' => $this->cleanToken($application->class_type),
            '{sequence}' => str_pad((string) $sequence, $rule->sequence_padding, '0', STR_PAD_LEFT),
        ];

        return strtr($rule->pattern, $tokens);
    }

    private function year(AdmissionApplication $application): int
    {
        return (int) (
            $application->period?->academic_year
            ?: $application->period?->academicYear?->year
            ?: now()->year
        );
    }

    private function cleanToken(?string $value): string
    {
        return strtoupper(preg_replace('/[^A-Za-z0-9]/', '', $value ?? ''));
    }
}
