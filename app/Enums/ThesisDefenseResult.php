<?php

namespace App\Enums;

enum ThesisDefenseResult: string
{
    case PASSED = 'passed';
    case PASSED_WITH_REVISION = 'passed_with_revision';
    case FAILED = 'failed';

    public function label(): string
    {
        return match ($this) {
            self::PASSED => 'Lulus',
            self::PASSED_WITH_REVISION => 'Lulus dengan Revisi',
            self::FAILED => 'Tidak Lulus',
        };
    }

    public function tone(): string
    {
        return match ($this) {
            self::PASSED => 'green',
            self::PASSED_WITH_REVISION => 'amber',
            self::FAILED => 'red',
        };
    }

    public static function options(): array
    {
        return collect(self::cases())
            ->mapWithKeys(fn (self $case) => [$case->value => $case->label()])
            ->toArray();
    }
}
