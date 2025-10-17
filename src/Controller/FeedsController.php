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


    #[Route('', name: 'create', methods: ['POST'])]
    #[OA\Post(
        path: '/api/feeds',
        summary: 'Crea un feed',
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                type: 'object',
                properties: [
                    new OA\Property(property: 'title', type: 'string', description: 'Titulo del feed'),
                    new OA\Property(property: 'url', type: 'string', description: 'Url del feed'),
                    new OA\Property(property: 'imageUrl', type: 'string', description: 'Url de la imagen del feed'),
                    new OA\Property(property: 'source', type: 'string', description: 'Fuente del feed'),
                ]
            )
        ),
        responses: [
            new OA\Response(response: 200, description: 'El Feed ha sido correctamente creado'),
            new OA\Response(response: 409, description: 'El feed esta duplicado, no se ha creado'),
            new OA\Response(response: 500, description: 'Error al crear el feed'),
            new OA\Response(
                response: 400,
                description: 'Error en los parametros de entradas',
                content: new OA\JsonContent(
                    type: 'object',
                    properties: [
                        new OA\Property(property: 'error', type: 'string'),
                        new OA\Property(property: 'code', type: 'integer'),
                    ],
                    examples: [
                        new OA\Examples(
                            'El título no puede estar vacío',
                            'El título no puede estar vacío',
                            null,
                            ['error' => 'El título no puede estar vacío La URL no puede estar vacía', 'code' => 404]
                        ),
                    ]
                )
            ),
        ],
        tags: ['Feeds']
    )]
    public function create(Request $request): JsonResponse
    {
        try {
            $data = json_decode($request->getContent(), true);

            if (JSON_ERROR_NONE !== json_last_error()) {
                return $this->json([
                    'success' => false,
                    'error' => 'JSON incorrecto',
                    'message' => json_last_error_msg(),
                ], Response::HTTP_BAD_REQUEST);
            }

            $dto = $this->serializer->denormalize(
                $data,
                CreateFeedDTO::class
            );


            $errors = $this->validator->validate($dto);


            if (count($errors) > 0) {
                $errorMessages = [];
                foreach ($errors as $error) {
                    $errorMessages[] = $error->getMessage();
                }

                return $this->json(['error' => implode(' ', $errorMessages)], Response::HTTP_BAD_REQUEST);
            }


            $feed = $this->feedsService->createFeed($dto);

            return $this->json([
                'success' => true,
                'message' => 'El Feed ha sido correctamente creado',
                'data' => $feed->toArray(),
            ], Response::HTTP_CREATED);
        } catch (DuplicateFeedException $e) {
            return $this->json([
                'success' => false,
                'error' => 'El feed esta duplicado, no se ha creado',
                'message' => $e->getMessage(),
            ], Response::HTTP_CONFLICT);
        } catch (\Exception $e) {
            return $this->json([
                'success' => false,
                'error' => 'Error al crear el feed',
                'message' => $e->getMessage(),
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }

    }

    #[Route('/{id}', name: 'delete', requirements: ['id' => '\d+'], methods: ['DELETE'])]
    #[OA\Delete(
        path: '/api/feeds/{id}',
        summary: 'Elimina un feed',
        parameters: [
            new OA\Parameter(
                name: 'id',
                in: 'path',
                description: 'id del feed',
                required: false,
                schema: new OA\Schema(type: 'int')
            ),
        ],
        responses: [
            new OA\Response(response: 200, description: 'El feed ha sido correctamente eliminado'),
            new OA\Response(response: 404, description: 'Feed no encontrado'),
            new OA\Response(response: 500, description: 'Error al eliminar el feed'),
        ],
        tags: ['Feeds']
    )]
    public function delete(int $id): JsonResponse
    {
        try {
            $this->feedsService->deleteFeed($id);

            return $this->json([
                'success' => true,
                'message' => 'El feed ha sido correctamente eliminado',
            ]);
        } catch (FeedNotFoundException $e) {
            return $this->json([
                'success' => false,
                'error' => 'Feed no encontrado',
                'message' => $e->getMessage(),
            ], Response::HTTP_NOT_FOUND);
        } catch (\Exception $e) {
            return $this->json([
                'success' => false,
                'error' => 'Error al eliminar el feed',
                'message' => $e->getMessage(),
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    #[Route('/{id}', name: 'update', requirements: ['id' => '\d+'], methods: ['PUT'])]
    #[OA\Put(
        path: '/api/feeds/{id}',
        summary: 'Actualiza un feed',
        parameters: [
            new OA\Parameter(
                name: 'id',
                in: 'path',
                description: 'id del feed',
                required: false,
                schema: new OA\Schema(type: 'int')
            ),
        ],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                type: 'object',
                properties: [
                    new OA\Property(property: 'title', type: 'string', description: 'Titulo del feed'),
                    new OA\Property(property: 'url', type: 'string', description: 'Url del feed'),
                    new OA\Property(property: 'imageUrl', type: 'string', description: 'Url de la imagen del feed'),
                    new OA\Property(property: 'source', type: 'string', description: 'Fuente del feed'),
                ]
            )
        ),
        responses: [
            new OA\Response(response: 200, description: 'El feed ha sido correctamente actualizado'),
            new OA\Response(response: 404, description: 'Feed no encontrado'),
            new OA\Response(response: 409, description: 'Esa url ya existe en otro feed, no se ha podido actualizar'),
            new OA\Response(response: 500, description: 'Error al actualizar el feed'),
            new OA\Response(
                response: 400,
                description: 'Error en los parametros de entradas',
                content: new OA\JsonContent(
                    type: 'object',
                    properties: [
                        new OA\Property(property: 'error', type: 'string'),
                        new OA\Property(property: 'code', type: 'integer'),
                    ],
                    examples: [
                        new OA\Examples(
                            'El título no puede estar vacío',
                            'El título no puede estar vacío',
                            null,
                            ['error' => 'El título no puede estar vacío La URL no puede estar vacía', 'code' => 404]
                        ),
                    ]
                )
            ),
        ],
        tags: ['Feeds']
    )]
    public function update(int $id, Request $request): JsonResponse
    {
        try {
            $data = json_decode($request->getContent(), true);

            if (JSON_ERROR_NONE !== json_last_error()) {
                return $this->json([
                    'success' => false,
                    'error' => 'JSON incorrecto',
                    'message' => json_last_error_msg(),
                ], Response::HTTP_BAD_REQUEST);
            }

            $dto = $this->serializer->denormalize(
                $data,
                UpdateFeedDTO::class,
            );


            $errors = $this->validator->validate($dto);

            if (count($errors) > 0) {
                $errorMessages = [];
                foreach ($errors as $error) {
                    $errorMessages[] = $error->getMessage();
                }

                return $this->json(['error' => implode(' ', $errorMessages)], Response::HTTP_BAD_REQUEST);
            }

            $feed = $this->feedsService->updateFeed($id, $dto);

            return $this->json([
                'success' => true,
                'message' => 'El feed ha sido correctamente actualizado',
                'data' => $feed->toArray(),
            ]);
        } catch (FeedNotFoundException $e) {
            return $this->json([
                'success' => false,
                'error' => 'Feed no encontrado',
                'message' => $e->getMessage(),
            ], Response::HTTP_NOT_FOUND);
        } catch (DuplicateFeedException $e) {
            return $this->json([
                'success' => false,
                'error' => 'Esa url ya existe en otro feed, no se ha podido actualizar',
                'message' => $e->getMessage(),
            ], Response::HTTP_CONFLICT);
        } catch (\Exception $e) {
            return $this->json([
                'success' => false,
                'error' => 'Error al actualizar el feed',
                'message' => $e->getMessage(),
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }    
}

