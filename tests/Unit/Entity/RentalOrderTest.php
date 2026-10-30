<?php
declare(strict_types=1);

namespace App\Tests\Unit\Entity;

use App\Entity\InventoryItem;
use App\Entity\RentalOrder;
use App\Entity\RentalOrderItem;
use App\Enums\RentalOrderStatus;
use PHPUnit\Framework\TestCase;

final class RentalOrderTest extends TestCase
{
    public function testOrderWithMultipleItemsCalculateTotal(): void
    {
        $order = new RentalOrder();

        $makita = new InventoryItem('Перфоратор Makita', 2);
        $bosch = new InventoryItem('Дрель Bosch', 1);

        $makitaStartsAt = new \DateTimeImmutable(
            '2026-11-10T13:00:00+03:00'
        );

        $boschStartsAt = new \DateTimeImmutable(
            '2026-11-12T10:00:00+03:00'
        );

        $makitaItem = new RentalOrderItem(
            $order,
            $makita,
            2,
            $makitaStartsAt,
            3,
            100000
        );

        $boschItem = new RentalOrderItem(
            $order,
            $bosch,
            1,
            $boschStartsAt,
            7,
            60000
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
        $inventoryItem = new InventoryItem('Перфоратор Makita', 5);

        // Act
        try {
            new RentalOrderItem(
                $order,
                $inventoryItem,
                0,
                new \DateTimeImmutable('2026-11-10 13:00:00'),
                3,
                100000
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
        $inventoryItem = new InventoryItem('Перфоратор Makita', 5);

        try {
            new RentalOrderItem(
                $order,
                $inventoryItem,
                2,
                new \DateTimeImmutable('2026-11-10 13:00:00'),
                0,
                100000
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



    public function testRejectsNegativeDailyRateWithoutModifyingOrder(): void
    {
        $order = new RentalOrder();
        $inventoryItem = new InventoryItem('Перфоратор Makita', 5);

        try {
            new RentalOrderItem(
                $order,
                $inventoryItem,
                2,
                new \DateTimeImmutable('2026-11-10 13:00:00'),
                3,
                -100
            );

            self::fail('Expected InvalidArgumentException.');
        } catch (\InvalidArgumentException $e) {
            self::assertSame(
                'DailyRateKopecks cannot be negative',
                $e->getMessage()
            );
        }

        self::assertCount(0, $order->getItems());
    }


}
