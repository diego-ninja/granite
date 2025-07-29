<?php

namespace Tests\Unit\Mapping\Fixtures\Bidirectional;

class OrderEntity
{
    public function __construct(
        public int $id = 0,
        public string $orderNumber = '',
        public ?UserEntity $customer = null,
        public array $items = [],
        public float $totalAmount = 0.0,
    ) {}
}
