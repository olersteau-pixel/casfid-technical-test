<?php

declare(strict_types=1);

namespace App\Service\Scraper;

interface ScraperInterface
{
    public function scrape(int $limit = 5): array;

    public function getSourceName(): string;

    public function getBaseUrl(): string;
}
