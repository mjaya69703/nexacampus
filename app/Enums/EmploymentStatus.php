<?php

namespace App\Enums;

enum EmploymentStatus: string
{
    case Working = 'working';
    case Entrepreneur = 'entrepreneur';
    case Studying = 'studying';
    case Unemployed = 'unemployed';
    case Other = 'other';

    public function label(): string
    {
        return match ($this) {
            self::Working => 'Bekerja',
            self::Entrepreneur => 'Wirausaha',
            self::Studying => 'Studi Lanjut',
            self::Unemployed => 'Belum Bekerja',
            self::Other => 'Lainnya',
        };
    }

    public static function options(): array
    {
        return collect(self::cases())
            ->mapWithKeys(fn ($case) => [$case->value => $case->label()])
            ->toArray();
    }
}
