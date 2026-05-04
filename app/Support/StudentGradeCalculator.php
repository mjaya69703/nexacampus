<?php

namespace App\Support;

use App\Models\Academic\StudentGrade;

class StudentGradeCalculator
{
    public function calculateFinalScore(StudentGrade $studentGrade): ?float
    {
        $components = $studentGrade->components;

        if ($components->isEmpty()) {
            return null;
        }

        $total = $components->sum(function ($component) {
            $score = (float) ($component->score ?? 0);
            $weight = (float) ($component->weight_percentage ?? 0);

            return ($score * $weight) / 100;
        });

        return round($total, 2);
    }

    public function resolveLetterGrade(?float $finalScore): ?string
    {
        if ($finalScore === null) {
            return null;
        }

        if ($finalScore >= 95 && $finalScore <= 100) {
            return 'A+';
        }

        if ($finalScore >= 90 && $finalScore <= 94) {
            return 'A';
        }

        if ($finalScore >= 80 && $finalScore <= 89) {
            return 'B+';
        }

        if ($finalScore >= 70 && $finalScore <= 79) {
            return 'B';
        }

        if ($finalScore >= 60 && $finalScore <= 69) {
            return 'C';
        }

        if ($finalScore >= 50 && $finalScore <= 59) {
            return 'D';
        }

        return 'E';
    }

    public function resolveGradePoint(?string $letterGrade): ?float
    {
        if ($letterGrade === null) {
            return null;
        }

        return match ($letterGrade) {
            'A+' => 4.00,
            'A' => 3.50,
            'B+' => 3.00,
            'B' => 2.50,
            'C' => 2.00,
            'D' => 1.00,
            'E' => 0.00,
            default => null,
        };
    }

    public function resolveResultStatus(?string $letterGrade): ?string
    {
        if ($letterGrade === null) {
            return null;
        }

        return in_array($letterGrade, ['A+', 'A', 'B+', 'B', 'C'], true)
            ? 'Passed'
            : 'Failed';
    }

    public function calculateTotalWeight(StudentGrade $studentGrade): float
    {
        $total = $studentGrade->components->sum(function ($component) {
            return (float) ($component->weight_percentage ?? 0);
        });

        return round($total, 2);
    }

    public function isFinalizable(StudentGrade $studentGrade): bool
    {
        return abs($this->calculateTotalWeight($studentGrade) - 100.00) < 0.0001;
    }

    public function buildSnapshot(StudentGrade $studentGrade): array
    {
        $finalScore = $this->calculateFinalScore($studentGrade);
        $letterGrade = $this->resolveLetterGrade($finalScore);
        $gradePoint = $this->resolveGradePoint($letterGrade);
        $resultStatus = $this->resolveResultStatus($letterGrade);

        return [
            'final_score' => $finalScore,
            'letter_grade' => $letterGrade,
            'grade_point' => $gradePoint,
            'result_status' => $resultStatus,
        ];
    }
}
