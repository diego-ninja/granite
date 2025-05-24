<?php

namespace Tests\Unit\Mapping\Attributes;

use Ninja\Granite\Mapping\Attributes\MapFrom;
use Ninja\Granite\Mapping\AutoMapper;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use Tests\Helpers\TestCase;

#[CoversClass(MapFrom::class)]
class MapFromTest extends TestCase
{
    private AutoMapper $mapper;

    protected function setUp(): void
    {
        $this->mapper = new AutoMapper();
        parent::setUp();
    }

    #[Test]
    public function it_maps_from_source_property(): void
    {
        $source = [
            'source_property' => 'test value'
        ];

        $result = $this->mapper->map($source, MapFromDTO::class);

        $this->assertEquals('test value', $result->destinationProperty);
    }

    #[Test]
    public function it_maps_from_nested_source_property(): void
    {
        $source = [
            'nested' => [
                'deeply' => [
                    'property' => 'nested value'
                ]
            ]
        ];

        $result = $this->mapper->map($source, NestedMapFromDTO::class);

        $this->assertEquals('nested value', $result->value);
    }

    #[Test]
    public function it_handles_missing_source_property(): void
    {
        $source = [
            'different_property' => 'test value'
        ];

        $result = $this->mapper->map($source, MapFromDTO::class);

        $this->assertNull($result->destinationProperty);
    }

    #[Test]
    public function it_handles_missing_nested_source_property(): void
    {
        $source = [
            'nested' => [
                'different' => 'test value'
            ]
        ];

        $result = $this->mapper->map($source, NestedMapFromDTO::class);

        $this->assertNull($result->value);
    }

    #[Test]
    public function it_maps_from_array_index(): void
    {
        $source = [
            'items' => ['first', 'second', 'third']
        ];

        $result = $this->mapper->map($source, ArrayIndexMapFromDTO::class);

        $this->assertEquals('first', $result->firstItem);
        $this->assertEquals('second', $result->secondItem);
    }

    #[Test]
    public function it_handles_multiple_map_from_attributes(): void
    {
        $source = [
            'first_name' => 'John',
            'last_name' => 'Doe',
            'email' => 'john@example.com'
        ];

        $result = $this->mapper->map($source, MultipleMapFromDTO::class);

        $this->assertEquals('John', $result->firstName);
        $this->assertEquals('Doe', $result->lastName);
        $this->assertEquals('john@example.com', $result->email);
    }

    #[Test]
    public function it_prioritizes_map_from_over_property_name(): void
    {
        $source = [
            'name' => 'Should not map',
            'source_name' => 'Should map'
        ];

        $result = $this->mapper->map($source, PriorityMapFromDTO::class);

        $this->assertEquals('Should map', $result->name);
    }

    #[Test]
    public function it_works_with_camel_case_to_snake_case(): void
    {
        $source = [
            'user_id' => 1,
            'first_name' => 'John',
            'last_name' => 'Doe'
        ];

        $result = $this->mapper->map($source, CamelCaseMapFromDTO::class);

        $this->assertEquals(1, $result->userId);
        $this->assertEquals('John', $result->firstName);
        $this->assertEquals('Doe', $result->lastName);
    }
}

// Test DTOs
class MapFromDTO
{
    public function __construct(
        #[MapFrom('source_property')]
        public ?string $destinationProperty = null
    ) {
    }
}

class NestedMapFromDTO
{
    public function __construct(
        #[MapFrom('nested.deeply.property')]
        public ?string $value = null
    ) {
    }
}

class ArrayIndexMapFromDTO
{
    public function __construct(
        #[MapFrom('items.0')]
        public ?string $firstItem = null,

        #[MapFrom('items.1')]
        public ?string $secondItem = null
    ) {
    }
}

class MultipleMapFromDTO
{
    public function __construct(
        #[MapFrom('first_name')]
        public ?string $firstName = null,

        #[MapFrom('last_name')]
        public ?string $lastName = null,

        #[MapFrom('email')]
        public ?string $email = null
    ) {
    }
}

class PriorityMapFromDTO
{
    public function __construct(
        #[MapFrom('source_name')]
        public ?string $name = null
    ) {
    }
}

class CamelCaseMapFromDTO
{
    public function __construct(
        #[MapFrom('user_id')]
        public ?int $userId = null,

        #[MapFrom('first_name')]
        public ?string $firstName = null,

        #[MapFrom('last_name')]
        public ?string $lastName = null
    ) {
    }
}
