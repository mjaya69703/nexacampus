<?php

namespace App\Enums;

enum StudentEventCategory: string
{
    case SEMINAR = 'seminar';
    case WORKSHOP = 'workshop';
    case COMPETITION = 'competition';
    case SOCIAL = 'social';
    case SPORTS = 'sports';
    case ARTS = 'arts';
    case OTHER = 'other';

    public function label(): string
    {
        return match ($this) {
            self::SEMINAR => 'Seminar',
            self::WORKSHOP => 'Workshop',
            self::COMPETITION => 'Kompetisi',
            self::SOCIAL => 'Pengabdian & Sosial',
            self::SPORTS => 'Olahraga',
            self::ARTS => 'Seni & Budaya',
            self::OTHER => 'Lainnya',
        };
    }

    public function tone(): string
    {
        return match ($this) {
            self::SEMINAR => 'blue',
            self::WORKSHOP => 'green',
            self::COMPETITION => 'red',
            self::SOCIAL => 'amber',
            self::SPORTS => 'green',
            self::ARTS => 'amber',
            self::OTHER => 'gray',
        };
    }

    /**
     * @return array<string, string>
     */
    public static function options(): array
    {
        return collect(self::cases())
            ->mapWithKeys(fn (self $case) => [$case->value => $case->label()])
            ->toArray();
    }
}
