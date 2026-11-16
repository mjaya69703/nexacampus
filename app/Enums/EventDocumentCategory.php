<?php

namespace App\Enums;

enum EventDocumentCategory: string
{
    case PHOTO = 'photo';
    case REPORT = 'report';
    case OTHER = 'other';

    public function label(): string
    {
        return match ($this) {
            self::PHOTO => 'Foto Kegiatan',
            self::REPORT => 'Laporan Kegiatan',
            self::OTHER => 'Dokumen Lain',
        };
    }

    public function tone(): string
    {
        return match ($this) {
            self::PHOTO => 'blue',
            self::REPORT => 'green',
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
