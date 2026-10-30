<?php
declare(strict_types=1);

namespace App\Tests\Functional;

use App\Entity\Asset;
use App\Entity\InventoryItem;
use App\Service\AssetService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

final class ApiProblemDetailsTest extends WebTestCase
{
    public function testInvalidAssetPayloadReturnsProblemDetails(): void
    {
        $payload = [
            'inventoryCode' => '',
            'purchasePriceKopecks' => -100,
            'purchasedAt' => '2026-02-30',
        ];

        $client = self::createClient();

        $client->request(
            'POST',
            '/api/inventory/99999/assets',
            [],
            [],
            ['CONTENT_TYPE' => 'application/json'],
            json_encode($payload, JSON_THROW_ON_ERROR)
        );

        $data = json_decode(
            $client->getResponse()->getContent(),
            true,
            512,
            JSON_THROW_ON_ERROR
        );

        self::assertResponseStatusCodeSame(422);

        self::assertArrayHasKey('code', $data);
        self::assertSame('validation_failed', $data['code']);

        self::assertArrayHasKey('violations', $data);
        self::assertIsArray($data['violations']);

        $fields = array_column($data['violations'], 'field');

        self::assertContains('inventoryCode', $fields);
        self::assertContains('purchasePriceKopecks', $fields);
        self::assertContains('purchasedAt', $fields);
        self::assertResponseHeaderSame(
            'Content-Type',
            'application/problem+json'
        );
        self::assertSame('about:blank', $data['type']);
        self::assertSame(422, $data['status']);

    }

    public function testMalformedJsonReturnsProblemDetails(): void
    {
        $client = self::createClient();

        $client->request(
            'POST',
            '/api/inventory/99999/assets',
            [],
            [],
            ['CONTENT_TYPE' => 'application/json'],
            '{bad json'
        );

        $data = json_decode(
            $client->getResponse()->getContent(),
            true,
            512,
            JSON_THROW_ON_ERROR
        );
        self::assertResponseStatusCodeSame(400);

        self::assertArrayHasKey('code', $data);
        self::assertSame('malformed_request', $data['code']);
        self::assertSame('about:blank', $data['type']);
        self::assertSame(400, $data['status']);
        self::assertResponseHeaderSame(
            'Content-Type',
            'application/problem+json'
        );
        self::assertSame('Bad Request', $data['title']);
        self::assertArrayNotHasKey('violations', $data);
    }

    public function testUnsupportedMethodReturnsProblemDetails(): void
    {
        $client = self::createClient();

        $client->request(
            'PUT',
            '/api/inventory'
        );

        $data = json_decode(
            $client->getResponse()->getContent(),
            true,
            512,
            JSON_THROW_ON_ERROR
        );

        self::assertResponseStatusCodeSame(405);
        self::assertResponseHeaderSame(
            'Content-Type',
            'application/problem+json'
        );
        self::assertArrayHasKey('code', $data);
        self::assertSame('http_error', $data['code']);
        self::assertSame(405, $data['status']);
        self::assertSame('Method Not Allowed', $data['title']);
        $allow = $client->getResponse()->headers->get('Allow');

        self::assertNotNull($allow);
        self::assertStringContainsString('GET', $allow);
        self::assertStringContainsString('POST', $allow);
    }

    public function testMissingInventoryItemReturnsProblemDetails(): void
    {
        $client = self::createClient();
        $client->request(
            'POST',
            '/api/inventory/99999999/assets',
            [],
            [],
            ['CONTENT_TYPE' => 'application/json'],
            json_encode(
                ['inventoryCode' => 'MISSING-001'],
                JSON_THROW_ON_ERROR
            )
        );
        $data = json_decode(
            $client->getResponse()->getContent(),
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

    public function testDuplicateInventoryCodeReturnsProblemDetails(): void
    {

        $client = self::createClient();

        $em = self::getContainer()
            ->get(EntityManagerInterface::class);

        $connection = $em->getConnection();

        $code = 'TEST-' . bin2hex(random_bytes(8));

        $inventoryItem = new InventoryItem(
            'Тестовый перфоратор',
            1
        );

        $asset = new Asset(
            $inventoryItem,
            $code,
            1500000
        );

        $em->persist($inventoryItem);
        $em->persist($asset);
        $em->flush();

        $inventoryId = $inventoryItem->getId();
        $assetId = $asset->getId();

        try {
            $client->request(
                'POST',
                "/api/inventory/{$inventoryId}/assets",
                [],
                [],
                ['CONTENT_TYPE' => 'application/json'],
                json_encode([
                    'inventoryCode' => $code,
                    'purchasePriceKopecks' => 1500000,
                ], JSON_THROW_ON_ERROR)
            );

            self::assertResponseStatusCodeSame(409);

            self::assertResponseHeaderSame(
                'Content-Type',
                'application/problem+json'
            );

            $data = json_decode(
                $client->getResponse()->getContent(),
                true,
                512,
                JSON_THROW_ON_ERROR
            );

            self::assertSame(
                'inventory_code_conflict',
                $data['code'] ?? null
            );

            self::assertSame('Conflict', $data['title'] ?? null);
            self::assertSame(409, $data['status'] ?? null);

            self::assertArrayNotHasKey('violations', $data);
            self::assertSame('about:blank', $data['type'] ?? null);
            $count = $connection->fetchOne(
                'SELECT COUNT(*) FROM asset WHERE inventory_code = :code',
                ['code' => $code]
            );

            self::assertSame(1, (int) $count);

        } finally {
            $connection->delete('asset', ['id' => $assetId]);
            $connection->delete('inventory_item', ['id' => $inventoryId]);
        }
    }

    public function testUnexpectedExceptionReturnsSafeProblemDetails(): void
    {
        $client = self::createClient();

        $mock = $this->createMock(AssetService::class);

        $mock
            ->expects(self::once())
            ->method('register')
            ->willThrowException(
                new \RuntimeException('SECRET-123')
            );

        self::getContainer()->set(AssetService::class, $mock);

        $payload = [
            'inventoryCode' => 'TEST-123',
            'purchasePriceKopecks' => 150000,
            'purchasedAt' => '2026-10-15',
        ];

        $client->request(
            'POST',
            '/api/inventory/123/assets',
            [],
            [],
            ['CONTENT_TYPE' => 'application/json'],
            json_encode($payload, JSON_THROW_ON_ERROR)
        );

        self::assertResponseStatusCodeSame(500);

        self::assertResponseHeaderSame(
            'Content-Type',
            'application/problem+json'
        );

        $content = $client->getResponse()->getContent();

        self::assertStringNotContainsString(
            'SECRET-123',
            $content
        );

        $data = json_decode(
            $content,
            true,
            512,
            JSON_THROW_ON_ERROR
        );

        self::assertSame([
            'type' => 'about:blank',
            'title' => 'Internal Server Error',
            'status' => 500,
            'detail' => 'Unexpected server error.',
            'code' => 'internal_error',
        ], $data);
    }
}
