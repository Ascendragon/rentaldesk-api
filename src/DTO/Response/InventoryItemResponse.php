<?php
declare(strict_types=1);

namespace App\DTO\Response;

use App\Entity\InventoryItem;

final readonly class InventoryItemResponse
{
    public function __construct(
        public int $id,
        public string $name,
        public int $stock,
        public int $reserved,
        public int $available,
    ) {}

    public static function fromEntity(InventoryItem $item): self
    {
        return new self(
            id: $item->getId(),
            name: $item->getName(),
            stock: $item->getStock(),
            reserved: $item->getReserved(),
            available: $item->getAvailableUnits()
        );
    }
}
