<?php

namespace App\Tests\Controller;

use App\Entity\Feed;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\HttpFoundation\Response;

class FeedsControllerTest extends WebTestCase
{
    private $client;
    private EntityManagerInterface $entityManager;

    protected function setUp(): void
    {
        $this->client = static::createClient();
        $this->entityManager = self::getContainer()->get('doctrine')->getManager();
        
        // Limpiar la base de datos antes de cada test
        $this->cleanDatabase();
    }

    protected function tearDown(): void
    {
        parent::tearDown();
        $this->cleanDatabase();
        $this->entityManager->close();
    }

    private function cleanDatabase(): void
    {
        $connection = $this->entityManager->getConnection();
        $connection->executeStatement('DELETE FROM feeds');
    }

    private function createFeed(array $data = []): Feed
    {
        $feed = new Feed();
        $feed->setTitle($data['title'] ?? 'Test Feed');
        $feed->setUrl($data['url'] ?? 'https://example.com/feed');
        $feed->setImageUrl($data['imageUrl'] ?? 'https://example.com/image.jpg');
        $feed->setSource($data['source'] ?? 'el_mundo');
        $reflection = new \ReflectionClass($feed);
        $idProperty = $reflection->getProperty('id');
        $idProperty->setAccessible(true);
        $idProperty->setValue($feed, 1); 
        
        $feed->setCreatedAt(new \DateTime());
        $feed->setUpdatedAt(new \DateTime());

        $this->entityManager->persist($feed);
        $this->entityManager->flush();

        return $feed;
    }

    // ==================== INDEX TESTS ====================

    public function testIndexReturnsListOfFeeds(): void
    {
        // Crear varios feeds
        $this->createFeed(['title' => 'Feed 1', 'url' => 'https://example.com/feed1']);
        $this->createFeed(['title' => 'Feed 2', 'url' => 'https://example.com/feed2']);
        $this->createFeed(['title' => 'Feed 3', 'url' => 'https://example.com/feed3']);

        $this->client->request('GET', '/api/feeds');

        $this->assertResponseIsSuccessful();
        $this->assertResponseStatusCodeSame(Response::HTTP_OK);

        $responseData = json_decode($this->client->getResponse()->getContent(), true);

        $this->assertTrue($responseData['success']);
        $this->assertArrayHasKey('data', $responseData);
        $this->assertCount(3, $responseData['data']);
    }

    public function testIndexReturnsEmptyArrayWhenNoFeeds(): void
    {
        $this->client->request('GET', '/api/feeds');

        $this->assertResponseIsSuccessful();
        
        $responseData = json_decode($this->client->getResponse()->getContent(), true);

        $this->assertTrue($responseData['success']);
        $this->assertEmpty($responseData['data']);
    }

    public function testIndexWithLimitParameter(): void
    {
        // Crear 5 feeds
        for ($i = 1; $i <= 5; $i++) {
            $this->createFeed([
                'title' => "Feed {$i}",
                'url' => "https://example.com/feed{$i}"
            ]);
        }

        $this->client->request('GET', '/api/feeds?limit=3');

        $this->assertResponseIsSuccessful();
        
        $responseData = json_decode($this->client->getResponse()->getContent(), true);

        $this->assertTrue($responseData['success']);
        $this->assertCount(3, $responseData['data']);
    }

    public function testIndexWithSourceFilter(): void
    {
        $this->createFeed(['source' => 'el_mundo', 'url' => 'https://example.com/feed1']);
        $this->createFeed(['source' => 'el_pais', 'url' => 'https://example.com/feed2']);
        $this->createFeed(['source' => 'el_mundo', 'url' => 'https://example.com/feed3']);

        $this->client->request('GET', '/api/feeds?source=el_mundo');

        $this->assertResponseIsSuccessful();
        
        $responseData = json_decode($this->client->getResponse()->getContent(), true);

        $this->assertTrue($responseData['success']);
        $this->assertCount(2, $responseData['data']);
        
        foreach ($responseData['data'] as $feed) {
            $this->assertEquals('el_mundo', $feed['source']);
        }
    }

    public function testIndexWithDateFilter(): void
    {
        $today = new \DateTime('today');
        $yesterday = new \DateTime('yesterday');

        $this->createFeed([
            'title' => 'Today Feed',
            'url' => 'https://example.com/today',
            'createdAt' => $today
        ]);
        
        $this->createFeed([
            'title' => 'Yesterday Feed',
            'url' => 'https://example.com/yesterday',
            'createdAt' => $yesterday
        ]);

        $this->client->request('GET', '/api/feeds?date=' . $today->format('Y-m-d'));

        $this->assertResponseIsSuccessful();
        
        $responseData = json_decode($this->client->getResponse()->getContent(), true);

        $this->assertTrue($responseData['success']);
        $this->assertCount(2, $responseData['data']);
        $this->assertEquals('Today Feed', $responseData['data'][0]['title']);
    }

