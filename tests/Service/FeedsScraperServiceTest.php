<?php

namespace App\Tests\Service;

use App\DTO\Scraper\ScrapedFeedDTO;
use App\Entity\Feed;
use App\Repository\FeedsRepository;
use App\Service\FeedsScraperService;
use App\Service\Scraper\ScraperInterface;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;

class FeedsScraperServiceTest extends TestCase
{
    private FeedsRepository $repository;
    private LoggerInterface $logger;

    protected function setUp(): void
    {
        $this->repository = $this->createMock(FeedsRepository::class);
        $this->logger = $this->createMock(LoggerInterface::class);
    }

    public function testScrapeAllSourcesSuccess(): void
    {
        // Crear mock de scraper
        $scraper = $this->createMock(ScraperInterface::class);
        
        $feed = new ScrapedFeedDTO(
            title: 'Test Feed',
            url: 'https://example.com/test',
            source: 'Test Source',
            imageUrl: null
        );

        $scraper->expects($this->once())
            ->method('scrape')
            ->with(5)
            ->willReturn([$feed]);
        
        $scraper->expects($this->any())
            ->method('getSourceName')
            ->willReturn('Test Source');

        $this->repository->expects($this->once())
            ->method('findByUrl')
            ->willReturn(null);

        $this->repository->expects($this->once())
            ->method('save');

        $this->repository->expects($this->once())
            ->method('flush');

        // Crear servicio con el scraper mockeado
        $service = new FeedsScraperService(
            [$scraper],
            $this->repository,
            $this->logger
        );

        $stats = $service->scrapeAllSources(5);

        $this->assertEquals(1, $stats->total_scraped);
        $this->assertEquals(1, $stats->total_saved);
        $this->assertEquals(0, $stats->total_duplicates);
        $this->assertEquals(0, $stats->total_errors);
    }

    public function testScrapeAllSourcesHandlesDuplicates(): void
    {
        $scraper = $this->createMock(ScraperInterface::class);
        
        $feed = new ScrapedFeedDTO(
            title: 'Test Feed',
            url: 'https://example.com/test',
            source: 'Test Source',
            imageUrl: null
        );
        
        $feedEntity = new Feed();
        $feedEntity->setUrl('https://example.com/test');

        $scraper->expects($this->once())
            ->method('scrape')
            ->willReturn([$feed]);
        
        $scraper->expects($this->any())
            ->method('getSourceName')
            ->willReturn('Test Source');

        // Repository encontrará un duplicado
        $this->repository->expects($this->once())
            ->method('findByUrl')
            ->willReturn($feedEntity);

        // Repository NO debe guardar
        $this->repository->expects($this->never())
            ->method('save');

        $service = new FeedsScraperService(
            [$scraper],
            $this->repository,
            $this->logger
        );

        $stats = $service->scrapeAllSources(5);

        $this->assertEquals(1, $stats->total_scraped);
        $this->assertEquals(0, $stats->total_saved);
        $this->assertEquals(1, $stats->total_duplicates);
    }

    public function testScrapeAllSourcesHandlesErrors(): void
    {
        $scraper = $this->createMock(ScraperInterface::class);
        
        $scraper->expects($this->once())
            ->method('scrape')
            ->willThrowException(new \Exception('Network error'));
        
        $scraper->expects($this->any())
            ->method('getSourceName')
            ->willReturn('Test Source');

        $service = new FeedsScraperService(
            [$scraper],
            $this->repository,
            $this->logger
        );

        $stats = $service->scrapeAllSources(5);

        $this->assertEquals(0, $stats->total_saved);
        $this->assertEquals(1, $stats->total_errors);
        $this->assertArrayHasKey('error', $stats->sources['Test Source']);
    }
}