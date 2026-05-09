<?php

namespace App\Enums;

enum AnnouncementPriority: string
{
    case NORMAL = 'normal';
    case IMPORTANT = 'important';
    case URGENT = 'urgent';

    public function label(): string
    {
        return match ($this) {
            self::NORMAL => 'Normal',
            self::IMPORTANT => 'Penting',
            self::URGENT => 'Mendesak',
        };
    }

    public function badgeClass(): string
    {
        return match ($this) {
            self::NORMAL => 'bg-secondary',
            self::IMPORTANT => 'bg-warning text-dark',
            self::URGENT => 'bg-danger',
        };
    }

    public function icon(): string
    {
        return match ($this) {
            self::NORMAL => 'fas fa-info-circle',
            self::IMPORTANT => 'fas fa-exclamation-triangle',
            self::URGENT => 'fas fa-bell',
        };
    }

    public static function options(): array
    {
        return collect(self::cases())
            ->mapWithKeys(fn ($case) => [$case->value => $case->label()])
            ->toArray();
    }
}