    public function testIndexWithInvalidDateReturnsError(): void
    {
        $this->client->request('GET', '/api/feeds?date=invalid-date');

        $this->assertResponseStatusCodeSame(Response::HTTP_BAD_REQUEST);
        
        $responseData = json_decode($this->client->getResponse()->getContent(), true);

        $this->assertArrayHasKey('error', $responseData);
    }

    public function testIndexWithAllFilters(): void
    {
        $today = new \DateTime('today');

        for ($i = 1; $i <= 10; $i++) {
            $this->createFeed([
                'title' => "Feed {$i}",
                'url' => "https://example.com/feed{$i}",
                'source' => $i % 2 === 0 ? 'el_mundo' : 'el_pais',
                'createdAt' => $i <= 5 ? $today : new \DateTime('yesterday')
            ]);
        }

        $this->client->request('GET', sprintf(
            '/api/feeds?limit=2&source=el_mundo&date=%s',
            $today->format('Y-m-d')
        ));

        $this->assertResponseIsSuccessful();
        
        $responseData = json_decode($this->client->getResponse()->getContent(), true);

        $this->assertTrue($responseData['success']);
        $this->assertCount(2, $responseData['data']);
        
        foreach ($responseData['data'] as $feed) {
            $this->assertEquals('el_mundo', $feed['source']);
        }
    }

    // ==================== SHOW TESTS ====================

    public function testShowReturnsFeedException(): void
    {
        $feed = $this->createFeed([
            'title' => 'Test Feed Show',
            'url' => 'https://example.com/show'
        ]);

        $this->client->request('GET', '/api/feeds/' . $feed->getId());

        $this->assertResponseIsSuccessful();
        
        $responseData = json_decode($this->client->getResponse()->getContent(), true);

        $this->assertTrue($responseData['success']);
        $this->assertEquals('Test Feed Show', $responseData['data']['title']);
        $this->assertEquals('https://example.com/show', $responseData['data']['url']);
    }

    public function testShowReturnsNotFoundWhenFeedDoesNotExist(): void
    {
        $this->client->request('GET', '/api/feeds/99999');

        $this->assertResponseStatusCodeSame(Response::HTTP_NOT_FOUND);
        
        $responseData = json_decode($this->client->getResponse()->getContent(), true);

        $this->assertFalse($responseData['success']);
        $this->assertEquals('El feed no ha sido encontrado', $responseData['error']);
    }

    // ==================== CREATE TESTS ====================

    public function testCreateFeedSuccessfully(): void
    {
        $feedData = [
            'title' => 'New Feed',
            'url' => 'https://example.com/new',
            'imageUrl' => 'https://example.com/new.jpg',
            'source' => 'el_pais'
        ];

        $this->client->request(
            'POST',
            '/api/feeds',
            [],
            [],
            ['CONTENT_TYPE' => 'application/json'],
            json_encode($feedData)
        );

        $this->assertResponseStatusCodeSame(Response::HTTP_CREATED);
        
        $responseData = json_decode($this->client->getResponse()->getContent(), true);

        $this->assertTrue($responseData['success']);
        $this->assertEquals('El Feed ha sido correctamente creado', $responseData['message']);
        $this->assertEquals('New Feed', $responseData['data']['title']);
        $this->assertEquals('https://example.com/new', $responseData['data']['url']);
    }

    public function testCreateFeedWithMissingTitleReturnsError(): void
    {
        $feedData = [
            'url' => 'https://example.com/new',
            'source' => 'el_pais'
        ];

        $this->client->request(
            'POST',
            '/api/feeds',
            [],
            [],
            ['CONTENT_TYPE' => 'application/json'],
            json_encode($feedData)
        );

        $this->assertResponseStatusCodeSame(Response::HTTP_BAD_REQUEST);
        
        $responseData = json_decode($this->client->getResponse()->getContent(), true);

        $this->assertArrayHasKey('error', $responseData);
    }

