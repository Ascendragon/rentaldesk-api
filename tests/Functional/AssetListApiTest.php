<?php

namespace App\Tests\Functional;

use App\Entity\Asset;
use App\Entity\InventoryItem;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

class AssetListApiTest extends WebTestCase
{
    public function testReturnsOnlyAssetsOfRequestedInventoryItem(): void
    {
        $inventoryItem1 = new InventoryItem('Перфоратор Makita', 6, 0);

        $inventoryItem2 = new InventoryItem('Дрель Bosch', 9, 0);

        $asset1 = new Asset($inventoryItem1, 'MK-TEST-001', 2000000);
        $asset2 = new Asset($inventoryItem1, 'MK-TEST-002', 1900000);

        $asset3 = new Asset($inventoryItem2, 'BH-TEST-001', 1000000);
        $asset4 = new Asset($inventoryItem2, 'BH-TEST-002', 1200000);

        $client = static::createClient();
        $em = self::getContainer()->get(EntityManagerInterface::class);
        $connection = $em->getConnection();
        self::assertSame(
            'rentaldesk_test',
            $connection->fetchOne('SELECT current_database()')
        );
        $connection->beginTransaction();
        try {
            $em->persist($inventoryItem1);
            $em->persist($inventoryItem2);

            $em->persist($asset1);
            $em->persist($asset2);
            $em->persist($asset3);
            $em->persist($asset4);

            $em->flush();

            $client->request(
                'GET',
                "/api/inventory/{$inventoryItem1->getId()}/assets",
                [],
                [],
                [
                    'CONTENT_TYPE' => 'application/json'
                ]
            );

            $response = $client->getResponse();

            $data = json_decode(
                $response->getContent(),
                true,
                512,
                JSON_THROW_ON_ERROR
            );
            self::assertResponseStatusCodeSame(200);
            self::assertCount(2, $data);
            $codes = array_column($data, 'inventoryCode');

            self::assertContains('MK-TEST-001', $codes);
            self::assertContains('MK-TEST-002', $codes);

            self::assertSame(
                [$asset1->getId(), $asset2->getId()],
                array_column($data, 'id')
            );
            foreach ($data as $asset) {
                self::assertSame(
                    $inventoryItem1->getId(),
                    $asset['inventoryItemId']
                );
            }


        } finally {
            $connection->rollBack();
        }
    }

    public function testReturnsEmptyArrayWhenInventoryItemHasNoAssets(): void
    {
        $inventoryItem1 = new InventoryItem('Перфоратор Makita', 6, 0);

        $client = static::createClient();
        $em = self::getContainer()->get(EntityManagerInterface::class);
        $connection = $em->getConnection();
        self::assertSame(
            'rentaldesk_test',
            $connection->fetchOne('SELECT current_database()')
        );
        $connection->beginTransaction();
        try {
            $em->persist($inventoryItem1);
            $em->flush();

            $client->request(
                'GET',
                "/api/inventory/{$inventoryItem1->getId()}/assets",
                [],
                [],
                [
                    'CONTENT_TYPE' => 'application/json'
                ]
            );

            $response = $client->getResponse();

            $data = json_decode(
                $response->getContent(),
                true,
                512,
                JSON_THROW_ON_ERROR
            );
            self::assertResponseStatusCodeSame(200);
            self::assertSame([], $data);
        } finally {
            $connection->rollback();
        }
    }

    public function testReturns404WhenInventoryItemDoesNotExist(): void
    {
        $client = static::createClient();
        $client->request(
            'GET',
            "/api/inventory/999999999/assets",
            [],
            [],
            [
                'CONTENT_TYPE' => 'application/json'
            ]
        );

        $response = $client->getResponse();

        $data = json_decode(
            $response->getContent(),
            true,
            512,
            JSON_THROW_ON_ERROR
        );

        self::assertResponseStatusCodeSame(404);

        self::assertResponseHeaderSame(
            'Content-Type',
            'application/problem+json'
        );

        self::assertSame('about:blank', $data['type']);
        self::assertSame('Not Found', $data['title']);
        self::assertSame(404, $data['status']);
        self::assertSame('inventory_item_not_found', $data['code']);

        self::assertArrayNotHasKey('violations', $data);
    }
}
