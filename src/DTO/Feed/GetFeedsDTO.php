<?php

namespace App\DTO\Feed;

use Symfony\Component\Validator\Constraints as Assert;
use App\Validator\ValidFeedSource;
use App\Validator\ValidDateString;

final class GetFeedsDTO
{
    #[Assert\Type('numeric', message: 'El valor "{{ value }}" no es válido para el campo límite.')]
    #[Assert\Positive(message: 'El límite debe ser un número positivo')]
    public ?string $sqlResult = null;

    #[ValidFeedSource]
    public ?string $source = null;

    #[ValidDateString]
    public ?string $since = null;

    public function getDate(): ?\DateTimeInterface
    {
        return is_null($this->since) ? null : new \DateTime($this->since);
    }

    public function getLimit(): ?int
    {
        return is_null($this->sqlResult) ? null : (int) $this->sqlResult;
    }

    public function getSource(): ?string
    {
        return $this->source;
    }
}
