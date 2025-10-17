<?php

declare(strict_types=1);

namespace App\DTO\Feed;

use App\Validator\ValidFeedSource;
use Symfony\Component\Validator\Constraints as Assert;

final class UpdateFeedDTO
{
    #[Assert\Length(
        min: 3,
        max: 255,
        minMessage: 'El título debe tener al menos {{ limit }} caracteres',
        maxMessage: 'El título no puede exceder {{ limit }} caracteres'
    )]
    public ?string $title = null;

    #[Assert\Url(message: 'La URL no es válida', requireTld: true)]
    public ?string $url = null;

    #[Assert\Url(message: 'La URL de la imagen no es válida', requireTld: true)]
    public ?string $imageUrl = null;

    #[ValidFeedSource]
    public ?string $source = null;

}
