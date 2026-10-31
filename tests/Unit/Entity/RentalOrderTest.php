<?php
declare(strict_types=1);

namespace App\Tests\Unit\Entity;

use App\Entity\InventoryItem;
use App\Entity\RentalOrder;
use App\Enums\RentalOrderStatus;
use PHPUnit\Framework\TestCase;

final class RentalOrderTest extends TestCase
{
    public function testOrderWithMultipleItemsCalculateTotal(): void
    {
        $order = new RentalOrder();

        $makita = new InventoryItem('Перфоратор Makita', 2, 100_000);
        $bosch = new InventoryItem('Дрель Bosch', 1, 60_000);

        $makitaStartsAt = new \DateTimeImmutable(
            '2026-11-10T13:00:00+03:00'
        );

        $boschStartsAt = new \DateTimeImmutable(
            '2026-11-12T10:00:00+03:00'
        );

        $makitaItem = $order->addItem(
            $makita,
            2,
            $makitaStartsAt,
            3
        );

        $boschItem = $order->addItem(
            $bosch,
            1,
            $boschStartsAt,
            7
        );


        $total = $order->getTotalPriceKopecks();

        self::assertSame(RentalOrderStatus::DRAFT, $order->getStatus());
        self::assertCount(2, $order->getItems());

        self::assertSame($order, $makitaItem->getRentalOrder());
        self::assertSame($order, $boschItem->getRentalOrder());

        // Assert: продолжительность
        self::assertSame(3, $makitaItem->getRentalDays());
        self::assertSame(7, $boschItem->getRentalDays());

        self::assertSame(
            '2026-11-13 13:00:00+03:00',
            $makitaItem->getEndsAt()->format('Y-m-d H:i:sP')
        );

        self::assertSame(
            '2026-11-19 10:00:00+03:00',
            $boschItem->getEndsAt()->format('Y-m-d H:i:sP')
        );

        // Assert: стоимость позиций
        self::assertSame(600000, $makitaItem->getTotalPriceKopecks());
        self::assertSame(420000, $boschItem->getTotalPriceKopecks());

        // Assert: общая стоимость
        self::assertSame(1020000, $total);

    }


    public function testRejectsZeroQuantityWithoutModifyingOrder(): void
    {
        // Arrange
        $order = new RentalOrder();
        $inventoryItem = new InventoryItem('Перфоратор Makita', 5, 100_000);

        // Act
        try {
            $order->addItem(
                $inventoryItem,
                0,
                new \DateTimeImmutable('2026-11-10 13:00:00'),
                3
            );

            self::fail('Expected InvalidArgumentException.');
        } catch (\InvalidArgumentException $e) {
            self::assertSame(
                'Quantity must be positive',
                $e->getMessage()
            );
        }

        // Assert
        self::assertCount(0, $order->getItems());
    }


    public function testRejectsZeroRentalDaysWithoutModifyingOrder(): void
    {
        $order = new RentalOrder();
        $inventoryItem = new InventoryItem('Перфоратор Makita', 5, 100_000);

        try {
            $order->addItem(
                $inventoryItem,
                2,
                new \DateTimeImmutable('2026-11-10 13:00:00'),
                0
            );

            self::fail('Expected InvalidArgumentException.');
        } catch (\InvalidArgumentException $e) {
            self::assertSame(
                'Rental days must be positive.',
                $e->getMessage()
            );
        }

        self::assertCount(0, $order->getItems());
    }

    public function testRejectsNegativeInventoryDailyRate(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        new InventoryItem(
            'Перфоратор Makita',
            5,
            -100
        );
    }

    public function testRejectsZeroDailyRateWithoutModifyingOrder(): void
    {
        $order = new RentalOrder();

        $inventoryItem = new InventoryItem(
            'Перфоратор Makita',
            5,
            0
        );

        try {
            $order->addItem(
                $inventoryItem,
                2,
                new \DateTimeImmutable('2026-11-10 13:00:00'),
                3
            );

            self::fail('Expected DomainException.');
        } catch (\DomainException $e) {
            self::assertSame(
                'Cannot add item with zero daily rate.',
                $e->getMessage()
            );
        }

        self::assertCount(0, $order->getItems());
        self::assertSame(0, $order->getTotalPriceKopecks());
    }


}
