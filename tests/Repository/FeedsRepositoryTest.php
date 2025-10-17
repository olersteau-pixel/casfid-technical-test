<?php

namespace App\Tests\Repository;

use App\Entity\Feed;
use App\Repository\FeedsRepository;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\QueryBuilder;
use Doctrine\ORM\Query;
use Doctrine\ORM\Mapping\ClassMetadata;
use Doctrine\Persistence\ManagerRegistry;
use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\MockObject\MockObject;

class FeedsRepositoryTest extends TestCase
{
    private EntityManagerInterface|MockObject $entityManager;
    private ManagerRegistry|MockObject $registry;
    private ClassMetadata|MockObject $classMetadata;
    private FeedsRepository $repository;

    protected function setUp(): void
    {
        $this->entityManager = $this->createMock(EntityManagerInterface::class);
        $this->registry = $this->createMock(ManagerRegistry::class);
        $this->classMetadata = $this->createMock(ClassMetadata::class);
        
        // Configurar ClassMetadata correctamente
        $this->classMetadata->name = Feed::class;
        
        $this->entityManager
            ->method('getClassMetadata')
            ->with(Feed::class)
            ->willReturn($this->classMetadata);
        
        $this->registry
            ->method('getManagerForClass')
            ->with(Feed::class)
            ->willReturn($this->entityManager);

        $this->repository = new FeedsRepository($this->registry);
    }

    public function testSaveWithoutFlush(): void
    {
        $feed = new Feed();
        
        $this->entityManager
            ->expects($this->once())
            ->method('persist')
            ->with($feed);
        
        $this->entityManager
            ->expects($this->never())
            ->method('flush');

        $this->repository->save($feed, false);
    }

    public function testSaveWithFlush(): void
    {
        $feed = new Feed();
        
        $this->entityManager
            ->expects($this->once())
            ->method('persist')
            ->with($feed);
        
        $this->entityManager
            ->expects($this->once())
            ->method('flush');

        $this->repository->save($feed, true);
    }

    public function testRemoveWithoutFlush(): void
    {
        $feed = new Feed();
        
        $this->entityManager
            ->expects($this->once())
            ->method('remove')
            ->with($feed);
        
        $this->entityManager
            ->expects($this->never())
            ->method('flush');

        $this->repository->remove($feed, false);
    }

    public function testRemoveWithFlush(): void
    {
        $feed = new Feed();
        
        $this->entityManager
            ->expects($this->once())
            ->method('remove')
            ->with($feed);
        
        $this->entityManager
            ->expects($this->once())
            ->method('flush');

        $this->repository->remove($feed, true);
    }

    public function testFlush(): void
    {
        $this->entityManager
            ->expects($this->once())
            ->method('flush');

        $this->repository->flush();
    }

    public function testFindByUrlReturnsFeeds(): void
    {
        $url = 'https://example.com/feed';
        $expectedFeed = new Feed();
        
        $repo = $this->getMockBuilder(FeedsRepository::class)
            ->setConstructorArgs([$this->registry])
            ->onlyMethods(['findOneBy'])
            ->getMock();

        $repo->expects($this->once())
            ->method('findOneBy')
            ->with(['url' => $url])
            ->willReturn($expectedFeed);

        $result = $repo->findByUrl($url);
        
        $this->assertSame($expectedFeed, $result);
    }

    public function testFindByUrlReturnsNull(): void
    {
        $url = 'https://example.com/notfound';
        
        $repo = $this->getMockBuilder(FeedsRepository::class)
            ->setConstructorArgs([$this->registry])
            ->onlyMethods(['findOneBy'])
            ->getMock();

        $repo->expects($this->once())
            ->method('findOneBy')
            ->with(['url' => $url])
            ->willReturn(null);

        $result = $repo->findByUrl($url);
        
        $this->assertNull($result);
    }

