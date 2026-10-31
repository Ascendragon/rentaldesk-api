<?php
declare(strict_types=1);

namespace App\Tests\Integration\Entity;

use App\Entity\InventoryItem;
use App\Entity\RentalOrder;
use App\Entity\RentalOrderItem;
use App\Enums\RentalOrderStatus;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

final class RentalOrderPersistenceTest extends KernelTestCase
{
    public function testPersistsAndReloadsOrderWithItems(): void
    {
        self::bootKernel();

        $em = self::getContainer()->get(EntityManagerInterface::class);
        $connection = $em->getConnection();

        // Не допускаем случайной записи в рабочую БД.
        self::assertSame(
            'rentaldesk_test',
            $connection->fetchOne('SELECT current_database()')
        );

        $connection->beginTransaction();

        try {
            $makita = new InventoryItem(
                'Перфоратор Makita',
                2,
                100_000
            );

            $bosch = new InventoryItem(
                'Дрель Bosch',
                1,
                60_000
            );

            $order = new RentalOrder();

            $makitaItem = $order->addItem(
                $makita,
                2,
                new \DateTimeImmutable('2026-11-10 13:00:00'),
                3
            );

            $boschItem = $order->addItem(
                $bosch,
                1,
                new \DateTimeImmutable('2026-11-12 10:00:00'),
                7
            );


            $em->persist($makita);
            $em->persist($bosch);
            $em->persist($order);

            // Позиции отдельно НЕ persist(): проверяем cascade ['persist'].
            $em->flush();

            $orderId = $order->getId();
            $makitaId = $makita->getId();
            $boschId = $bosch->getId();

            self::assertNotNull($orderId);
            self::assertNotNull($makitaId);
            self::assertNotNull($boschId);

            // Очищаем identity map, чтобы Doctrine читал сущности из БД.
            $em->clear();

            $reloaded = $em->getRepository(RentalOrder::class)->find($orderId);

            self::assertInstanceOf(RentalOrder::class, $reloaded);
            self::assertSame(RentalOrderStatus::DRAFT, $reloaded->getStatus());
            self::assertCount(2, $reloaded->getItems());
            self::assertSame(1_020_000, $reloaded->getTotalPriceKopecks());

            // Не предполагаем определённый порядок OneToMany-коллекции.
            $itemsByInventoryId = [];

            foreach ($reloaded->getItems() as $item) {
                self::assertNotNull($item->getId());
                self::assertSame($reloaded, $item->getRentalOrder());

                $inventoryId = $item->getInventoryItem()->getId();
                self::assertNotNull($inventoryId);

                $itemsByInventoryId[$inventoryId] = $item;
            }

            self::assertCount(2, $itemsByInventoryId);
            self::assertArrayHasKey($makitaId, $itemsByInventoryId);
            self::assertArrayHasKey($boschId, $itemsByInventoryId);

            $savedMakita = $itemsByInventoryId[$makitaId];

            self::assertSame(2, $savedMakita->getQuantity());
            self::assertSame(3, $savedMakita->getRentalDays());
            self::assertSame(100_000, $savedMakita->getDailyRateKopecks());
            self::assertSame(600_000, $savedMakita->getTotalPriceKopecks());
            self::assertSame(
                '2026-11-10 13:00:00',
                $savedMakita->getStartsAt()->format('Y-m-d H:i:s')
            );
            self::assertSame(
                '2026-11-13 13:00:00',
                $savedMakita->getEndsAt()->format('Y-m-d H:i:s')
            );

            $savedBosch = $itemsByInventoryId[$boschId];

            self::assertSame(1, $savedBosch->getQuantity());
            self::assertSame(7, $savedBosch->getRentalDays());
            self::assertSame(60_000, $savedBosch->getDailyRateKopecks());
            self::assertSame(420_000, $savedBosch->getTotalPriceKopecks());
            self::assertSame(
                '2026-11-12 10:00:00',
                $savedBosch->getStartsAt()->format('Y-m-d H:i:s')
            );
            self::assertSame(
                '2026-11-19 10:00:00',
                $savedBosch->getEndsAt()->format('Y-m-d H:i:s')
            );
        } finally {
            $connection->rollBack();
        }
    }

    public function testPreservesRentalInstantsAfterDatabaseReload(): void
    {
        self::bootKernel();

        $em = self::getContainer()->get(EntityManagerInterface::class);

        $connection = $em->getConnection();

        self::assertSame(
            'rentaldesk_test',
            $connection->fetchOne('SELECT current_database()')
        );

        $connection->beginTransaction();

        try {
            $order = new RentalOrder();

            $makita = new InventoryItem('Перфоратор Makita', 2, 100_000);
            $bosch = new InventoryItem('Дрель Bosch', 1, 60_000);

            $makitaStart = new \DateTimeImmutable('2026-11-12T10:00:00+05:00');
            $boschStart = new \DateTimeImmutable(
                '2026-11-12T10:00:00+05:00'
            );

            $makitaItem = $order->addItem(
                $makita,
                2,
                $makitaStart,
                3
            );

            $boschItem = $order->addItem(
                $bosch,
                1,
                $boschStart,
                7
            );

            // Запоминаем модели до сохранения

            $expectedMakitaStart = $expectedMakitaStart = $makitaItem->getStartsAt()->getTimestamp();
            $expectedMakitaEnd = $makitaItem->getEndsAt()->getTimestamp();

            $expectedBoschStart = $boschItem->getStartsAt()->getTimestamp();
            $expectedBoschEnd = $boschItem->getEndsAt()->getTimestamp();

            $em->persist($makita);
            $em->persist($bosch);
            $em->persist($order);

            // RentalOrderItem сохраняются через cascade persist.
            $em->flush();

            $orderId = $order->getId();
            $makitaId = $makita->getId();
            $boschId = $bosch->getId();

            self::assertNotNull($orderId);
            self::assertNotNull($makitaId);
            self::assertNotNull($boschId);

            // Убираем сущности из памяти Doctrine.
            $em->clear();

            // Act: повторно читаем заказ из PostgreSQL.
            $reloaded = $em->getRepository(RentalOrder::class)
                ->find($orderId);

            self::assertInstanceOf(RentalOrder::class, $reloaded);

            $items = [];

            foreach ($reloaded->getItems() as $item) {
                $items[$item->getInventoryItem()->getId()] = $item;
            }

            self::assertCount(2, $items);
            self::assertArrayHasKey($makitaId, $items);
            self::assertArrayHasKey($boschId, $items);

            // Assert: Makita
            self::assertSame(
                $expectedMakitaStart,
                $items[$makitaId]->getStartsAt()->getTimestamp()
            );

            self::assertSame(
                $expectedMakitaEnd,
                $items[$makitaId]->getEndsAt()->getTimestamp()
            );

            // Assert: Bosch
            self::assertSame(
                $expectedBoschStart,
                $items[$boschId]->getStartsAt()->getTimestamp()
            );

            self::assertSame(
                $expectedBoschEnd,
                $items[$boschId]->getEndsAt()->getTimestamp()
            );

        } finally {
            $connection->rollBack();

        }
    }
}
