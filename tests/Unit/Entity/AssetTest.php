<?php

namespace App\Tests\Unit\Entity;

use App\Entity\Asset;
use App\Entity\InventoryItem;
use App\Enums\AssetStatus;
use PHPUnit\Framework\TestCase;

final class AssetTest extends TestCase
{
    public function testCreatesAssetWithValidData(): void
    {
        // Arrange
        $inventoryItem = new InventoryItem("Перфоратор Makita", 5);

        // Act
        $asset = new Asset($inventoryItem, 'MK-001', 1500000);

        // Assert
        self::assertNull($asset->getId());
        self::assertSame(AssetStatus::ACTIVE, $asset->getStatus());
        self::assertSame('MK-001', $asset->getInventoryCode());
        self::assertSame(1500000, $asset->getPurchasePriceKopecks());
        self::assertSame($inventoryItem, $asset->getInventoryItem());
    }

    public function testRejectsEmptyInventoryCode(): void
    {
        $inventoryItem = new InventoryItem('Перфоратор Makita', 5);

        self::expectException(\InvalidArgumentException::class);
        $asset = new Asset($inventoryItem, '   ');
    }

    public function testRejectsNegativePurchasePrice(): void
    {
        $inventoryItem = new InventoryItem('Перфоратор Makita', 5);

        self::expectException(\InvalidArgumentException::class);
        new Asset($inventoryItem, 'MK-001', -200000);
    }

    public function testSendsActiveAssetToMaintenance(): void
    {
        $inventoryItem = new InventoryItem('Перфоратор Makita', 5);

        $asset = new Asset($inventoryItem, 'MK-001', 1500000);

        self::assertSame(AssetStatus::ACTIVE, $asset->getStatus());

        $asset->sendToMaintenance();

        self::assertSame(AssetStatus::MAINTENANCE, $asset->getStatus());

    }

    public function testReturnsAssetFromMaintenanceToService(): void
    {
        $inventoryItem = new InventoryItem('Перфоратор Makita', 5);

        $asset = new Asset($inventoryItem, 'MK-001', 1500000);

        $asset->sendToMaintenance();

        self::assertSame(AssetStatus::MAINTENANCE, $asset->getStatus());

        $asset->returnToService();

        self::assertSame(AssetStatus::ACTIVE, $asset->getStatus());
    }

    public function testRetiresAssetFromMaintenance(): void
    {
        $inventoryItem = new InventoryItem('Перфоратор Makita', 5);

        $asset = new Asset($inventoryItem, 'MK-001', 1500000);

        $asset->sendToMaintenance();

        self::assertSame(AssetStatus::MAINTENANCE, $asset->getStatus());

        $asset->retire();

        self::assertSame(AssetStatus::RETIRED, $asset->getStatus());
    }

    public function testCannotReturnRetiredAssetToService(): void
    {
        $inventoryItem = new InventoryItem('Перфоратор Makita', 5);

        $asset = new Asset($inventoryItem, 'MK-001', 1500000);

        $asset->retire();

        self::assertSame(AssetStatus::RETIRED, $asset->getStatus());

        self::expectException(\DomainException::class);
        $asset->returnToService();
    }

    public function testCannotSendRetiredAssetToMaintenance(): void
    {
        $inventoryItem = new InventoryItem('Перфоратор Makita', 5);

        $asset = new Asset($inventoryItem, 'MK-001', 1500000);

        $asset->retire();

        self::assertSame(AssetStatus::RETIRED, $asset->getStatus());

        self::expectException(\DomainException::class);
        $asset->sendToMaintenance();
    }

    public function testRejectsInventoryCodeLongerThan64Characters(): void
    {
        $inventoryCode = str_repeat('A', 65);

        $inventoryItem = new InventoryItem('Перфоратор Makita', 5);

        self::expectException(\InvalidArgumentException::class);
        new Asset($inventoryItem, $inventoryCode, 1500000);
    }

    public function testRetireAlreadyRetiredThrowDomainException(): void
    {
        $inventoryItem = new InventoryItem('Перфоратор Makita', 5);

        $asset = new Asset($inventoryItem, 'MK-001', 1500000);

        $asset->retire();

        self::assertSame(AssetStatus::RETIRED, $asset->getStatus());

        self::expectException(\DomainException::class);
        $asset->retire();
    }

    public function testReturnToServiceNewAssetThrowDomainException(): void
    {
        $inventoryItem = new InventoryItem('Перфоратор Makita', 5);

        $asset = new Asset($inventoryItem, 'MK-001', 1500000);
        self::expectException(\DomainException::class);

        $asset->returnToService();
    }
}
