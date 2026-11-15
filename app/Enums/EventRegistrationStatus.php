<?php

namespace App\Enums;

enum EventRegistrationStatus: string
{
    case REGISTERED = 'registered';
    case ATTENDED = 'attended';
    case CANCELLED = 'cancelled';

    public function label(): string
    {
        return match ($this) {
            self::REGISTERED => 'Terdaftar',
            self::ATTENDED => 'Hadir',
            self::CANCELLED => 'Dibatalkan',
        };
    }

    public function tone(): string
    {
        return match ($this) {
            self::REGISTERED => 'amber',
            self::ATTENDED => 'green',
            self::CANCELLED => 'gray',
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
