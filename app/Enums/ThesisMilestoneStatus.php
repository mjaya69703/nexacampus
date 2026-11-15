<?php

namespace App\Enums;

enum ThesisMilestoneStatus: string
{
    case PENDING = 'pending';
    case IN_PROGRESS = 'in_progress';
    case SUBMITTED = 'submitted';
    case REVISION = 'revision';
    case APPROVED = 'approved';

    public function label(): string
    {
        return match ($this) {
            self::PENDING => 'Belum Dimulai',
            self::IN_PROGRESS => 'Sedang Dikerjakan',
            self::SUBMITTED => 'Menunggu Review',
            self::REVISION => 'Perlu Revisi',
            self::APPROVED => 'Disetujui',
        };
    }

    public function tone(): string
    {
        return match ($this) {
            self::PENDING => 'gray',
            self::IN_PROGRESS => 'blue',
            self::SUBMITTED => 'amber',
            self::REVISION => 'red',
            self::APPROVED => 'green',
        };
    }

    public static function options(): array
    {
        return collect(self::cases())
            ->mapWithKeys(fn (self $case) => [$case->value => $case->label()])
            ->toArray();
    }
}
