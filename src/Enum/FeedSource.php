<?php

declare(strict_types=1);

namespace App\Enum;

enum FeedSource: string
{
    case EL_MUNDO = 'el_mundo';
    case EL_PAIS = 'el_pais';

    public function getName(): string
    {
        return match($this) {
            self::EL_MUNDO => 'El Mundo',
            self::EL_PAIS => 'El País',
        };
    }

    public function getBaseUrl(): string
    {
        return match($this) {
            self::EL_MUNDO => 'https://www.elmundo.es',
            self::EL_PAIS => 'https://www.elpais.com',
        };
    }

    public function getFlag(): string
    {
        return $this->value;
    }

    public static function getNameFromFlag(string $flag): ?string
    {
        $source = self::tryFrom($flag);

        return $source?->getName();
    }
}