    public function testCreateFeedWithMissingUrlReturnsError(): void
    {
        $feedData = [
            'title' => 'New Feed',
            'source' => 'el_pais'
        ];

        $this->client->request(
            'POST',
            '/api/feeds',
            [],
            [],
            ['CONTENT_TYPE' => 'application/json'],
            json_encode($feedData)
        );

        $this->assertResponseStatusCodeSame(Response::HTTP_BAD_REQUEST);
        
        $responseData = json_decode($this->client->getResponse()->getContent(), true);

        $this->assertArrayHasKey('error', $responseData);
    }

    public function testCreateFeedWithDuplicateUrlReturnsConflict(): void
    {
        $this->createFeed(['url' => 'https://example.com/duplicate']);

        $feedData = [
            'title' => 'Duplicate Feed',
            'url' => 'https://example.com/duplicate',
            'source' => 'el_mundo'
        ];

        $this->client->request(
            'POST',
            '/api/feeds',
            [],
            [],
            ['CONTENT_TYPE' => 'application/json'],
            json_encode($feedData)
        );

        $this->assertResponseStatusCodeSame(Response::HTTP_CONFLICT);
        
        $responseData = json_decode($this->client->getResponse()->getContent(), true);

        $this->assertFalse($responseData['success']);
        $this->assertEquals('El feed esta duplicado, no se ha creado', $responseData['error']);
    }

    public function testCreateFeedWithInvalidJsonReturnsError(): void
    {
        $this->client->request(
            'POST',
            '/api/feeds',
            [],
            [],
            ['CONTENT_TYPE' => 'application/json'],
            'invalid-json'
        );

        $this->assertResponseStatusCodeSame(Response::HTTP_BAD_REQUEST);
        
        $responseData = json_decode($this->client->getResponse()->getContent(), true);

        $this->assertFalse($responseData['success']);
        $this->assertEquals('JSON incorrecto', $responseData['error']);
    }

    // ==================== UPDATE TESTS ====================

    public function testUpdateFeedSuccessfully(): void
    {
        $feed = $this->createFeed([
            'title' => 'Original Title',
            'url' => 'https://example.com/original'
        ]);

        $updateData = [
            'title' => 'Updated Title',
            'url' => 'https://example.com/updated',
            'imageUrl' => 'https://example.com/updated.jpg',
            'source' => 'el_pais'
        ];

        $this->client->request(
            'PUT',
            '/api/feeds/' . $feed->getId(),
            [],
            [],
            ['CONTENT_TYPE' => 'application/json'],
            json_encode($updateData)
        );

        $this->assertResponseIsSuccessful();
        
        $responseData = json_decode($this->client->getResponse()->getContent(), true);

        $this->assertTrue($responseData['success']);
        $this->assertEquals('El feed ha sido correctamente actualizado', $responseData['message']);
        $this->assertEquals('Updated Title', $responseData['data']['title']);
        $this->assertEquals('https://example.com/updated', $responseData['data']['url']);
    }

    public function testUpdateNonExistentFeedReturnsNotFound(): void
    {
        $updateData = [
            'title' => 'Updated Title',
            'url' => 'https://example.com/updated'
        ];

        $this->client->request(
            'PUT',
            '/api/feeds/99999',
            [],
            [],
            ['CONTENT_TYPE' => 'application/json'],
            json_encode($updateData)
        );

        $this->assertResponseStatusCodeSame(Response::HTTP_NOT_FOUND);
        
        $responseData = json_decode($this->client->getResponse()->getContent(), true);

        $this->assertFalse($responseData['success']);
        $this->assertEquals('Feed no encontrado', $responseData['error']);
    }

    public function testUpdateFeedWithDuplicateUrlReturnsConflict(): void
    {
        $feed1 = $this->createFeed(['url' => 'https://example.com/feed1']);
        $feed2 = $this->createFeed(['url' => 'https://example.com/feed2']);

        $updateData = [
            'title' => 'Updated Feed',
            'url' => 'https://example.com/feed1' // URL ya existe en feed1
        ];

        $this->client->request(
            'PUT',
            '/api/feeds/' . $feed2->getId(),
            [],
            [],
            ['CONTENT_TYPE' => 'application/json'],
            json_encode($updateData)
        );

        $this->assertResponseStatusCodeSame(Response::HTTP_CONFLICT);
        
        $responseData = json_decode($this->client->getResponse()->getContent(), true);

        $this->assertFalse($responseData['success']);
        $this->assertEquals('Esa url ya existe en otro feed, no se ha podido actualizar', $responseData['error']);
    }

