<?php

namespace Tests\Unit\Mapping\Fixtures\Bidirectional;

class OrderItemEntity
{
    public function __construct(
        public int $id = 0,
        public string $productName = '',
        public int $quantity = 0,
        public float $unitPrice = 0.0
    ) {
    }
}
