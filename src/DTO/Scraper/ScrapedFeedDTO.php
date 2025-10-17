<?php

declare(strict_types=1);

namespace App\DTO\Scraper;

final class ScrapedFeedDTO
{
    public function __construct(
        public readonly string $title,
        public readonly string $url,
        public readonly string $source,
        public readonly ?string $imageUrl = null,
    ) {}

    public function getTitle(): string
    {
        return $this->title;
    }

    public function getUrl(): string
    {
        return $this->url;
    }
    
    public function getSource(): string
    {
        return $this->source;
    }
    
    public function getImageUrl(): ?string
    {
        return $this->imageUrl;
    }    
}
