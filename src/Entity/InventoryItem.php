<?php
declare(strict_types=1);

namespace App\Entity;

use App\Repository\InventoryItemRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: InventoryItemRepository::class)]
class InventoryItem
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 128)]
    private string $name;

    #[ORM\Column]
    private int $stock;

    #[ORM\Column]
    private int $reserved;

    #[ORM\Column]
    private int $dailyRateKopecks;

    public function __construct(
        string $name,
        int $stock,
        int $dailyRateKopecks = 0,
        int $reserved = 0
    )
    {
        if (trim($name) === '') {
            throw new \InvalidArgumentException(
                'Equipment name cannot be empty'
            );
        }
        if ($stock < 0 || $reserved < 0 || $reserved > $stock) {
            throw new \InvalidArgumentException(
                'Invalid inventory quantities'
            );
        }

        $this->name = $name;
        $this->stock = $stock;
        $this->reserved = $reserved;
        $this->dailyRateKopecks = $dailyRateKopecks;
    }

    public function getDailyRateKopecks(): int
    {
        return $this->dailyRateKopecks;
    }
    public function getAvailableUnits(): int
    {
        return $this->stock - $this->reserved;
    }

    public function canReserve(int $quantity): bool
    {
        if ($quantity <= 0) {
            return false;
        }
        return $this->getAvailableUnits() >= $quantity;
    }

    public function reserve(int $quantity): bool
    {
        if (!$this->canReserve($quantity)) {
            return false;
        }
        $this->reserved += $quantity;
        return true;
    }

    public function canRelease(int $quantity): bool
    {
        if ($quantity <= 0) {
            return false;
        }

        if($quantity > $this->reserved) {
            return false;
        }
        return true;
    }

    public function release(int $quantity): bool
    {
        if (!$this->canRelease($quantity)) {
            return false;
        }
        $this->reserved -= $quantity;
        return true;
    }

    public function rename(string $name): void
    {
        if (trim($name) === '') {
            throw new \InvalidArgumentException(
                'Equipment name cannot be empty'
            );
        }
        $this->name = $name;
    }
    public function getId(): ?int
    {
        return $this->id;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function getStock(): int
    {
        return $this->stock;
    }

    public function getReserved(): int
    {
        return $this->reserved;
    }

}
