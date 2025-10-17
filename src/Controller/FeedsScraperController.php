<?php

declare(strict_types=1);

namespace App\Controller;

use OpenApi\Attributes as OA;
use App\Service\FeedsScraperService;
use Psr\Log\LoggerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

#[Route('/api/feeds', name: 'api_feeds_')]
final class FeedsScraperController extends AbstractController
{
    public function __construct(
        private readonly FeedsScraperService $feedsScraperService,
        private readonly LoggerInterface $logger,
    ) {
    }

    /**
     * Ejecuta el scraping de todas las fuentes.
     */
    #[Route('/scrape', name: 'scrape', methods: ['POST'])]
    #[OA\Post(
        path: '/api/feeds/scrape',
        summary: 'Scraping web de las ultimas 5 notiicias de el mundo y el pais',
        responses: [
            new OA\Response(response: 200, description: 'Scraping completado'),
            new OA\Response(response: 500, description: 'Error durante el scraping'),
        ],
        tags: ['Scrapping']
    )]
    public function scrape(): JsonResponse
    {
        try {
            $this->logger->info('Iniciando scraping manual desde API');

            $stats = $this->feedsScraperService->scrapeAllSources(5);

            return $this->json([
                'success' => true,
                'message' => 'Scraping completado',
                'stats' => $stats->toArray(),
            ], Response::HTTP_OK);

        } catch (\Exception $e) {
            $this->logger->error('Error en endpoint de scraping', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return $this->json([
                'success' => false,
                'message' => 'Error durante el scraping',
                'error' => $e->getMessage(),
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }
}
