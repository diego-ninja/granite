<?php

namespace Tests\Unit\Mapping\Fixtures\Bidirectional;

class OrderDTO
{
    public function __construct(
        public string $number,
        public string $customerName,
        public array $items,
        public float $total,
    ) {}
}
