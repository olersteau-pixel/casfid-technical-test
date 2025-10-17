<?php

declare(strict_types=1);

namespace App\Validator;

use Symfony\Component\Validator\Constraint;

#[\Attribute]
class ValidDateString extends Constraint
{
    public string $message = 'El valor "{{ value }}" no es una fecha válida. Formato esperado: Y-m-d';
    public string $format = 'Y-m-d';
}