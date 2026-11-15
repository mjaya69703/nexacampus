<?php

namespace App\Enums;

enum PlagiarismStatus: string
{
    case NOT_CHECKED = 'not_checked';
    case PENDING = 'pending';
    case PASSED = 'passed';
    case FLAGGED = 'flagged';

    public function label(): string
    {
        return match ($this) {
            self::NOT_CHECKED => 'Belum Dicek',
            self::PENDING => 'Menunggu Hasil',
            self::PASSED => 'Lolos Cek',
            self::FLAGGED => 'Ditandai',
        };
    }

    public function tone(): string
    {
        return match ($this) {
            self::NOT_CHECKED => 'gray',
            self::PENDING => 'amber',
            self::PASSED => 'green',
            self::FLAGGED => 'red',
        };
    }

    public static function options(): array
    {
        return collect(self::cases())
            ->mapWithKeys(fn (self $case) => [$case->value => $case->label()])
            ->toArray();
    }
}
