<?php

declare(strict_types=1);

namespace App\Exception;

final class DuplicateFeedException extends \Exception
{
    public function __construct(string $message = 'Feed duplicado', int $code = 409)
    {
        parent::__construct($message, $code);
    }
}
