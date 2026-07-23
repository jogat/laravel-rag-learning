<?php

namespace App\Enums;

enum LanguagesEnum: string
{
    case English = 'English';
    case Spanish = 'Español';
    case French = 'Français';

    public function shortName(): string
    {
        return match ($this) {
            self::English => 'en',
            self::Spanish => 'es',
            self::French => 'fr',
        };
    }
}
