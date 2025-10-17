<?php

declare(strict_types=1);

namespace App\Validator;

use Symfony\Component\Validator\Constraint;

#[\Attribute]
class ValidFeedSource extends Constraint
{
    public string $message = 'El valor "{{ value }}" no es válido. Opciones válidas: {{ valid_values }}';
}