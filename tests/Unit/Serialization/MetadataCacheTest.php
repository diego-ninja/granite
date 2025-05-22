<?php
// tests/Unit/Serialization/MetadataCacheTest.php

declare(strict_types=1);

namespace Tests\Unit\Serialization;

use Ninja\Granite\Serialization\MetadataCache;
use Ninja\Granite\Serialization\Metadata;
use PHPUnit\Framework\Attributes\CoversClass;
use Tests\Fixtures\DTOs\AttributeBasedDTO;
use Tests\Fixtures\DTOs\MethodBasedDTO;
use Tests\Fixtures\DTOs\MixedSerializationDTO;
use Tests\Fixtures\DTOs\OnlyHiddenDTO;
use Tests\Fixtures\DTOs\OnlySerializedNameDTO;
use Tests\Fixtures\DTOs\PlainDTO;
use Tests\Fixtures\DTOs\ProtectedMethodsDTO;
use Tests\Helpers\TestCase;
use Tests\Fixtures\DTOs\SerializableDTO;


#[CoversClass(MetadataCache::class)] class MetadataCacheTest extends TestCase
{
    protected function tearDown(): void
    {
        // Reset cache between tests for proper isolation
        $this->resetMetadataCache();
        parent::tearDown();
    }

    public function test_gets_metadata_for_class(): void
    {
        $metadata = MetadataCache::getMetadata(SerializableDTO::class);

        $this->assertInstanceOf(Metadata::class, $metadata);
    }

    public function test_caches_metadata_instances(): void
    {
        $metadata1 = MetadataCache::getMetadata(SerializableDTO::class);
        $metadata2 = MetadataCache::getMetadata(SerializableDTO::class);

        $this->assertSame($metadata1, $metadata2);
    }

    public function test_builds_different_metadata_for_different_classes(): void
    {
        $metadata1 = MetadataCache::getMetadata(SerializableDTO::class);
        $metadata2 = MetadataCache::getMetadata(MethodBasedDTO::class);

        $this->assertNotSame($metadata1, $metadata2);
        $this->assertInstanceOf(Metadata::class, $metadata1);
        $this->assertInstanceOf(Metadata::class, $metadata2);
    }

    public function test_builds_metadata_from_attributes(): void
    {
        $metadata = MetadataCache::getMetadata(AttributeBasedDTO::class);

        // Should map firstName to first_name based on SerializedName attribute
        $this->assertEquals('first_name', $metadata->getSerializedName('firstName'));

        // Should map lastName to last_name based on SerializedName attribute
        $this->assertEquals('last_name', $metadata->getSerializedName('lastName'));

        // Should keep email as-is (no SerializedName attribute)
        $this->assertEquals('email', $metadata->getSerializedName('email'));

        // Should hide password based on Hidden attribute
        $this->assertTrue($metadata->isHidden('password'));

        // Should hide apiToken based on Hidden attribute
        $this->assertTrue($metadata->isHidden('apiToken'));

        // Should not hide visible properties
        $this->assertFalse($metadata->isHidden('firstName'));
        $this->assertFalse($metadata->isHidden('email'));
    }

    public function test_builds_metadata_from_methods(): void
    {
        $metadata = MetadataCache::getMetadata(MethodBasedDTO::class);

        // Should respect serializedNames() method
        $this->assertEquals('email_address', $metadata->getSerializedName('email'));
        $this->assertEquals('user_name', $metadata->getSerializedName('username'));

        // Should respect hiddenProperties() method
        $this->assertTrue($metadata->isHidden('internalId'));
        $this->assertTrue($metadata->isHidden('createdAt'));

        // Should not hide non-hidden properties
        $this->assertFalse($metadata->isHidden('email'));
        $this->assertFalse($metadata->isHidden('username'));
    }

    public function test_attributes_override_methods_when_both_present(): void
    {
        $metadata = MetadataCache::getMetadata(MixedSerializationDTO::class);

        // Attribute should override method for property name mapping
        $this->assertEquals('given_name', $metadata->getSerializedName('firstName')); // From attribute

        // Method should be used when no attribute present
        $this->assertEquals('family_name', $metadata->getSerializedName('lastName')); // From method

        // Attribute should override method for hiding
        $this->assertTrue($metadata->isHidden('password')); // From attribute
        $this->assertTrue($metadata->isHidden('secret')); // From method only
    }

    public function test_handles_class_with_no_serialization_config(): void
    {
        $metadata = MetadataCache::getMetadata(PlainDTO::class);

        // Should return property names as-is
        $this->assertEquals('firstName', $metadata->getSerializedName('firstName'));
        $this->assertEquals('lastName', $metadata->getSerializedName('lastName'));

        // Should not hide any properties
        $this->assertFalse($metadata->isHidden('firstName'));
        $this->assertFalse($metadata->isHidden('lastName'));
        $this->assertFalse($metadata->isHidden('email'));
    }

    public function test_handles_class_with_only_hidden_attributes(): void
    {
        $metadata = MetadataCache::getMetadata(OnlyHiddenDTO::class);

        // Should not change property names
        $this->assertEquals('name', $metadata->getSerializedName('name'));
        $this->assertEquals('secret', $metadata->getSerializedName('secret'));

        // Should hide only marked properties
        $this->assertFalse($metadata->isHidden('name'));
        $this->assertTrue($metadata->isHidden('secret'));
    }

    public function test_handles_class_with_only_serialized_name_attributes(): void
    {
        $metadata = MetadataCache::getMetadata(OnlySerializedNameDTO::class);

        // Should map property names
        $this->assertEquals('full_name', $metadata->getSerializedName('name'));
        $this->assertEquals('description', $metadata->getSerializedName('description')); // No attribute

        // Should not hide any properties
        $this->assertFalse($metadata->isHidden('name'));
        $this->assertFalse($metadata->isHidden('description'));
    }

    public function test_cache_isolation_between_classes(): void
    {
        // Load metadata for first class
        $metadata1 = MetadataCache::getMetadata(SerializableDTO::class);
        $this->assertEquals('first_name', $metadata1->getSerializedName('firstName'));

        // Load metadata for second class
        $metadata2 = MetadataCache::getMetadata(MethodBasedDTO::class);
        $this->assertEquals('email_address', $metadata2->getSerializedName('email'));

        // Verify first class metadata is still correct
        $metadata1Again = MetadataCache::getMetadata(SerializableDTO::class);
        $this->assertSame($metadata1, $metadata1Again);
        $this->assertEquals('first_name', $metadata1Again->getSerializedName('firstName'));
    }

    public function test_throws_exception_for_non_existent_class(): void
    {
        $this->expectException(\Ninja\Granite\Exceptions\ReflectionException::class);
        $this->expectExceptionMessage('Class "NonExistentClass" not found');

        MetadataCache::getMetadata('NonExistentClass');
    }

    public function test_handles_class_with_protected_serialization_methods(): void
    {
        $metadata = MetadataCache::getMetadata(ProtectedMethodsDTO::class);

        // Should invoke protected static methods using reflection
        $this->assertEquals('custom_name', $metadata->getSerializedName('name'));
        $this->assertTrue($metadata->isHidden('hiddenField'));
    }

    public function test_performance_with_repeated_calls(): void
    {
        $className = SerializableDTO::class;

        // First call builds metadata
        $start = microtime(true);
        $metadata1 = MetadataCache::getMetadata($className);
        $firstCallTime = microtime(true) - $start;

        // Subsequent calls should be much faster (cached)
        $start = microtime(true);
        $metadata2 = MetadataCache::getMetadata($className);
        $metadata3 = MetadataCache::getMetadata($className);
        $metadata4 = MetadataCache::getMetadata($className);
        $cachedCallsTime = microtime(true) - $start;

        // Verify same instances
        $this->assertSame($metadata1, $metadata2);
        $this->assertSame($metadata1, $metadata3);
        $this->assertSame($metadata1, $metadata4);

        // Cached calls should be significantly faster
        $this->assertLessThan($firstCallTime, $cachedCallsTime);
    }

    /**
     * Reset the metadata cache for test isolation
     */
    private function resetMetadataCache(): void
    {
        $reflection = new \ReflectionClass(MetadataCache::class);

        if ($reflection->hasProperty('metadataCache')) {
            $cacheProperty = $reflection->getProperty('metadataCache');
            $cacheProperty->setAccessible(true);
            $cacheProperty->setValue([]);
        }
    }
}













