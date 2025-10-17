<?php

namespace App\Tests\Service;

use App\DTO\Feed\CreateFeedDTO;
use App\DTO\Feed\UpdateFeedDTO;
use App\Entity\Feed;
use App\Exception\FeedNotFoundException;
use App\Exception\DuplicateFeedException;
use App\Repository\FeedsRepository;
use App\Service\FeedsService;
use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\MockObject\MockObject;
use Psr\Log\LoggerInterface;

class FeedsServiceTest extends TestCase
{
    private FeedRepository|MockObject $feedRepository;
    private LoggerInterface|MockObject $logger;
    private FeedsService $feedsService;

    protected function setUp(): void
    {
        $this->feedRepository = $this->createMock(FeedsRepository::class);
        $this->logger = $this->createMock(LoggerInterface::class);
        $this->feedsService = new FeedsService($this->feedRepository, $this->logger);
    }

    public function testGetFeedByIdSuccess(): void
    {
        $feed = $this->createFeed();
        
        $this->feedRepository
            ->expects($this->once())
            ->method('find')
            ->with(1)
            ->willReturn($feed);

        $result = $this->feedsService->getFeedById(1);

        $this->assertEquals(1, $result->id);
        $this->assertEquals('Test Feed', $result->title);
    }

    public function testGetFeedByIdNotFound(): void
    {
        $this->feedRepository
            ->expects($this->once())
            ->method('find')
            ->with(999)
            ->willReturn(null);

        $this->expectException(FeedNotFoundException::class);
        
        $this->feedsService->getFeedById(999);
    }

    public function testCreateFeedSuccess(): void
    {
        $dto = new CreateFeedDTO();
            $dto->title= 'Noticia Test';
            $dto->url= 'http://test.com/feed';
            $dto->source= 'el_mundo';

        $repositoryMock = $this->createMock(FeedsRepository::class);

        $repositoryMock
            ->method('save')
            ->willReturnCallback(function (Feed $feed, bool $flush) {
                $reflection = new \ReflectionClass($feed);
                $idProperty = $reflection->getProperty('id');
                $idProperty->setAccessible(true);
                $idProperty->setValue($feed, 123); // ID simulado
            });


        $service = new FeedsService($repositoryMock, $this->logger);

        $responseDTO = $service->createFeed($dto);

        $this->assertSame(123, $responseDTO->id);
        $this->assertSame($dto->title, $responseDTO->title);
        $this->assertSame($dto->url, $responseDTO->url);
        $this->assertSame($dto->imageUrl, $responseDTO->imageUrl);
        $this->assertSame($dto->source, $responseDTO->source);

        $this->assertMatchesRegularExpression('/^\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2}$/', $responseDTO->createdAt);
        $this->assertMatchesRegularExpression('/^\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2}$/', $responseDTO->updatedAt);
    }


    public function testCreateFeedDuplicate(): void
    {
        $dto = new CreateFeedDTO();
        $dto->title = 'New Feed';
        $dto->imageUrl = 'https://example.com/news.jpg';
        $dto->url = 'https://example.com/news';
        $dto->source = 'el_pais';

        $existingFeed = $this->createFeed();

        $this->feedRepository
            ->expects($this->once())
            ->method('findByUrl')
            ->willReturn($existingFeed);

        $this->expectException(DuplicateFeedException::class);
        
        $this->feedsService->createFeed($dto);
    }

    public function testUpdateFeedSuccess(): void
    {
        $feed = $this->createFeed();
        
        $dto = new UpdateFeedDTO();
        $dto->title = 'Updated Title';

        $this->feedRepository
            ->expects($this->once())
            ->method('find')
            ->with(1)
            ->willReturn($feed);

        $this->feedRepository
            ->expects($this->once())
            ->method('save')
            ->with($feed, true);

        $result = $this->feedsService->updateFeed(1, $dto);

        $this->assertEquals('Updated Title', $result->title);
    }


    public function testUpdateFeedNotFound(): void
    {
        $feed = $this->createFeed();
        
        $dto = new UpdateFeedDTO();
        $dto->title = 'Updated Title';

        $this->feedRepository
            ->expects($this->once())
            ->method('find')
            ->with(999)
            ->willReturn(null);

        $this->expectException(FeedNotFoundException::class);

        $result = $this->feedsService->updateFeed(999, $dto);
    }    

    public function testDeleteFeedSuccess(): void
    {
        $feed = $this->createFeed();

        $this->feedRepository
            ->expects($this->once())
            ->method('find')
            ->with(1)
            ->willReturn($feed);

        $this->feedRepository
            ->expects($this->once())
            ->method('remove')
            ->with($feed, true);

        $this->feedsService->deleteFeed(1);
    }

    public function testDeleteFeedNotFound(): void
    {
        $this->feedRepository
            ->expects($this->once())
            ->method('find')
            ->with(999)
            ->willReturn(null);

        $this->expectException(FeedNotFoundException::class);
        
        $this->feedsService->deleteFeed(999);
    }

    private function createFeed(): Feed
    {
        $feed = new Feed();
        $feed->setTitle('Test Feed')
            ->setUrl('https://example.com')
            ->setSource('Test Source')
            ->setCreatedAt(new \DateTime())
            ->setUpdatedAt(new \DateTime());

        // Usar reflexión para establecer el ID
        $reflection = new \ReflectionClass($feed);
        $property = $reflection->getProperty('id');
        $property->setAccessible(true);
        $property->setValue($feed, 1);

        return $feed;
    }
}