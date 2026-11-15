<?php

namespace App\Enums;

enum ThesisDocumentCategory: string
{
    case PROPOSAL = 'proposal';
    case CHAPTER = 'chapter';
    case REVISION = 'revision';
    case FINAL = 'final';
    case PRESENTATION = 'presentation';
    case OTHER = 'other';

    public function label(): string
    {
        return match ($this) {
            self::PROPOSAL => 'Proposal',
            self::CHAPTER => 'Bab / Naskah',
            self::REVISION => 'Revisi',
            self::FINAL => 'Laporan Final',
            self::PRESENTATION => 'Bahan Presentasi',
            self::OTHER => 'Lainnya',
        };
    }

    public static function options(): array
    {
        return collect(self::cases())
            ->mapWithKeys(fn (self $case) => [$case->value => $case->label()])
            ->toArray();
    }
}
