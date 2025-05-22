<?php
// tests/Unit/Serialization/Attributes/SerializedNameTest.php

declare(strict_types=1);

namespace Tests\Unit\Serialization\Attributes;

use Ninja\Granite\Serialization\Attributes\SerializedName;
use PHPUnit\Framework\Attributes\CoversClass;
use Tests\Helpers\TestCase;

#[CoversClass(SerializedName::class)] class SerializedNameTest extends TestCase
{
    public function test_creates_attribute_with_name(): void
    {
        $attribute = new SerializedName('first_name');

        $this->assertInstanceOf(SerializedName::class, $attribute);
        $this->assertEquals('first_name', $attribute->name);
    }

    public function test_is_readonly_class(): void
    {
        $reflection = new \ReflectionClass(SerializedName::class);

        $this->assertTrue($reflection->isReadonly());
    }

    public function test_has_correct_attribute_target(): void
    {
        $reflection = new \ReflectionClass(SerializedName::class);
        $attributes = $reflection->getAttributes(\Attribute::class);

        $this->assertCount(1, $attributes);

        $attributeInstance = $attributes[0]->newInstance();
        $this->assertEquals(\Attribute::TARGET_PROPERTY, $attributeInstance->flags);
    }

    public function test_accepts_empty_string_name(): void
    {
        $attribute = new SerializedName('');

        $this->assertEquals('', $attribute->name);
    }

    public function test_accepts_special_characters_in_name(): void
    {
        $specialNames = [
            'snake_case_name',
            'kebab-case-name',
            'camelCaseName',
            'PascalCaseName',
            'name.with.dots',
            'name with spaces',
            'name123',
            '123name',
            'UPPERCASE_NAME',
            'MiXeD_CaSe-Name.123'
        ];

        foreach ($specialNames as $name) {
            $attribute = new SerializedName($name);
            $this->assertEquals($name, $attribute->name);
        }
    }

    public function test_accepts_unicode_characters(): void
    {
        $unicodeNames = [
            'título',
            'contraseña',
            'configuración',
            'ñoño',
            'café',
            'résumé'
        ];

        foreach ($unicodeNames as $name) {
            $attribute = new SerializedName($name);
            $this->assertEquals($name, $attribute->name);
        }
    }

    public function test_property_is_public_readonly(): void
    {
        $reflection = new \ReflectionClass(SerializedName::class);
        $nameProperty = $reflection->getProperty('name');

        $this->assertTrue($nameProperty->isPublic());
        $this->assertTrue($nameProperty->isReadonly());
    }
}