    public function testUpdateFeedWithInvalidDataReturnsError(): void
    {
        $feed = $this->createFeed();

        $updateData = [
            'title' => '', // Título vacío
            'url' => ''
        ];

        $this->client->request(
            'PUT',
            '/api/feeds/' . $feed->getId(),
            [],
            [],
            ['CONTENT_TYPE' => 'application/json'],
            json_encode($updateData)
        );

        $this->assertResponseStatusCodeSame(Response::HTTP_BAD_REQUEST);
        
        $responseData = json_decode($this->client->getResponse()->getContent(), true);

        $this->assertArrayHasKey('error', $responseData);
    }

    public function testUpdateFeedWithInvalidJsonReturnsError(): void
    {
        $feed = $this->createFeed();

        $this->client->request(
            'PUT',
            '/api/feeds/' . $feed->getId(),
            [],
            [],
            ['CONTENT_TYPE' => 'application/json'],
            'invalid-json'
        );

        $this->assertResponseStatusCodeSame(Response::HTTP_BAD_REQUEST);
        
        $responseData = json_decode($this->client->getResponse()->getContent(), true);

        $this->assertFalse($responseData['success']);
        $this->assertEquals('JSON incorrecto', $responseData['error']);
    }

    // ==================== DELETE TESTS ====================

    public function testDeleteFeedSuccessfully(): void
    {
        $feed = $this->createFeed(['title' => 'Feed to Delete']);
        $id = $feed->getId();
        $this->client->request('DELETE', '/api/feeds/' . $feed->getId());

        $this->assertResponseIsSuccessful();
        
        $responseData = json_decode($this->client->getResponse()->getContent(), true);

        $this->assertTrue($responseData['success']);
        $this->assertEquals('El feed ha sido correctamente eliminado', $responseData['message']);

        // Verificar que el feed fue eliminado
        $deletedFeed = $this->entityManager->getRepository(Feed::class)->find($id);
        $this->assertNull($deletedFeed);
    }

    public function testDeleteNonExistentFeedReturnsNotFound(): void
    {
        $this->client->request('DELETE', '/api/feeds/99999');

        $this->assertResponseStatusCodeSame(Response::HTTP_NOT_FOUND);
        
        $responseData = json_decode($this->client->getResponse()->getContent(), true);

        $this->assertFalse($responseData['success']);
        $this->assertEquals('Feed no encontrado', $responseData['error']);
    }

    // ==================== INTEGRATION TESTS ====================

    public function testCompleteWorkflow(): void
    {
        // 1. Crear un feed
        $feedData = [
            'title' => 'Workflow Feed',
            'url' => 'https://example.com/workflow',
            'imageUrl' => 'https://example.com/workflow.jpg',
            'source' => 'el_mundo'
        ];

        $this->client->request(
            'POST',
            '/api/feeds',
            [],
            [],
            ['CONTENT_TYPE' => 'application/json'],
            json_encode($feedData)
        );

        $this->assertResponseStatusCodeSame(Response::HTTP_CREATED);
        $createResponse = json_decode($this->client->getResponse()->getContent(), true);
        $feedId = $createResponse['data']['id'];

        // 2. Obtener el feed creado
        $this->client->request('GET', '/api/feeds/' . $feedId);
        $this->assertResponseIsSuccessful();
        $showResponse = json_decode($this->client->getResponse()->getContent(), true);
        $this->assertEquals('Workflow Feed', $showResponse['data']['title']);

        // 3. Actualizar el feed
        $updateData = [
            'title' => 'Updated Workflow Feed',
            'url' => 'https://example.com/workflow-updated',
            'source' => 'el_pais'
        ];

        $this->client->request(
            'PUT',
            '/api/feeds/' . $feedId,
            [],
            [],
            ['CONTENT_TYPE' => 'application/json'],
            json_encode($updateData)
        );

        $this->assertResponseIsSuccessful();
        $updateResponse = json_decode($this->client->getResponse()->getContent(), true);
        $this->assertEquals('Updated Workflow Feed', $updateResponse['data']['title']);

        // 4. Listar feeds
        $this->client->request('GET', '/api/feeds');
        $this->assertResponseIsSuccessful();
        $listResponse = json_decode($this->client->getResponse()->getContent(), true);
        $this->assertGreaterThanOrEqual(1, count($listResponse['data']));

        // 5. Eliminar el feed
        $this->client->request('DELETE', '/api/feeds/' . $feedId);
        $this->assertResponseIsSuccessful();

        // 6. Verificar que no existe
        $this->client->request('GET', '/api/feeds/' . $feedId);
        $this->assertResponseStatusCodeSame(Response::HTTP_NOT_FOUND);
    }
}