<?php

declare(strict_types=1);

namespace Tests\Unit\Mapping\Attributes;

use Ninja\Granite\Mapping\Attributes\MapBidirectional;
use Ninja\Granite\Mapping\Attributes\MapCollection;
use Ninja\Granite\Mapping\Attributes\MapDefault;
use Ninja\Granite\Mapping\Attributes\MapWhen;
use Ninja\Granite\Mapping\Contracts\Mapper;
use Ninja\Granite\Mapping\Transformers\CollectionTransformer;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use Tests\Fixtures\Mapping\MyTestTransformer;
use Tests\Fixtures\Mapping\SomeTransformer;
use Tests\Helpers\TestCase;

#[CoversClass(MapBidirectional::class)]
#[CoversClass(MapCollection::class)]
#[CoversClass(MapDefault::class)]
#[CoversClass(MapWhen::class)]
class MappingAttributesTest extends TestCase
{
    #[Test]
    public function test_map_bidirectional_attribute(): void
    {
        // MapBidirectional constructor is: public function __construct(public string $otherProperty) {}
        $attr1 = new MapBidirectional('targetPropName');
        $this->assertEquals('targetPropName', $attr1->otherProperty);

        $attr2 = new MapBidirectional('anotherTarget');
        $this->assertEquals('anotherTarget', $attr2->otherProperty);
    }

    #[Test]
    public function test_map_collection_attribute(): void
    {
        // Constructor: public string $destinationType, public bool $preserveKeys = false, public bool $recursive = false, public mixed $itemTransformer = null
        $attr = new MapCollection(
            destinationType: 'MemberClass',
            preserveKeys: true,
            recursive: false,
            itemTransformer: SomeTransformer::class
        );
        $this->assertEquals('MemberClass', $attr->destinationType);
        $this->assertTrue($attr->preserveKeys);
        $this->assertFalse($attr->recursive);
        $this->assertEquals(SomeTransformer::class, $attr->itemTransformer);
    }

    #[Test]
    public function test_map_collection_create_transformer(): void
    {
        $mockMapper = $this->createMock(Mapper::class);
        $customItemTransformerInstance = new MyTestTransformer();

        // Test with custom transformer instance for items
        $attrWithCustomItemTransformer = new MapCollection(
            destinationType: 'MyDTO',
            itemTransformer: $customItemTransformerInstance
        );
        $collectionTransformer1 = $attrWithCustomItemTransformer->createTransformer($mockMapper);
        $this->assertInstanceOf(CollectionTransformer::class, $collectionTransformer1);
        // In a real scenario, we'd check if $collectionTransformer1 correctly uses $customItemTransformerInstance.
        // This might involve checking a property of $collectionTransformer1 or its behavior.
        // For this test, we primarily check that createTransformer returns the correct wrapper type.

        // Test with custom transformer class string for items
        $attrWithCustomItemTransformerClass = new MapCollection(
            destinationType: 'MyDTO',
            itemTransformer: MyTestTransformer::class
        );
        $collectionTransformer2 = $attrWithCustomItemTransformerClass->createTransformer($mockMapper);
        $this->assertInstanceOf(CollectionTransformer::class, $collectionTransformer2);

        // Test with no item transformer (relies on default behavior within CollectionTransformer)
        $attrWithDefaultTransformer = new MapCollection('MyOtherDTO');
        $collectionTransformer3 = $attrWithDefaultTransformer->createTransformer($mockMapper);
        $this->assertInstanceOf(CollectionTransformer::class, $collectionTransformer3);
    }

    #[Test]
    public function test_map_default_attribute(): void
    {
        $attr1 = new MapDefault('defaultValue');
        $this->assertEquals('defaultValue', $attr1->value);

        $attr2 = new MapDefault(null);
        $this->assertNull($attr2->value);

        $attr3 = new MapDefault(['foo' => 'bar']);
        $this->assertEquals(['foo' => 'bar'], $attr3->value);
    }

    #[Test]
    public function test_map_when_attribute(): void
    {
        // MapWhen constructor is: public function __construct(public mixed $condition) {}
        $condition = static fn ($source) => isset($source['type']) && $source['type'] === 'admin';
        $attr = new MapWhen($condition);

        $this->assertSame($condition, $attr->condition);
    }
}
