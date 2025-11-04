<?php

namespace Tests\Integration;

use Ninja\Granite\GraniteVO;
use Tests\Fixtures\VOs\CustomUuid;
use Tests\Fixtures\VOs\Rcuid;
use Tests\Fixtures\VOs\UserId;
use Tests\Helpers\TestCase;

class UuidHydrationTest extends TestCase
{
    public function test_hydrate_object_with_uuid_properties(): void
    {
        $data = [
            'orderId' => 'order-123',
            'customerId' => 'customer-456',
            'trackingId' => 'track-789',
        ];

        $order = OrderWithIds::from($data);

        $this->assertInstanceOf(CustomUuid::class, $order->orderId);
        $this->assertEquals('order-123', $order->orderId->value);

        $this->assertInstanceOf(UserId::class, $order->customerId);
        $this->assertEquals('customer-456', $order->customerId->value);

        $this->assertInstanceOf(Rcuid::class, $order->trackingId);
        $this->assertEquals('track-789', $order->trackingId->value);
    }

    public function test_hydrate_object_with_uuid_from_json(): void
    {
        $json = '{"productId":"prod-111","supplierId":"supp-222"}';

        $product = ProductWithIds::from($json);

        $this->assertInstanceOf(CustomUuid::class, $product->productId);
        $this->assertEquals('prod-111', $product->productId->value);

        $this->assertInstanceOf(UserId::class, $product->supplierId);
        $this->assertEquals('supp-222', $product->supplierId->value);
    }

    public function test_hydrate_object_with_mixed_types(): void
    {
        $data = [
            'id' => 'mixed-123',
            'name' => 'Test Product',
            'quantity' => 42,
        ];

        $item = ItemWithMixedTypes::from($data);

        $this->assertInstanceOf(UserId::class, $item->id);
        $this->assertEquals('mixed-123', $item->id->value);
        $this->assertEquals('Test Product', $item->name);
        $this->assertEquals(42, $item->quantity);
    }
}

readonly class OrderWithIds extends GraniteVO
{
    public CustomUuid $orderId;
    public UserId $customerId;
    public Rcuid $trackingId;
}

readonly class ProductWithIds extends GraniteVO
{
    public CustomUuid $productId;
    public UserId $supplierId;
}

readonly class ItemWithMixedTypes extends GraniteVO
{
    public UserId $id;
    public string $name;
    public int $quantity;
}
