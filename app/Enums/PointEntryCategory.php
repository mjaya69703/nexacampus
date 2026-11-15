<?php

namespace App\Enums;

enum PointEntryCategory: string
{
    case EVENT = 'event';
    case ACHIEVEMENT = 'achievement';
    case ORGANIZATION = 'organization';
    case MANUAL = 'manual';
    case OTHER = 'other';

    public function label(): string
    {
        return match ($this) {
            self::EVENT => 'Keikutsertaan Event',
            self::ACHIEVEMENT => 'Prestasi',
            self::ORGANIZATION => 'Keaktifan Organisasi',
            self::MANUAL => 'Penyesuaian Manual',
            self::OTHER => 'Lainnya',
        };
    }

    public function tone(): string
    {
        return match ($this) {
            self::EVENT => 'blue',
            self::ACHIEVEMENT => 'amber',
            self::ORGANIZATION => 'green',
            self::MANUAL => 'gray',
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
