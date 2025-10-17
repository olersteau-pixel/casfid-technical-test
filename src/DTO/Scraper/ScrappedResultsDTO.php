<?php

declare(strict_types=1);

namespace App\DTO\Scraper;

final class ScrappedResultsDTO
{
    public int $total_scraped = 0;
    public int $total_saved = 0;
    public int $total_duplicates = 0;
    public int $total_errors = 0;
    public array $sources = [];

    public function toArray(): array
    {
        return [
            'total_scraped' => $this->total_scraped,
            'total_saved' => $this->total_saved,
            'total_duplicates' => $this->total_duplicates,
            'total_errors' => $this->total_errors,
            'sources' => $this->sources,
        ];
    }
}
