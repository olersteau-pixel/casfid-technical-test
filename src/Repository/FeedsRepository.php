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

    public function findAllCustom(?int $sqlResult = null, ?\DateTimeInterface $since = null, ?string $source = null): array
    {
        $qb = $this->createQueryBuilder('n')
                   ->orderBy('n.createdAt', 'DESC');

        if ($since) {
            /** @var \DateTime $dateSinceFirst */
            $dateSinceFirst = (clone $since);
            /** @var \DateTime $dateSinceLast */
            $dateSinceLast = (clone $since);
            $startOfDay = $dateSinceFirst->setTime(0, 0, 0);
            $endOfDay   = $dateSinceLast->setTime(23, 59, 59);

            $qb->andWhere('n.createdAt BETWEEN :start AND :end')
                ->setParameter('start', $startOfDay)
                ->setParameter('end', $endOfDay);
        }

        if ($source) {
            $qb->andWhere('n.source = :source')
               ->setParameter('source', $source);
        }

        if ($sqlResult) {
            $qb->setMaxResults($sqlResult);
        }

        return $qb->getQuery()->getResult();
    }

    public function remove(Feed $feed, bool $flush = false): void
    {
        $this->getEntityManager()->remove($feed);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }
}
