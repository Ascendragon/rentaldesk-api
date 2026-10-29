<?php

namespace App\Tests\Integration\Entity;

use App\Entity\Asset;
use App\Entity\InventoryItem;
use App\Enums\AssetStatus;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

final class AssetPersistenceTest extends KernelTestCase
{
    public function testPersistsAndReloadAsset(): void
    {
        self::bootKernel();

        $em = self::getContainer()->get(EntityManagerInterface::class);

        $connection = $em->getConnection();

        // Защита от случайного запуска на рабочей БД
        self::assertSame(
            'rentaldesk_test',
            $connection->fetchOne('SELECT current_database()')
        );
        $connection->beginTransaction();

        try {
            $inventoryItem = new InventoryItem("Перфоратор Макита", 6);

            $asset = new Asset($inventoryItem, 'MK-TEST-' . bin2hex(random_bytes(6)),
                1_500_000, new \DateTimeImmutable('2026-10-01'));

            $em->persist($inventoryItem);
            $em->persist($asset);
            $em->flush();

            $id = $asset->getId();

            self::assertNotNull($id);

            $em->clear();

            $reloaded = $em->find(Asset::class, $id);

            self::assertInstanceOf(Asset::class, $reloaded);

            self::assertSame(
                $asset->getInventoryCode(),
                $reloaded->getInventoryCode()
            );
            self::assertSame(
                1_500_000,
                $reloaded->getPurchasePriceKopecks()
            );
            self::assertSame(
                AssetStatus::ACTIVE,
                $reloaded->getStatus()
            );
            self::assertSame(
                '2026-10-01',
                $reloaded->getPurchasedAt()?->format('Y-m-d')
            );
            self::assertSame(
                $inventoryItem->getId(),
                $reloaded->getInventoryItem()->getId()
            );
        } finally {
            $connection->rollBack();
        }
    }
}
