<?php

namespace App\Enums;

enum EventType: string
{
    case Webinar = 'webinar';
    case Networking = 'networking';
    case CareerFair = 'career_fair';
    case Workshop = 'workshop';
    case AlumniMeet = 'alumni_meet';

    public function label(): string
    {
        return match ($this) {
            self::Webinar => 'Webinar',
            self::Networking => 'Networking',
            self::CareerFair => 'Career Fair',
            self::Workshop => 'Workshop',
            self::AlumniMeet => 'Alumni Meet',
        };
    }

    public static function options(): array
    {
        return collect(self::cases())
            ->mapWithKeys(fn ($case) => [$case->value => $case->label()])
            ->toArray();
    }
}
