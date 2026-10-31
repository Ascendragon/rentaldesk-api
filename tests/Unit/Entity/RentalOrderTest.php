<?php
declare(strict_types=1);

namespace App\Tests\Unit\Entity;

use App\Entity\InventoryItem;
use App\Entity\RentalOrder;
use App\Enums\RentalOrderStatus;
use App\Exception\RentalRateNotConfiguredException;
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
            (new \DateTimeImmutable('2026-11-13T13:00:00+03:00'))->getTimestamp(),
            $makitaItem->getEndsAt()->getTimestamp()
        );

        self::assertSame(
            (new \DateTimeImmutable('2026-11-19T10:00:00+03:00'))->getTimestamp(),
            $boschItem->getEndsAt()->getTimestamp()
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

            self::fail('Expected RentalRateNotConfiguredException.');
        } catch (RentalRateNotConfiguredException $e) {
            self::assertSame(
                'Cannot add item with zero daily rate.',
                $e->getMessage()
            );
        }

        self::assertCount(0, $order->getItems());
        self::assertSame(0, $order->getTotalPriceKopecks());
    }

    public function testNormalizesRentalPeriodToUtcAndPreservesDuration(): void
    {
        $startsAt = new \DateTimeImmutable(
            '2026-11-10T13:00:00+03:00'
        );

        $rentalDays = 5;

        $makita = new InventoryItem('Перфоратор Makita', 7, 200000);

        $order = new RentalOrder();

        $item = $order->addItem($makita, 3, $startsAt, $rentalDays);

        self::assertSame(
            '2026-11-10 10:00:00+00:00',
            $item->getStartsAt()->format('Y-m-d H:i:sP')
        );

        self::assertSame(
            '2026-11-15 10:00:00+00:00',
            $item->getEndsAt()->format('Y-m-d H:i:sP')
        );

        self::assertSame(
            432_000,
            $item->getEndsAt()->getTimestamp()
            - $item->getStartsAt()->getTimestamp()
        );

        self::assertSame(5, $item->getRentalDays());
    }

    /**
     * @throws \Exception
     */
    public function testRentalDurationRemainsExactAcrossDstTransition(): void
    {
        $startsAt = new \DateTimeImmutable(
            '2027-03-27 13:00:00',
            new \DateTimeZone('Europe/Amsterdam')
        );

        $rentalDays = 2;

        $makita = new InventoryItem('Перфоратор Makita', 7, 200000);

        $order = new RentalOrder();

        $item = $order->addItem($makita, 3, $startsAt, $rentalDays);

        self::assertSame(
            '2027-03-27 12:00:00+00:00',
            $item->getStartsAt()->format('Y-m-d H:i:sP')
        );

        self::assertSame(
            '2027-03-29 12:00:00+00:00',
            $item->getEndsAt()->format('Y-m-d H:i:sP')
        );

        self::assertSame(
            48 * 60 * 60,
            $item->getEndsAt()->getTimestamp()
            - $item->getStartsAt()->getTimestamp()
        );
        self::assertSame(2, $item->getRentalDays());

        self::assertSame(
            '2027-03-29 14:00:00+02:00',
            $item->getEndsAt()
                ->setTimezone(new \DateTimeZone('Europe/Amsterdam'))
                ->format('Y-m-d H:i:sP')
        );
    }
}
