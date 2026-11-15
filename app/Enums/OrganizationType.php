<?php

namespace App\Enums;

enum OrganizationType: string
{
    case BEM = 'bem';
    case DPM = 'dpm';
    case HIMA = 'hima';
    case UKM = 'ukm';
    case COMMUNITY = 'community';

    public function label(): string
    {
        return match ($this) {
            self::BEM => 'Badan Eksekutif Mahasiswa',
            self::DPM => 'Dewan Perwakilan Mahasiswa',
            self::HIMA => 'Himpunan Mahasiswa',
            self::UKM => 'Unit Kegiatan Mahasiswa',
            self::COMMUNITY => 'Komunitas Mahasiswa',
        };
    }

    public function shortLabel(): string
    {
        return match ($this) {
            self::BEM => 'BEM',
            self::DPM => 'DPM',
            self::HIMA => 'HIMA',
            self::UKM => 'UKM',
            self::COMMUNITY => 'Komunitas',
        };
    }

    public function tone(): string
    {
        return match ($this) {
            self::BEM, self::DPM => 'red',
            self::HIMA => 'blue',
            self::UKM => 'green',
            self::COMMUNITY => 'amber',
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
