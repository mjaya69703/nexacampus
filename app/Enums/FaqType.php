<?php

namespace App\Enums;

enum FaqType: string
{
    case GENERAL = 'general';
    case ADMISSION = 'admission';
    case ACADEMIC = 'academic';
    case FINANCIAL = 'financial';
    case STUDENT_SERVICE = 'student_service';

    public function label(): string
    {
        return match ($this) {
            self::GENERAL => 'Umum',
            self::ADMISSION => 'PMB / Admission',
            self::ACADEMIC => 'Akademik',
            self::FINANCIAL => 'Keuangan',
            self::STUDENT_SERVICE => 'Layanan Mahasiswa',
        };
    }

    public function badgeClass(): string
    {
        return match ($this) {
            self::GENERAL => 'bg-secondary',
            self::ADMISSION => 'bg-primary',
            self::ACADEMIC => 'bg-info text-white',
            self::FINANCIAL => 'bg-success',
            self::STUDENT_SERVICE => 'bg-warning text-dark',
        };
    }

    public function icon(): string
    {
        return match ($this) {
            self::GENERAL => 'fas fa-info-circle',
            self::ADMISSION => 'fas fa-user-plus',
            self::ACADEMIC => 'fas fa-graduation-cap',
            self::FINANCIAL => 'fas fa-wallet',
            self::STUDENT_SERVICE => 'fas fa-hand-holding-heart',
        };
    }

    public static function options(): array
    {
        return collect(self::cases())
            ->mapWithKeys(fn ($case) => [$case->value => $case->label()])
            ->toArray();
    }
}
