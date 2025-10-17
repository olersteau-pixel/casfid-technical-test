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