    public function testFindAllCustomWithoutFilters(): void
    {
        $expectedFeeds = [new Feed(), new Feed()];
        
        $query = $this->createMock(Query::class);
        $query->expects($this->once())
            ->method('getResult')
            ->willReturn($expectedFeeds);

        $qb = $this->createMock(QueryBuilder::class);
        $qb->expects($this->once())
            ->method('orderBy')
            ->with('n.createdAt', 'DESC')
            ->willReturnSelf();
        
        $qb->expects($this->once())
            ->method('getQuery')
            ->willReturn($query);

        $repo = $this->getMockBuilder(FeedsRepository::class)
            ->setConstructorArgs([$this->registry])
            ->onlyMethods(['createQueryBuilder'])
            ->getMock();

        $repo->expects($this->once())
            ->method('createQueryBuilder')
            ->with('n')
            ->willReturn($qb);

        $result = $repo->findAllCustom();
        
        $this->assertSame($expectedFeeds, $result);
        $this->assertCount(2, $result);
    }

    public function testFindAllCustomWithMaxResults(): void
    {
        $maxResults = 10;
        $expectedFeeds = [new Feed()];
        
        $query = $this->createMock(Query::class);
        $query->method('getResult')->willReturn($expectedFeeds);

        $qb = $this->createMock(QueryBuilder::class);
        $qb->method('orderBy')->willReturnSelf();
        
        $qb->expects($this->once())
            ->method('setMaxResults')
            ->with($maxResults)
            ->willReturnSelf();
        
        $qb->method('getQuery')->willReturn($query);

        $repo = $this->getMockBuilder(FeedsRepository::class)
            ->setConstructorArgs([$this->registry])
            ->onlyMethods(['createQueryBuilder'])
            ->getMock();

        $repo->method('createQueryBuilder')->willReturn($qb);

        $result = $repo->findAllCustom($maxResults);
        
        $this->assertSame($expectedFeeds, $result);
    }

    public function testFindAllCustomWithSinceFilter(): void
    {
        $since = new \DateTime('2025-01-15 14:30:00');
        $expectedFeeds = [new Feed()];
        
        $query = $this->createMock(Query::class);
        $query->method('getResult')->willReturn($expectedFeeds);

        $qb = $this->createMock(QueryBuilder::class);
        $qb->method('orderBy')->willReturnSelf();
        
        $qb->expects($this->once())
            ->method('andWhere')
            ->with('n.createdAt BETWEEN :start AND :end')
            ->willReturnSelf();
        
        // PHPUnit 10+ usa willReturnCallback en lugar de withConsecutive
        $parameterCalls = 0;
        $qb->expects($this->exactly(2))
            ->method('setParameter')
            ->willReturnCallback(function($key, $value) use (&$parameterCalls, $qb) {
                $parameterCalls++;
                if ($parameterCalls === 1) {
                    $this->assertEquals('start', $key);
                    $this->assertInstanceOf(\DateTimeInterface::class, $value);
                    $this->assertEquals('00:00:00', $value->format('H:i:s'));
                } elseif ($parameterCalls === 2) {
                    $this->assertEquals('end', $key);
                    $this->assertInstanceOf(\DateTimeInterface::class, $value);
                    $this->assertEquals('23:59:59', $value->format('H:i:s'));
                }
                return $qb;
            });
        
        $qb->method('getQuery')->willReturn($query);

        $repo = $this->getMockBuilder(FeedsRepository::class)
            ->setConstructorArgs([$this->registry])
            ->onlyMethods(['createQueryBuilder'])
            ->getMock();

        $repo->method('createQueryBuilder')->willReturn($qb);

        $result = $repo->findAllCustom(null, $since);
        
        $this->assertSame($expectedFeeds, $result);
    }

    public function testFindAllCustomWithSourceFilter(): void
    {
        $source = 'RSS';
        $expectedFeeds = [new Feed()];
        
        $query = $this->createMock(Query::class);
        $query->method('getResult')->willReturn($expectedFeeds);

        $qb = $this->createMock(QueryBuilder::class);
        $qb->method('orderBy')->willReturnSelf();
        
        $qb->expects($this->once())
            ->method('andWhere')
            ->with('n.source = :source')
            ->willReturnSelf();
        
        $qb->expects($this->once())
            ->method('setParameter')
            ->with('source', $source)
            ->willReturnSelf();
        
        $qb->method('getQuery')->willReturn($query);

        $repo = $this->getMockBuilder(FeedsRepository::class)
            ->setConstructorArgs([$this->registry])
            ->onlyMethods(['createQueryBuilder'])
            ->getMock();

        $repo->method('createQueryBuilder')->willReturn($qb);

        $result = $repo->findAllCustom(null, null, $source);
        
        $this->assertSame($expectedFeeds, $result);
    }

