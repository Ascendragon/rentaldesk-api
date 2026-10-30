<?php
declare(strict_types=1);

namespace App\DTO\Response;

use App\Entity\Asset;

final readonly class AssetResponse
{
    public function __construct(
        public int $id,
        public int $inventoryItemId,
        public string $inventoryCode,
        public string $status,
        public ?int $purchasePriceKopecks = null,
        public ?string $purchasedAt = null,
    ) {}

    public static function fromEntity(Asset $asset): self
    {
        return new self(
            id: $asset->getId(),
            inventoryItemId: $asset->getInventoryItem()->getId(),
            inventoryCode: $asset->getInventoryCode(),
            status: $asset->getStatus()->value,
            purchasePriceKopecks: $asset->getPurchasePriceKopecks(),
            purchasedAt: $asset->getPurchasedAt()?->format('Y-m-d')
        );
    }
}
