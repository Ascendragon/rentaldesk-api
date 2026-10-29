<?php

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
        $data = [];
        $inventoryItems = $repository->findAll();

        foreach($inventoryItems as $item) {
            $data[] = [
                'id' => $item->getId(),
                'name' => $item->getName(),
                'stock' => $item->getStock(),
                'reserved' => $item->getReserved(),
                'available' => $item->getAvailableUnits()
            ];
        }

        return new JsonResponse($data, 200);
    }

    #[Route('/api/inventory', methods: ['POST'], format: 'json')]
    public function create(
        #[MapRequestPayload(acceptFormat: 'json')]
        CreateInventoryItemRequest $request,
    )
    {
        $item = $this->service->create($request->name, $request->stock);

        return $this->json(InventoryItemResponse::fromEntity($item), Response::HTTP_CREATED);
    }
}
