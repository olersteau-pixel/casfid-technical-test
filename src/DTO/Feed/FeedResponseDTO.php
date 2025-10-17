<?php

declare(strict_types=1);

namespace App\DTO\Feed;

final class FeedResponseDTO
{
    public function __construct(
        public readonly ?int $id,
        public readonly ?string $title,
        public readonly ?string $url,
        public readonly ?string $imageUrl,
        public readonly ?string $source,
        public readonly ?string $sourceName,
        public readonly ?string $updatedAt,
        public readonly ?string $createdAt,
    ) {}

    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'title' => $this->title,
            'url' => $this->url,
            'imageUrl' => $this->imageUrl,
            'source' => $this->source,
            'sourceName' => $this->sourceName,
            'updatedAt' => $this->updatedAt,
            'createdAt' => $this->createdAt,
        ];
    }
}
