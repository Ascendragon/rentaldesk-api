<?php
declare(strict_types=1);

namespace App\Controller;

use App\DTO\Request\CreateAssetRequest;
use App\DTO\Response\AssetResponse;
use App\Exception\InventoryItemNotFoundException;
use App\Service\AssetService;
use Doctrine\DBAL\Exception\UniqueConstraintViolationException;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\MapRequestPayload;
use Symfony\Component\Routing\Attribute\Route;

class AssetController extends AbstractController
{
    public function __construct(
        private AssetService $service
    )
    {
    }

    #[Route(
        '/api/inventory/{id}/assets',
        methods: ['POST'],
        format: 'json'
    )]
    public function register(
        int                $id,
        #[MapRequestPayload(acceptFormat: 'json')]
        CreateAssetRequest $request
    ): JsonResponse
    {
        $purchasedAt = $request->purchasedAt !== null
            ? \DateTimeImmutable::createFromFormat('!Y-m-d', $request->purchasedAt)
            : null;
        if (
            $purchasedAt === false ||
            (
                $purchasedAt !== null &&
                $purchasedAt->format('Y-m-d') !== $request->purchasedAt
            )
        ) {
            return $this->json(
                ['error' => 'Invalid purchase date'],
                Response::HTTP_UNPROCESSABLE_ENTITY
            );
        }
        $asset = $this->service->register(
            $id,
            $request->inventoryCode,
            $request->purchasePriceKopecks,
            $purchasedAt
        );


        return $this->json(AssetResponse::fromEntity($asset), Response::HTTP_CREATED);
    }
}
