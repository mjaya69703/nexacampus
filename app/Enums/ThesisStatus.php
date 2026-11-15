<?php

namespace App\Enums;

enum ThesisStatus: string
{
    case DRAFT = 'draft';
    case SUBMITTED = 'submitted';
    case IN_PROGRESS = 'in_progress';
    case DEFENSE_READY = 'defense_ready';
    case DEFENDED = 'defended';
    case COMPLETED = 'completed';
    case REJECTED = 'rejected';
    case CANCELLED = 'cancelled';

    public function label(): string
    {
        return match ($this) {
            self::DRAFT => 'Draf',
            self::SUBMITTED => 'Menunggu Persetujuan',
            self::IN_PROGRESS => 'Bimbingan Berjalan',
            self::DEFENSE_READY => 'Siap Sidang',
            self::DEFENDED => 'Sudah Sidang',
            self::COMPLETED => 'Selesai',
            self::REJECTED => 'Ditolak',
            self::CANCELLED => 'Dibatalkan',
        };
    }

    public function tone(): string
    {
        return match ($this) {
            self::DRAFT => 'gray',
            self::SUBMITTED => 'amber',
            self::IN_PROGRESS => 'blue',
            self::DEFENSE_READY => 'purple',
            self::DEFENDED => 'teal',
            self::COMPLETED => 'green',
            self::REJECTED => 'red',
            self::CANCELLED => 'gray',
        };
    }

    public static function options(): array
    {
        return collect(self::cases())
            ->mapWithKeys(fn (self $case) => [$case->value => $case->label()])
            ->toArray();
    }

    public static function activeValues(): array
    {
        return [
            self::DRAFT->value,
            self::SUBMITTED->value,
            self::IN_PROGRESS->value,
            self::DEFENSE_READY->value,
            self::DEFENDED->value,
        ];
    }
}
