<?php

namespace App\Enums;

enum AchievementLevel: string
{
    case LOCAL = 'local';
    case REGIONAL = 'regional';
    case NATIONAL = 'national';
    case INTERNATIONAL = 'international';

    public function label(): string
    {
        return match ($this) {
            self::LOCAL => 'Lokal',
            self::REGIONAL => 'Regional',
            self::NATIONAL => 'Nasional',
            self::INTERNATIONAL => 'Internasional',
        };
    }

    public function tone(): string
    {
        return match ($this) {
            self::LOCAL => 'gray',
            self::REGIONAL => 'blue',
            self::NATIONAL => 'amber',
            self::INTERNATIONAL => 'red',
        };
    }

    public function weight(): int
    {
        return match ($this) {
            self::LOCAL => 1,
            self::REGIONAL => 2,
            self::NATIONAL => 3,
            self::INTERNATIONAL => 4,
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
