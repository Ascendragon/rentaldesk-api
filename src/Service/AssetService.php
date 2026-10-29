<?php

namespace App\Service;

use App\Entity\Asset;
use App\Exception\InventoryItemNotFoundException;
use App\Repository\InventoryItemRepository;
use Doctrine\ORM\EntityManagerInterface;
use Exception;

class AssetService
{
    public function __construct(
        private InventoryItemRepository $repository,
        private EntityManagerInterface $em
    ) {}
    public function register(
        int $inventoryItemId,
        string $inventoryCode,
        ?int $purchasePriceKopecks = null,
        ?\DateTimeImmutable $purchasedAt = null
    ): Asset
    {
        $inventoryItem = $this->repository->find($inventoryItemId);

        if ($inventoryItem === null ) {
            throw new InventoryItemNotFoundException("Inventory item with id {$inventoryItemId} not found");
        }
        $asset = new Asset($inventoryItem, $inventoryCode, $purchasePriceKopecks, $purchasedAt);

        $this->em->persist($asset);
        $this->em->flush();
        return $asset;
    }
}
