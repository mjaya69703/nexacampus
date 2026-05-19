<?php

namespace App\Enums;

enum AnnouncementTargetType: string
{
    case GLOBAL = 'global';
    case FACULTY = 'faculty';
    case STUDY_PROGRAM = 'study_program';
    case COURSE_OFFERING = 'course_offering';
    case LECTURER = 'lecturer';
    case STUDENT = 'student';

    public function label(): string
    {
        return match ($this) {
            self::GLOBAL => 'Global (Semua Pengguna)',
            self::FACULTY => 'Fakultas',
            self::STUDY_PROGRAM => 'Program Studi',
            self::COURSE_OFFERING => 'Kelas/Kursus',
            self::LECTURER => 'Dosen Tertentu',
            self::STUDENT => 'Mahasiswa Tertentu',
        };
    }

    public function requiresTargetId(): bool
    {
        return $this !== self::GLOBAL;
    }

    public static function options(): array
    {
        return collect(self::cases())
            ->mapWithKeys(fn ($case) => [$case->value => $case->label()])
            ->toArray();
    }
}
