<?php
declare(strict_types=1);

namespace App\Service;

use App\Entity\Asset;
use App\Exception\InventoryCodeAlreadyExistsException;
use App\Exception\InventoryItemNotFoundException;
use App\Repository\InventoryItemRepository;
use Doctrine\DBAL\Exception\UniqueConstraintViolationException;
use Doctrine\ORM\EntityManagerInterface;

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

        try {
            $this->em->persist($asset);
            $this->em->flush();
        } catch (UniqueConstraintViolationException $e) {
            throw new InventoryCodeAlreadyExistsException(
                'Inventory code already exists.',
                previous: $e
            );
        }

        return $asset;
    }
}
