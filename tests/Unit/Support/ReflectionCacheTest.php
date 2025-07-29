<?php

// tests/Unit/Support/ReflectionCacheTest.php

declare(strict_types=1);

namespace Tests\Unit\Support;

use Ninja\Granite\Exceptions\ReflectionException;
use Ninja\Granite\Support\ReflectionCache;
use PHPUnit\Framework\Attributes\CoversClass;
use ReflectionClass;
use ReflectionProperty;
use Tests\Fixtures\DTOs\SimpleDTO;
use Tests\Helpers\TestCase;

#[CoversClass(ReflectionCache::class)] class ReflectionCacheTest extends TestCase
{
    protected function tearDown(): void
    {
        // Reset cache between tests to ensure isolation
        $this->resetReflectionCache();
        parent::tearDown();
    }

    public function test_gets_class_reflection(): void
    {
        $reflection = ReflectionCache::getClass(SimpleDTO::class);

        $this->assertInstanceOf(ReflectionClass::class, $reflection);
        $this->assertEquals(SimpleDTO::class, $reflection->getName());
        $this->assertTrue($reflection->isReadonly());
    }

    public function test_caches_class_reflection(): void
    {
        $reflection1 = ReflectionCache::getClass(SimpleDTO::class);
        $reflection2 = ReflectionCache::getClass(SimpleDTO::class);

        $this->assertSame($reflection1, $reflection2);
    }

    public function test_throws_exception_for_non_existent_class(): void
    {
        $this->expectException(ReflectionException::class);
        $this->expectExceptionMessage('Class "NonExistentClass" not found');

        ReflectionCache::getClass('NonExistentClass');
    }

    public function test_gets_public_properties(): void
    {
        $properties = ReflectionCache::getPublicProperties(SimpleDTO::class);

        $this->assertIsArray($properties);
        $this->assertNotEmpty($properties);
        $this->assertContainsOnlyInstancesOf(ReflectionProperty::class, $properties);

        // Check that all expected properties are present
        $propertyNames = array_map(fn($prop) => $prop->getName(), $properties);
        $this->assertContains('id', $propertyNames);
        $this->assertContains('name', $propertyNames);
        $this->assertContains('email', $propertyNames);
        $this->assertContains('age', $propertyNames);
    }

    public function test_caches_public_properties(): void
    {
        $properties1 = ReflectionCache::getPublicProperties(SimpleDTO::class);
        $properties2 = ReflectionCache::getPublicProperties(SimpleDTO::class);

        $this->assertSame($properties1, $properties2);
    }

    public function test_properties_are_public_only(): void
    {
        $properties = ReflectionCache::getPublicProperties(SimpleDTO::class);

        foreach ($properties as $property) {
            $this->assertTrue($property->isPublic());
            $this->assertFalse($property->isPrivate());
            $this->assertFalse($property->isProtected());
        }
    }

    public function test_handles_class_with_no_public_properties(): void
    {
        $properties = ReflectionCache::getPublicProperties(EmptyTestClass::class);

        $this->assertIsArray($properties);
        $this->assertEmpty($properties);
    }

    public function test_properties_cache_is_independent_per_class(): void
    {
        $simpleProperties = ReflectionCache::getPublicProperties(SimpleDTO::class);
        $emptyProperties = ReflectionCache::getPublicProperties(EmptyTestClass::class);

        $this->assertNotSame($simpleProperties, $emptyProperties);
        $this->assertNotEmpty($simpleProperties);
        $this->assertEmpty($emptyProperties);
    }

    public function test_class_cache_stores_different_classes(): void
    {
        $simpleReflection = ReflectionCache::getClass(SimpleDTO::class);
        $emptyReflection = ReflectionCache::getClass(EmptyTestClass::class);

        $this->assertNotSame($simpleReflection, $emptyReflection);
        $this->assertEquals(SimpleDTO::class, $simpleReflection->getName());
        $this->assertEquals(EmptyTestClass::class, $emptyReflection->getName());
    }

    /**
     * Reset the reflection cache for test isolation
     */
    private function resetReflectionCache(): void
    {
        $reflection = new ReflectionClass(ReflectionCache::class);

        if ($reflection->hasProperty('classCache')) {
            $classCache = $reflection->getProperty('classCache');
            $classCache->setAccessible(true);
            $classCache->setValue(null, []);
        }

        if ($reflection->hasProperty('propertiesCache')) {
            $propertiesCache = $reflection->getProperty('propertiesCache');
            $propertiesCache->setAccessible(true);
            $propertiesCache->setValue(null, []);
        }
    }
}

/**
 * Test class with no public properties
 */
final class EmptyTestClass
{
    private string $private = 'private';
    private string $protected = 'protected';

    public function __construct()
    {
        // Initialize properties if needed
    }
}
