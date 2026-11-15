<?php

namespace App\Enums;

enum MemberPosition: string
{
    case CHAIRMAN = 'chairman';
    case VICE_CHAIRMAN = 'vice_chairman';
    case SECRETARY = 'secretary';
    case TREASURER = 'treasurer';
    case COORDINATOR = 'coordinator';
    case MEMBER = 'member';

    public function label(): string
    {
        return match ($this) {
            self::CHAIRMAN => 'Ketua',
            self::VICE_CHAIRMAN => 'Wakil Ketua',
            self::SECRETARY => 'Sekretaris',
            self::TREASURER => 'Bendahara',
            self::COORDINATOR => 'Koordinator',
            self::MEMBER => 'Anggota',
        };
    }

    public function tone(): string
    {
        return match ($this) {
            self::CHAIRMAN => 'red',
            self::VICE_CHAIRMAN => 'amber',
            self::SECRETARY, self::TREASURER => 'blue',
            self::COORDINATOR => 'green',
            self::MEMBER => 'gray',
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
