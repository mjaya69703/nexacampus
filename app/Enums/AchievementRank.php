<?php

namespace App\Enums;

enum AchievementRank: string
{
    case WINNER = 'winner';
    case RUNNER_UP = 'runner_up';
    case THIRD_PLACE = 'third_place';
    case HONORABLE_MENTION = 'honorable_mention';
    case FINALIST = 'finalist';
    case PARTICIPANT = 'participant';

    public function label(): string
    {
        return match ($this) {
            self::WINNER => 'Juara 1',
            self::RUNNER_UP => 'Juara 2',
            self::THIRD_PLACE => 'Juara 3',
            self::HONORABLE_MENTION => 'Juara Harapan',
            self::FINALIST => 'Finalis',
            self::PARTICIPANT => 'Peserta',
        };
    }

    public function tone(): string
    {
        return match ($this) {
            self::WINNER => 'amber',
            self::RUNNER_UP => 'gray',
            self::THIRD_PLACE => 'amber',
            self::HONORABLE_MENTION => 'green',
            self::FINALIST => 'blue',
            self::PARTICIPANT => 'gray',
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
