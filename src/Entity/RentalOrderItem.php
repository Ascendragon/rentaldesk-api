<?php
declare(strict_types=1);

namespace App\Entity;

use App\Repository\RentalOrderItemRepository;
use DateInterval;
use DateTimeImmutable;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: RentalOrderItemRepository::class)]
class RentalOrderItem
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: RentalOrder::class, inversedBy: 'items')]
    #[ORM\JoinColumn(
        name: 'order_id',
        referencedColumnName: 'id',
        nullable: false,
        onDelete: 'RESTRICT'
    )]
    private RentalOrder $rentalOrder;

    #[ORM\ManyToOne(targetEntity: InventoryItem::class)]
    #[ORM\JoinColumn(nullable: false, onDelete: 'RESTRICT')]
    private InventoryItem $inventoryItem;

    #[ORM\Column]
    private int $quantity;

    #[ORM\Column(type: Types::DATETIMETZ_IMMUTABLE)]
    private DateTimeImmutable $startsAt;

    #[ORM\Column(type: Types::DATETIMETZ_IMMUTABLE)]
    private DateTimeImmutable $endsAt;

    #[ORM\Column]
    private int $dailyRateKopecks;

    /**
     * @internal Use RentalOrder::addItem() to create order items.
     */
    public function __construct(
        RentalOrder        $rentalOrder,
        InventoryItem      $inventoryItem,
        int                $quantity,
        \DateTimeImmutable $startsAt,
        int $rentalDays,
        int                $dailyRateKopecks,
    )
    {
        $this->rentalOrder = $rentalOrder;
        $this->inventoryItem = $inventoryItem;

        if ($rentalDays <= 0) {
            throw new \InvalidArgumentException(
                'Rental days must be positive.'
            );
        }
        $startsAtUtc = $startsAt->setTimezone(
            new \DateTimeZone('UTC')
        );
        $endsAt = $startsAtUtc->add(
            new DateInterval("P{$rentalDays}D")
        );

        if ($quantity <= 0) {
            throw new \InvalidArgumentException('Quantity must be positive');
        }

        if ($dailyRateKopecks < 0 ) {
            throw new \InvalidArgumentException("DailyRateKopecks cannot be negative");
        }
        $this->quantity = $quantity;
        $this->startsAt = $startsAtUtc;
        $this->endsAt = $endsAt;
        $this->dailyRateKopecks = $dailyRateKopecks;

    }

    public function getRentalDays(): int
    {
        return $this->startsAt->diff($this->endsAt)->days;
    }
    public function getTotalPriceKopecks(): int
    {
        return $this->quantity
            * $this->getRentalDays()
            * $this->dailyRateKopecks;
    }
    public function getId(): ?int
    {
        return $this->id;
    }

    public function getQuantity(): int
    {
        return $this->quantity;
    }

    public function getStartsAt(): DateTimeImmutable
    {
        return $this->startsAt;
    }

    public function getEndsAt(): DateTimeImmutable
    {
        return $this->endsAt;
    }

    public function getDailyRateKopecks(): int
    {
        return $this->dailyRateKopecks;
    }

    public function getRentalOrder(): RentalOrder
    {
        return $this->rentalOrder;
    }

    public function getInventoryItem(): InventoryItem
    {
        return $this->inventoryItem;
    }
}