    public function testFindAllCustomWithAllFilters(): void
    {
        $maxResults = 5;
        $since = new \DateTime('2025-10-15');
        $source = 'API';
        $expectedFeeds = [new Feed(), new Feed()];
        
        $query = $this->createMock(Query::class);
        $query->method('getResult')->willReturn($expectedFeeds);

        $qb = $this->createMock(QueryBuilder::class);
        $qb->method('orderBy')->willReturnSelf();
        $qb->method('andWhere')->willReturnSelf();
        $qb->method('setParameter')->willReturnSelf();
        $qb->method('setMaxResults')->willReturnSelf();
        $qb->method('getQuery')->willReturn($query);

        $repo = $this->getMockBuilder(FeedsRepository::class)
            ->setConstructorArgs([$this->registry])
            ->onlyMethods(['createQueryBuilder'])
            ->getMock();

        $repo->method('createQueryBuilder')->willReturn($qb);

        // Verificar que setMaxResults fue llamado
        $qb->expects($this->once())
            ->method('setMaxResults')
            ->with($maxResults);

        // Verificar que andWhere fue llamado 2 veces (para fecha y source)
        $qb->expects($this->exactly(2))
            ->method('andWhere');

        $result = $repo->findAllCustom($maxResults, $since, $source);
        
        $this->assertSame($expectedFeeds, $result);
        $this->assertIsArray($result);
        $this->assertCount(2, $result);
    }

    public function testFindAllCustomReturnsEmptyArray(): void
    {
        $query = $this->createMock(Query::class);
        $query->method('getResult')->willReturn([]);

        $qb = $this->createMock(QueryBuilder::class);
        $qb->method('orderBy')->willReturnSelf();
        $qb->method('getQuery')->willReturn($query);

        $repo = $this->getMockBuilder(FeedsRepository::class)
            ->setConstructorArgs([$this->registry])
            ->onlyMethods(['createQueryBuilder'])
            ->getMock();

        $repo->method('createQueryBuilder')->willReturn($qb);

        $result = $repo->findAllCustom();
        
        $this->assertIsArray($result);
        $this->assertEmpty($result);
    }

    public function testFindAllCustomOrdersByCreatedAtDesc(): void
    {
        $expectedFeeds = [new Feed()];
        
        $query = $this->createMock(Query::class);
        $query->method('getResult')->willReturn($expectedFeeds);

        $qb = $this->createMock(QueryBuilder::class);
        
        $qb->expects($this->once())
            ->method('orderBy')
            ->with('n.createdAt', 'DESC')
            ->willReturnSelf();
        
        $qb->method('getQuery')->willReturn($query);

        $repo = $this->getMockBuilder(FeedsRepository::class)
            ->setConstructorArgs([$this->registry])
            ->onlyMethods(['createQueryBuilder'])
            ->getMock();

        $repo->method('createQueryBuilder')->willReturn($qb);

        $result = $repo->findAllCustom();
        
        $this->assertSame($expectedFeeds, $result);
    }

    public function testFindAllCustomWithSinceAndSource(): void
    {
        $since = new \DateTime('2025-10-15');
        $source = 'RSS';
        $expectedFeeds = [new Feed()];
        
        $query = $this->createMock(Query::class);
        $query->method('getResult')->willReturn($expectedFeeds);

        $qb = $this->createMock(QueryBuilder::class);
        $qb->method('orderBy')->willReturnSelf();
        $qb->method('andWhere')->willReturnSelf();
        $qb->method('setParameter')->willReturnSelf();
        $qb->method('getQuery')->willReturn($query);

        $repo = $this->getMockBuilder(FeedsRepository::class)
            ->setConstructorArgs([$this->registry])
            ->onlyMethods(['createQueryBuilder'])
            ->getMock();

        $repo->method('createQueryBuilder')->willReturn($qb);

        // Verificar que andWhere fue llamado 2 veces
        $qb->expects($this->exactly(2))
            ->method('andWhere');

        // Verificar que setParameter fue llamado 3 veces (start, end, source)
        $qb->expects($this->exactly(3))
            ->method('setParameter');

        $result = $repo->findAllCustom(null, $since, $source);
        
        $this->assertSame($expectedFeeds, $result);
    }
}