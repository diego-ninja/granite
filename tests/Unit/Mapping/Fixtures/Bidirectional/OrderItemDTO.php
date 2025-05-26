<?php

namespace Tests\Unit\Mapping\Fixtures\Bidirectional;

class OrderItemDTO
{
    public function __construct(
        public string $product,
        public int $qty,
        public float $price,
        public float $subtotal
    ) {
    }
}
