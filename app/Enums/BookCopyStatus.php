<?php

namespace App\Enums;

enum BookCopyStatus: string
{
    case AVAILABLE = 'available';
    case BORROWED = 'borrowed';
    case RESERVED = 'reserved';
    case LOST = 'lost';
    case DAMAGED = 'damaged';
    case MAINTENANCE = 'maintenance';

    public function label(): string
    {
        return match ($this) {
            self::AVAILABLE => 'Tersedia',
            self::BORROWED => 'Dipinjam',
            self::RESERVED => 'Dipesan',
            self::LOST => 'Hilang',
            self::DAMAGED => 'Rusak',
            self::MAINTENANCE => 'Perawatan',
        };
    }

    public function tone(): string
    {
        return match ($this) {
            self::AVAILABLE => 'green',
            self::BORROWED => 'blue',
            self::RESERVED => 'amber',
            self::LOST => 'red',
            self::DAMAGED => 'red',
            self::MAINTENANCE => 'gray',
        };
    }

    /**
     * Status yang boleh ditetapkan manual oleh petugas tanpa transaksi pinjam.
     *
     * @return array<int, string>
     */
    public static function manualValues(): array
    {
        return [
            self::AVAILABLE->value,
            self::LOST->value,
            self::DAMAGED->value,
            self::MAINTENANCE->value,
        ];
    }

    public static function options(): array
    {
        return collect(self::cases())
            ->mapWithKeys(fn (self $case) => [$case->value => $case->label()])
            ->toArray();
    }
}
