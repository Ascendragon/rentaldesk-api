<?php

namespace App\Entity;

use App\Enums\AssetStatus;
use App\Repository\AssetRepository;
use DateTimeImmutable;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Bridge\Doctrine\Validator\Constraints\UniqueEntity;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity(repositoryClass: AssetRepository::class)]
#[UniqueEntity(
    fields: ['inventoryCode'],
    message: 'Инвентарный номер уже существует'
)]
class Asset
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: InventoryItem::class)]
    #[ORM\JoinColumn(
        name: 'inventory_item_id',
        referencedColumnName: 'id',
        nullable: false,
        onDelete: 'RESTRICT'
    )]
    private InventoryItem $inventoryItem;

    #[ORM\Column(length: 64, unique: true)]
    private string $inventoryCode;

    #[Orm\Column(
        type: Types::BIGINT,
        nullable: true
    )]
    #[Assert\PositiveOrZero]
    private ?int $purchasePriceKopecks = null;

    #[Orm\Column(
        type: Types::DATE_IMMUTABLE,
        nullable: true
    )]
    private ?DateTimeImmutable $purchasedAt = null;

    #[ORM\Column(
        type: Types::STRING,
        length: 30,
        enumType: AssetStatus::class
    )]
    private AssetStatus $status;

    public function __construct(
        InventoryItem $inventoryItem,
        string $inventoryCode,
        ?int $purchasePriceKopecks = null,
        ?DateTimeImmutable $purchasedAt = null
    )
    {
        $inventoryCode = trim($inventoryCode);
        if ($inventoryCode === '') {
            throw new \InvalidArgumentException('Inventory code cannot be empty.');
        }

        if (mb_strlen($inventoryCode) > 64) {
            throw new \InvalidArgumentException('Inventory code cannot exceed 64 characters.');
        }

        if ($purchasePriceKopecks !== null && $purchasePriceKopecks < 0) {
            throw new \InvalidArgumentException("Purchase price cannot be negative.");
        }

        $this->inventoryItem = $inventoryItem;
        $this->inventoryCode = $inventoryCode;
        $this->purchasePriceKopecks = $purchasePriceKopecks;
        $this->purchasedAt = $purchasedAt;
        $this->status = AssetStatus::ACTIVE;
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getInventoryItem(): InventoryItem
    {
        return $this->inventoryItem;
    }

    public function getInventoryCode(): string
    {
        return $this->inventoryCode;
    }

    public function getPurchasedAt(): ?DateTimeImmutable
    {
        return $this->purchasedAt;
    }

    public function getStatus(): AssetStatus
    {
        return $this->status;
    }

    public function getPurchasePriceKopecks(): ?int
    {
        return $this->purchasePriceKopecks;
    }

    public function sendToMaintenance(): void
    {
        if ($this->status !== AssetStatus::ACTIVE) {
            throw new \DomainException("Asset cannot be send to maintenance");
        }

        $this->status = AssetStatus::MAINTENANCE;
    }

    public function returnToService(): void
    {
        if ($this->status !== AssetStatus::MAINTENANCE) {
            throw new \DomainException("Asset cannot be return to service");
        }

        $this->status = AssetStatus::ACTIVE;
    }

    public function retire(): void
    {
        if ($this->status === AssetStatus::RETIRED) {
            throw new \DomainException("Asset already retired");
        }

        $this->status = AssetStatus::RETIRED;
    }
}
