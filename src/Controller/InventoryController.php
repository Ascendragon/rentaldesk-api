<?php
declare(strict_types=1);

namespace App\Controller;

use App\DTO\Request\CreateInventoryItemRequest;
use App\DTO\Response\InventoryItemResponse;
use App\Repository\InventoryItemRepository;
use App\Service\InventoryItemService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\MapRequestPayload;
use Symfony\Component\Routing\Attribute\Route;

class InventoryController extends AbstractController
{

    public function __construct(
        private InventoryItemService $service
    ){}

    #[Route('/api/inventory', methods: ['GET'])]
    public function index(InventoryItemRepository $repository): JsonResponse
    {
        $items = array_map(
            InventoryItemResponse::fromEntity(...),
            $repository->findAll()
        );

        return $this->json($items);
    }

    #[Route('/api/inventory', methods: ['POST'], format: 'json')]
    public function create(
        #[MapRequestPayload(acceptFormat: 'json')]
        CreateInventoryItemRequest $request,
    ): JsonResponse
    {
        $item = $this->service->create($request->name, $request->stock, $request->dailyRateKopecks);

        return $this->json(InventoryItemResponse::fromEntity($item), Response::HTTP_CREATED);
    }
}
