<?php

declare(strict_types=1);

namespace App\Validator;

use App\Enum\FeedSource;
use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\ConstraintValidator;
use Symfony\Component\Validator\Exception\UnexpectedTypeException;

class ValidFeedSourceValidator extends ConstraintValidator
{
    public function validate(mixed $value, Constraint $constraint): void
    {
        if (!$constraint instanceof ValidFeedSource) {
            throw new UnexpectedTypeException($constraint, ValidFeedSource::class);
        }

        if (null === $value || '' === $value) {
            return;
        }

        if (!is_string($value)) {
            throw new UnexpectedTypeException($value, 'string');
        }

        if (!FeedSource::tryFrom($value)) {
            $validValues = implode(', ', array_column(FeedSource::cases(), 'value'));

            $this->context->buildViolation($constraint->message)
                ->setParameter('{{ value }}', $value)
                ->setParameter('{{ valid_values }}', $validValues)
                ->addViolation();
        }
    }
}
