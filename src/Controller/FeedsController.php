<?php

declare(strict_types=1);

namespace App\Controller;

use OpenApi\Attributes as OA;
use App\DTO\Feed\CreateFeedDTO;
use App\DTO\Feed\GetFeedsDTO;
use App\DTO\Feed\UpdateFeedDTO;
use App\Service\FeedsService;
use App\Exception\FeedNotFoundException;
use App\Exception\DuplicateFeedException;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Serializer\Normalizer\DenormalizerInterface;
use Symfony\Component\Validator\Validator\ValidatorInterface;

#[Route('/api/feeds', name: 'api_feeds_')]
final class FeedsController extends AbstractController
{
    public function __construct(
        private readonly FeedsService $feedsService,
        private readonly DenormalizerInterface $serializer,
        private readonly ValidatorInterface $validator,
    ) {
    }

    #[Route('', name: 'list', methods: ['GET'])]
    #[OA\Get(
        path: '/api/feeds',
        summary: 'Obtiene un listado de feeds',
        parameters: [
            new OA\Parameter(
                name: 'limit',
                in: 'query',
                description: 'Numero de resultados',
                required: false,
                schema: new OA\Schema(type: 'int')
            ),
            new OA\Parameter(
                name: 'date',
                in: 'query',
                description: 'Fecha de publicación',
                required: false,
                schema: new OA\Schema(type: 'string')
            ),
            new OA\Parameter(
                name: 'source',
                in: 'query',
                description: 'Fuente del feed',
                required: false,
                schema: new OA\Schema(type: 'string', enum: ['el_mundo', 'el_pais'])
            ),
        ],
        responses: [
            new OA\Response(response: 200, description: 'Listado de feeds'),
            new OA\Response(response: 400, description: 'Error en los datos de filtro'),
            new OA\Response(response: 500, description: 'Error en el intento de recuperar los feeds'),
        ],
        tags: ['Feeds']
    )]
    public function index(Request $request): JsonResponse
    {
        try {
            $sqlResult = $request->query->get('limit');
            $source = $request->query->get('source');
            $since = $request->query->get('date');

            $dto = $this->serializer->denormalize(['sqlResult' => $sqlResult, 'source' => $source, 'since' => $since], GetFeedsDTO::class);
            $errors = $this->validator->validate($dto);

            if (count($errors) > 0) {
                $errorMessages = [];
                foreach ($errors as $error) {
                    $errorMessages[] = $error->getMessage();
                }

                return $this->json(['error' => implode(' ', $errorMessages)], Response::HTTP_BAD_REQUEST);
            }


            $result = $this->feedsService->getAllFeeds(
                $dto
            );

            return $this->json([
                'success' => true,
                'data' => array_map(static fn ($dto) => $dto->toArray(), $result['data']),
            ]);
        } catch (\Exception $e) {
            return $this->json([
                'success' => false,
                'error' => 'Error en el intento de recuperar los feeds',
                'message' => $e->getMessage(),
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    #[Route('/{id}', name: 'show', requirements: ['id' => '\d+'], methods: ['GET'])]
    #[OA\Get(
        path: '/api/feeds/{id}',
        summary: 'Obtiene los detalles de un feed',
        parameters: [
            new OA\Parameter(
                name: 'id',
                in: 'path',
                description: 'Id del feed',
                required: false,
                schema: new OA\Schema(type: 'int')
            ),
        ],
        responses: [
            new OA\Response(response: 200, description: 'Detalle del feed'),
            new OA\Response(response: 404, description: 'El feed no ha sido encontrado'),
            new OA\Response(response: 500, description: 'Error en el intento de recuperar los feeds'),
        ],
        tags: ['Feeds']
    )]
    public function show(int $id): JsonResponse
    {
        try {
            $feed = $this->feedsService->getFeedById($id);

            return $this->json([
                'success' => true,
                'data' => $feed->toArray(),
            ]);
        } catch (FeedNotFoundException $e) {
            return $this->json([
                'success' => false,
                'error' => 'El feed no ha sido encontrado',
                'message' => $e->getMessage(),
            ], Response::HTTP_NOT_FOUND);
        } catch (\Exception $e) {
            return $this->json([
                'success' => false,
                'error' => 'Error en el intento de recuperar el feed',
                'message' => $e->getMessage(),
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }
}

