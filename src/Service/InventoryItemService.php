<?php

namespace App\Service;

use App\Entity\InventoryItem;
use App\Repository\InventoryItemRepository;
use Doctrine\ORM\EntityManagerInterface;

class InventoryItemService
{
    public function __construct(
        private EntityManagerInterface $em,
    ) {}

    public function create(
        string $name,
        int $stock,
    ) : InventoryItem
    {
        $item = new InventoryItem(trim($name), $stock);

        $this->em->persist($item);
        $this->em->flush();

        return $item;
    }
}
