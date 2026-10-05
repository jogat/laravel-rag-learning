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

    public function outOfScopeReply(): string
    {
        return match ($this) {
            self::English => 'I can only help with questions about our business and your orders.',
            self::Spanish => 'Solo puedo ayudarte con preguntas sobre nuestro negocio y tus pedidos.',
            self::French => 'Je peux uniquement vous aider avec des questions sur notre entreprise et vos commandes.',
        };
    }
}
