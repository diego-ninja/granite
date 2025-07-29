<?php

// tests/Unit/Serialization/Attributes/HiddenTest.php

declare(strict_types=1);

namespace Tests\Unit\Serialization\Attributes;

use Attribute;
use Ninja\Granite\Serialization\Attributes\Hidden;
use PHPUnit\Framework\Attributes\CoversClass;
use ReflectionClass;
use Tests\Helpers\TestCase;

#[CoversClass(Hidden::class)] class HiddenTest extends TestCase
{
    public function test_creates_attribute(): void
    {
        $attribute = new Hidden();

        $this->assertInstanceOf(Hidden::class, $attribute);
    }

    public function test_is_readonly_class(): void
    {
        $reflection = new ReflectionClass(Hidden::class);

        $this->assertTrue($reflection->isReadonly());
    }

    public function test_has_correct_attribute_target(): void
    {
        $reflection = new ReflectionClass(Hidden::class);
        $attributes = $reflection->getAttributes(Attribute::class);

        $this->assertCount(1, $attributes);

        $attributeInstance = $attributes[0]->newInstance();
        $this->assertEquals(Attribute::TARGET_PROPERTY, $attributeInstance->flags);
    }

    public function test_constructor_takes_no_parameters(): void
    {
        $reflection = new ReflectionClass(Hidden::class);
        $constructor = $reflection->getConstructor();

        $this->assertNotNull($constructor);
        $this->assertCount(0, $constructor->getParameters());
    }

    public function test_multiple_instances_are_equal(): void
    {
        $hidden1 = new Hidden();
        $hidden2 = new Hidden();

        // Since it's a marker attribute with no properties, all instances are functionally equivalent
        $this->assertEquals($hidden1, $hidden2);
    }

    public function test_can_be_used_in_reflection(): void
    {
        $testClass = new readonly class () {
            #[Hidden]
            public string $hiddenProperty;

            public string $visibleProperty;
        };

        $reflection = new ReflectionClass($testClass);
        $properties = $reflection->getProperties();

        $hiddenProperty = null;
        $visibleProperty = null;

        foreach ($properties as $property) {
            if ('hiddenProperty' === $property->getName()) {
                $hiddenProperty = $property;
            } elseif ('visibleProperty' === $property->getName()) {
                $visibleProperty = $property;
            }
        }

        $this->assertNotNull($hiddenProperty);
        $this->assertNotNull($visibleProperty);

        // Check that hiddenProperty has Hidden attribute
        $hiddenAttributes = $hiddenProperty->getAttributes(Hidden::class);
        $this->assertCount(1, $hiddenAttributes);

        // Check that visibleProperty doesn't have Hidden attribute
        $visibleAttributes = $visibleProperty->getAttributes(Hidden::class);
        $this->assertCount(0, $visibleAttributes);
    }
}
