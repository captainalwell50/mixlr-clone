<?php

namespace App\Enums;

enum EmailCategory: string
{
    case Transactional = 'transactional';
    case Marketing = 'marketing';
    case Operational = 'operational';

    public function label(): string
    {
        return match ($this) {
            self::Transactional => 'Transactional',
            self::Marketing => 'Marketing',
            self::Operational => 'Operational',
        };
    }

    public function allowsSuppression(): bool
    {
        return $this === self::Marketing;
    }
}
