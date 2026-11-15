<?php

namespace App\Enums;

enum BookReservationStatus: string
{
    case PENDING = 'pending';
    case READY = 'ready';
    case FULFILLED = 'fulfilled';
    case CANCELLED = 'cancelled';
    case EXPIRED = 'expired';

    public function label(): string
    {
        return match ($this) {
            self::PENDING => 'Menunggu',
            self::READY => 'Siap Diambil',
            self::FULFILLED => 'Selesai',
            self::CANCELLED => 'Dibatalkan',
            self::EXPIRED => 'Kedaluwarsa',
        };
    }

    public function tone(): string
    {
        return match ($this) {
            self::PENDING => 'amber',
            self::READY => 'blue',
            self::FULFILLED => 'green',
            self::CANCELLED => 'gray',
            self::EXPIRED => 'red',
        };
    }

    /**
     * @return array<int, string>
     */
    public static function activeValues(): array
    {
        return [self::PENDING->value, self::READY->value];
    }

    public static function options(): array
    {
        return collect(self::cases())
            ->mapWithKeys(fn (self $case) => [$case->value => $case->label()])
            ->toArray();
    }
}
