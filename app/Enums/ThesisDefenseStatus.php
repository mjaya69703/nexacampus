<?php

namespace App\Enums;

enum ThesisDefenseStatus: string
{
    case SCHEDULED = 'scheduled';
    case COMPLETED = 'completed';
    case CANCELLED = 'cancelled';
    case POSTPONED = 'postponed';

    public function label(): string
    {
        return match ($this) {
            self::SCHEDULED => 'Terjadwal',
            self::COMPLETED => 'Selesai',
            self::CANCELLED => 'Dibatalkan',
            self::POSTPONED => 'Ditunda',
        };
    }

    public function tone(): string
    {
        return match ($this) {
            self::SCHEDULED => 'blue',
            self::COMPLETED => 'green',
            self::CANCELLED => 'red',
            self::POSTPONED => 'amber',
        };
    }

    public static function options(): array
    {
        return collect(self::cases())
            ->mapWithKeys(fn (self $case) => [$case->value => $case->label()])
            ->toArray();
    }
}
