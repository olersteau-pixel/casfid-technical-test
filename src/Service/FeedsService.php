<?php

declare(strict_types=1);

namespace App\Service;

use App\DTO\Feed\CreateFeedDTO;
use App\DTO\Feed\GetFeedsDTO;
use App\DTO\Feed\UpdateFeedDTO;
use App\DTO\Feed\FeedResponseDTO;
use App\Entity\Feed;
use App\Enum\FeedSource;
use App\Repository\FeedsRepository;
use App\Exception\FeedNotFoundException;
use App\Exception\DuplicateFeedException;
use Psr\Log\LoggerInterface;

final class FeedsService
{
    public function __construct(
        private readonly FeedsRepository $feedsRepository,
        private readonly LoggerInterface $logger,
    ) {
    }

    public function getAllFeeds(
        GetFeedsDTO $dto,
    ): array {
        $this->logger->info('Fetching feeds', [
            'creationDate' => $dto->getDate(),
            'limit' => $dto->getLimit(),
            'source' => $dto->getSource(),
        ]);

        $result = $this->feedsRepository->findAllCustom(
            $dto->getLimit(),
            $dto->getDate(),
            $dto->getSource(),
        );

        return [
            'data' => array_map(
                static fn (Feed $feed) => self::mapToResponseDTO($feed),
                $result
            ),
        ];
    }

    public function getFeedById(int $id): FeedResponseDTO
    {
        /** @var ?Feed @feed */
        $feed = $this->feedsRepository->find($id);

        if (!$feed) {
            $this->logger->warning('Feed not found', ['id' => $id]);
            throw new FeedNotFoundException("El feed con id {$id} no ha sido encontrado");
        }

        $this->logger->info('Feed retrieved', ['id' => $id]);

        return $this->mapToResponseDTO($feed);
    }


    public function createFeed(CreateFeedDTO $dto): FeedResponseDTO
    {
        $existingFeed = $this->feedsRepository->findByUrl(
            $dto->url
        );

        if ($existingFeed) {
            $this->logger->warning('Duplicate feed detected', [
                'url' => $dto->url,
            ]);
            throw new DuplicateFeedException('Ya existe un feed con esa url');
        }

        $feed = new Feed();
        $feed->setTitle($dto->title)
            ->setUrl($dto->url)
            ->setImageUrl($dto->imageUrl)
            ->setSource($dto->source)
            ->setUpdatedAt(new \DateTime())
            ->setCreatedAt(new \DateTime());

        $this->feedsRepository->save($feed, true);

        $this->logger->info('Feed created', ['id' => $feed->getId()]);

        return $this->mapToResponseDTO($feed);
    }

    public function updateFeed(int $id, UpdateFeedDTO $dto): FeedResponseDTO
    {
        /** @var ?Feed @feed */
        $feed = $this->feedsRepository->find($id);

        if (!$feed) {
            $this->logger->warning('Feed not found for update', ['id' => $id]);
            throw new FeedNotFoundException("El feed con id {$id} no ha sido encontrado");
        }

        if (null !== $dto->title) {
            $feed->setTitle($dto->title);
        }


        if (null !== $dto->url) {
            // Check if new URL creates a duplicate
            if ($dto->url !== $feed->getUrl()) {
                $existingFeed = $this->feedsRepository->findByUrl(
                    $dto->url
                );

                if ($existingFeed && $existingFeed->getId() !== $id) {
                    throw new DuplicateFeedException('Ya existe un feed con esa url');
                }
            }
            $feed->setUrl($dto->url);
        }

        if (null !== $dto->imageUrl) {
            $feed->setImageUrl($dto->imageUrl);
        }

        if (null !== $dto->source) {
            $feed->setSource($dto->source);
        }

        $feed->setUpdatedAt(new \DateTime());


        $this->feedsRepository->save($feed, true);

        $this->logger->info('Feed updated', ['id' => $id]);

        return $this->mapToResponseDTO($feed);
    }

    public function deleteFeed(int $id): void
    {
        /** @var ?Feed @feed */
        $feed = $this->feedsRepository->find($id);

        if (!$feed) {
            $this->logger->warning('Feed not found for deletion', ['id' => $id]);
            throw new FeedNotFoundException("El feed con id {$id} no ha sido encontrado");
        }

        $this->feedsRepository->remove($feed, true);

        $this->logger->info('Feed deleted', ['id' => $id]);
    }    
        
    private static function mapToResponseDTO(Feed $feed): FeedResponseDTO
    {
        $sourceEnum = FeedSource::tryFrom($feed->getSource());

        return new FeedResponseDTO(
            id: $feed->getId(),
            title: $feed->getTitle(),
            url: $feed->getUrl(),
            imageUrl: $feed->getImageUrl(),
            source: $feed->getSource(),
            sourceName: $sourceEnum?->getName(),
            updatedAt: $feed->getUpdatedAt()?->format('Y-m-d H:i:s'),
            createdAt: $feed->getCreatedAt()?->format('Y-m-d H:i:s')
        );
    }
}
