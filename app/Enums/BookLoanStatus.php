<?php

namespace App\Enums;

enum BookLoanStatus: string
{
    case BORROWED = 'borrowed';
    case RETURNED = 'returned';
    case OVERDUE = 'overdue';
    case LOST = 'lost';

    public function label(): string
    {
        return match ($this) {
            self::BORROWED => 'Dipinjam',
            self::RETURNED => 'Dikembalikan',
            self::OVERDUE => 'Terlambat',
            self::LOST => 'Hilang',
        };
    }

    public function tone(): string
    {
        return match ($this) {
            self::BORROWED => 'blue',
            self::RETURNED => 'green',
            self::OVERDUE => 'red',
            self::LOST => 'gray',
        };
    }

    /**
     * @return array<int, string>
     */
    public static function activeValues(): array
    {
        return [self::BORROWED->value, self::OVERDUE->value];
    }

    public static function options(): array
    {
        return collect(self::cases())
            ->mapWithKeys(fn (self $case) => [$case->value => $case->label()])
            ->toArray();
    }
}
