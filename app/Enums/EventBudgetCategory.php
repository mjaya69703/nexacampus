<?php

namespace App\Enums;

enum EventBudgetCategory: string
{
    case CONSUMPTION = 'consumption';
    case FACILITY = 'facility';
    case SPEAKER = 'speaker';
    case PUBLICATION = 'publication';
    case EQUIPMENT = 'equipment';
    case TRANSPORTATION = 'transportation';
    case OTHER = 'other';

    public function label(): string
    {
        return match ($this) {
            self::CONSUMPTION => 'Konsumsi',
            self::FACILITY => 'Sewa Fasilitas',
            self::SPEAKER => 'Honor Pembicara',
            self::PUBLICATION => 'Publikasi & Dokumentasi',
            self::EQUIPMENT => 'Peralatan',
            self::TRANSPORTATION => 'Transportasi',
            self::OTHER => 'Lainnya',
        };
    }

    public function tone(): string
    {
        return match ($this) {
            self::CONSUMPTION => 'amber',
            self::FACILITY => 'blue',
            self::SPEAKER => 'green',
            self::PUBLICATION => 'blue',
            self::EQUIPMENT => 'gray',
            self::TRANSPORTATION => 'amber',
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
