<?php
declare(strict_types=1);

namespace App\Entity;

use App\Enums\RentalOrderStatus;
use App\Exception\RentalOrderNotEditableException;
use App\Exception\RentalRateNotConfiguredException;
use App\Repository\RentalOrderRepository;
use DateTimeImmutable;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: RentalOrderRepository::class)]
class RentalOrder
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(enumType: RentalOrderStatus::class)]
    private RentalOrderStatus $status;

    #[ORM\Column(type: Types::DATETIMETZ_IMMUTABLE)]
    private DateTimeImmutable $createdAt;

    /** @var Collection<int, RentalOrderItem> */
    #[ORM\OneToMany(
        targetEntity: RentalOrderItem::class,
        mappedBy: 'rentalOrder',
        cascade: ['persist']
    )]
    private Collection $items;

    public function __construct(
    ) {
        $this->status = RentalOrderStatus::DRAFT;
        $this->createdAt = new DateTimeImmutable();
        $this->items = new ArrayCollection();
    }

    public function addItem(InventoryItem $inventoryItem, int $quantity, DateTimeImmutable $startsAt, int $rentalDays): RentalOrderItem
    {
        if ($this->status !== RentalOrderStatus::DRAFT) {
            throw new RentalOrderNotEditableException('Cannot modify a non-draft order.');
        }
        if ($inventoryItem->getDailyRateKopecks() <= 0) {
            throw new RentalRateNotConfiguredException("Cannot add item with zero daily rate.");
        }
        $orderItem = new RentalOrderItem($this, $inventoryItem, $quantity, $startsAt, $rentalDays, $inventoryItem->getDailyRateKopecks());
        $this->items->add($orderItem);
        return $orderItem;
    }
    /** @return list<RentalOrderItem> */
    public function getItems(): array
    {
        return array_values($this->items->toArray());
    }
    public function getTotalPriceKopecks(): int
    {
        $totalPrice = 0;
        foreach ($this->items as $item) {
            $totalPrice += $item->getTotalPriceKopecks();
        }
        return $totalPrice;
    }
    public function getId(): ?int
    {
        return $this->id;
    }

    public function getStatus(): RentalOrderStatus
    {
        return $this->status;
    }

    public function getCreatedAt(): DateTimeImmutable
    {
        return $this->createdAt;
    }
}
