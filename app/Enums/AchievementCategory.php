<?php

namespace App\Enums;

enum AchievementCategory: string
{
    case ACADEMIC = 'academic';
    case SPORTS = 'sports';
    case ARTS = 'arts';
    case RESEARCH = 'research';
    case ENTREPRENEURSHIP = 'entrepreneurship';
    case COMMUNITY = 'community';

    public function label(): string
    {
        return match ($this) {
            self::ACADEMIC => 'Akademik',
            self::SPORTS => 'Olahraga',
            self::ARTS => 'Seni & Budaya',
            self::RESEARCH => 'Penelitian & Inovasi',
            self::ENTREPRENEURSHIP => 'Kewirausahaan',
            self::COMMUNITY => 'Pengabdian Masyarakat',
        };
    }

    public function tone(): string
    {
        return match ($this) {
            self::ACADEMIC => 'blue',
            self::SPORTS => 'green',
            self::ARTS => 'amber',
            self::RESEARCH => 'red',
            self::ENTREPRENEURSHIP => 'green',
            self::COMMUNITY => 'gray',
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
