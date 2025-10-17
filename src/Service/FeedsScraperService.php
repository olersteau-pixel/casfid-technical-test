<?php

declare(strict_types=1);

namespace App\Service;

use App\Repository\FeedsRepository;
use App\DTO\Scraper\ScrappedResultsDTO;
use App\DTO\Scraper\ScrapedFeedDTO;
use App\Entity\Feed;
use App\Service\Scraper\ScraperInterface;
use Doctrine\DBAL\Exception\UniqueConstraintViolationException;
use Psr\Log\LoggerInterface;

final class FeedsScraperService
{

    public function __construct(
        private readonly iterable $scrapers,
        private readonly FeedsRepository $feedsRepository,
        private readonly LoggerInterface $logger,
    ) {
    }

    public function scrapeAllSources(int $limit = 5): ScrappedResultsDTO
    {
        $stats = new ScrappedResultsDTO();

        foreach ($this->scrapers as $scraper) {
            try {
                $sourceStats = $this->scrapeSingleSource($scraper, $limit);
                $stats->total_scraped += $sourceStats['scraped'];
                $stats->total_saved += $sourceStats['saved'];
                $stats->total_duplicates += $sourceStats['duplicates'];
                $stats->total_errors += $sourceStats['errors'];
                $stats->sources[$scraper->getSourceName()] = $sourceStats;

            } catch (\Exception $e) {
                ++$stats->total_errors;
                $stats->sources[$scraper->getSourceName()] = [
                    'error' => $e->getMessage(),
                    'scraped' => 0,
                    'saved' => 0,
                    'duplicates' => 0,
                    'errors' => 1,
                ];

                $this->logger->error('Error scrapeando fuente', [
                    'source' => $scraper->getSourceName(),
                    'error' => $e->getMessage(),
                ]);
            }
        }


        return $stats;
    }

    /**
     * Ejecuta un scraper individual.
     */
    private function scrapeSingleSource(ScraperInterface $scraper, int $limit): array
    {
        $stats = [
            'scraped' => 0,
            'saved' => 0,
            'duplicates' => 0,
            'errors' => 0,
        ];

        $this->logger->info('Iniciando scraping', [
            'source' => $scraper->getSourceName(),
            'limit' => $limit,
        ]);

        try {
            $news = $scraper->scrape($limit);
            $stats['scraped'] = count($news);

            foreach ($news as $newsItem) {
                try {
                    // Verificar si ya existe por URL
                    $existing = $this->feedsRepository->findByUrl($newsItem->getUrl());

                    if ($existing) {
                        ++$stats['duplicates'];
                        $this->logger->debug('Noticia duplicada omitida', [
                            'url' => $newsItem->getUrl(),
                            'source' => $scraper->getSourceName(),
                        ]);
                        continue;
                    }
                    $feed = $this->mapScrapedDTOToEntity($newsItem);
                    $this->feedsRepository->save($feed);
                    ++$stats['saved'];

                } catch (UniqueConstraintViolationException $e) {
                    ++$stats['duplicates'];
                    $this->logger->debug('Duplicado detectado por base de datos', [
                        'url' => $newsItem->getUrl(),
                    ]);
                } catch (\Exception $e) {
                    ++$stats['errors'];
                    $this->logger->error('Error guardando noticia', [
                        'url' => $newsItem->getUrl(),
                        'error' => $e->getMessage(),
                    ]);
                }
            }

            // Flush de todas las noticias guardadas
            if ($stats['saved'] > 0) {
                $this->feedsRepository->flush();
            }

            $this->logger->info('Scraping completado', [
                'source' => $scraper->getSourceName(),
                'stats' => $stats,
            ]);

        } catch (\Exception $e) {
            $this->logger->error('Error en scraper', [
                'source' => $scraper->getSourceName(),
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
            throw $e;
        }

        return $stats;
    }

    private static function mapScrapedDTOToEntity(ScrapedFeedDTO $dto): Feed
    {
        $feed = new Feed();
        $feed->setTitle($dto->title);
        $feed->setUrl($dto->url);
        $feed->setSource($dto->source);
        $feed->setImageUrl($dto->imageUrl);
        $feed->setUpdatedAt(new \DateTime());
        $feed->setCreatedAt(new \DateTime());

        return $feed;
    }    
}
