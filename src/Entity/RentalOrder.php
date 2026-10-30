<?php
declare(strict_types=1);

namespace App\Entity;

use App\Enums\RentalOrderStatus;
use App\Repository\RentalOrderRepository;
use DateTimeImmutable;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
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

    #[ORM\Column]
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

    public function addItem(RentalOrderItem $item): void
    {
        if ($this->status !== RentalOrderStatus::DRAFT) {
            throw new \DomainException('Cannot modify a non-draft order.');
        }

        if ($item->getRentalOrder() !== $this) {
            throw new \InvalidArgumentException('Order item belongs to another order.');
        }

        if (!$this->items->contains($item)) {
            $this->items->add($item);
        }
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
