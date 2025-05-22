<?php

// tests/Fixtures/VOs/ProductVO.php

declare(strict_types=1);

namespace Tests\Fixtures\VOs;

use Ninja\Granite\GraniteVO;

final readonly class ProductVO extends GraniteVO
{
    public function __construct(
        public string $name,
        public string $sku,
        public float $price,
        public int $quantity,
        public string $category
    ) {}

    protected static function rules(): array
    {
        return [
            'name' => 'required|string|min:3|max:100',
            'sku' => 'required|string|regex:/^[A-Z0-9]{10}$/',
            'price' => 'required|number|min:0.01',
            'quantity' => 'required|integer|min:0',
            'category' => 'required|in:electronics,clothing,books,home'
        ];
    }
}