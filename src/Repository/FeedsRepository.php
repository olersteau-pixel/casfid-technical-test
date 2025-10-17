<?php

namespace App\Repository;

use App\Entity\Feed;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

class FeedsRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Feed::class);
    }

    public function save(Feed $feed, bool $flush = false): void
    {
        $this->getEntityManager()->persist($feed);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    public function flush(): void
    {
        $this->getEntityManager()->flush();
    }

    public function findByUrl(string $url): ?Feed
    {
        /** @var ?Feed $feed */
        $feed = $this->findOneBy(['url' => $url]); 
        return $feed;
    }
}
