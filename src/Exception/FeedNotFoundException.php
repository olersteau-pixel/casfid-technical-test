<?php

declare(strict_types=1);

namespace App\Exception;

final class FeedNotFoundException extends \Exception
{
    public function __construct(string $message = 'Feed no encontrado', int $code = 404)
    {
        parent::__construct($message, $code);
    }
}
