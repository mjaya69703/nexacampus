<?php

namespace App\Enums;

enum JobRelevance: string
{
    case Relevant = 'relevant';
    case PartiallyRelevant = 'partially_relevant';
    case NotRelevant = 'not_relevant';

    public function label(): string
    {
        return match ($this) {
            self::Relevant => 'Sesuai',
            self::PartiallyRelevant => 'Sebagian Sesuai',
            self::NotRelevant => 'Tidak Sesuai',
        };
    }

    public static function options(): array
    {
        return collect(self::cases())
            ->mapWithKeys(fn ($case) => [$case->value => $case->label()])
            ->toArray();
    }
}
