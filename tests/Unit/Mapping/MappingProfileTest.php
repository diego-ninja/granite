<?php

// tests/Unit/Mapping/MappingProfileTest.php

declare(strict_types=1);

namespace Tests\Unit\Mapping;

use Ninja\Granite\Mapping\MappingProfile;
use Ninja\Granite\Mapping\PropertyMapping;
use Ninja\Granite\Mapping\TypeMapping;
use PHPUnit\Framework\Attributes\CoversClass;
use Tests\Fixtures\Automapper\BasicProfile;
use Tests\Fixtures\Automapper\ComplexProfile;
use Tests\Fixtures\Automapper\ConfigureProfile;
use Tests\Fixtures\Automapper\CreateMapProfile;
use Tests\Fixtures\Automapper\EmptyProfile;
use Tests\Fixtures\Automapper\InheritedProfile;
use Tests\Fixtures\Automapper\PerformanceProfile;
use Tests\Fixtures\Automapper\PropertyMappingProfile;
use Tests\Helpers\TestCase;

#[CoversClass(MappingProfile::class)]
class MappingProfileTest extends TestCase
{
    public function test_creates_mapping_profile(): void
    {
        $profile = new BasicProfile();

        $this->assertInstanceOf(MappingProfile::class, $profile);
    }

    public function test_configure_method_is_called_during_construction(): void
    {
        $profile = new ConfigureProfile();

        $this->assertTrue($profile->configureWasCalled);
    }

    public function test_creates_type_mapping(): void
    {
        $profile = new CreateMapProfile();

        // Verify that createMap was called and TypeMapping was created
        $this->assertInstanceOf(TypeMapping::class, $profile->createdMapping);
    }

    public function test_adds_property_mapping(): void
    {
        $profile = new PropertyMappingProfile();
        $propertyMapping = new PropertyMapping();

        $profile->addPropertyMapping('SourceClass', 'DestClass', 'property', $propertyMapping);

        $retrieved = $profile->getMapping('SourceClass', 'DestClass', 'property');
        $this->assertSame($propertyMapping, $retrieved);
    }

    public function test_gets_property_mapping(): void
    {
        $profile = new PropertyMappingProfile();
        $propertyMapping = new PropertyMapping();

        $profile->addPropertyMapping('Source', 'Dest', 'prop', $propertyMapping);

        $result = $profile->getMapping('Source', 'Dest', 'prop');
        $this->assertSame($propertyMapping, $result);
    }

    public function test_returns_null_for_non_existent_mapping(): void
    {
        $profile = new PropertyMappingProfile();

        $result = $profile->getMapping('NonExistent', 'Class', 'property');
        $this->assertNull($result);
    }

    public function test_mapping_key_format(): void
    {
        $profile = new PropertyMappingProfile();
        $mapping1 = new PropertyMapping();
        $mapping2 = new PropertyMapping();

        // Add mappings with same source but different destination
        $profile->addPropertyMapping('Source', 'Dest1', 'prop', $mapping1);
        $profile->addPropertyMapping('Source', 'Dest2', 'prop', $mapping2);

        $this->assertSame($mapping1, $profile->getMapping('Source', 'Dest1', 'prop'));
        $this->assertSame($mapping2, $profile->getMapping('Source', 'Dest2', 'prop'));
    }

    public function test_overwrites_existing_property_mapping(): void
    {
        $profile = new PropertyMappingProfile();
        $originalMapping = new PropertyMapping();
        $newMapping = new PropertyMapping();

        $profile->addPropertyMapping('Source', 'Dest', 'prop', $originalMapping);
        $profile->addPropertyMapping('Source', 'Dest', 'prop', $newMapping);

        $result = $profile->getMapping('Source', 'Dest', 'prop');
        $this->assertSame($newMapping, $result);
        $this->assertNotSame($originalMapping, $result);
    }

    public function test_handles_multiple_properties_for_same_types(): void
    {
        $profile = new PropertyMappingProfile();
        $mapping1 = new PropertyMapping();
        $mapping2 = new PropertyMapping();

        $profile->addPropertyMapping('Source', 'Dest', 'prop1', $mapping1);
        $profile->addPropertyMapping('Source', 'Dest', 'prop2', $mapping2);

        $this->assertSame($mapping1, $profile->getMapping('Source', 'Dest', 'prop1'));
        $this->assertSame($mapping2, $profile->getMapping('Source', 'Dest', 'prop2'));
    }

    public function test_case_sensitive_mapping_keys(): void
    {
        $profile = new PropertyMappingProfile();
        $mapping = new PropertyMapping();

        $profile->addPropertyMapping('Source', 'Dest', 'Property', $mapping);

        $this->assertSame($mapping, $profile->getMapping('Source', 'Dest', 'Property'));
        $this->assertNull($profile->getMapping('Source', 'Dest', 'property')); // Different case
        $this->assertNull($profile->getMapping('source', 'Dest', 'Property')); // Different case
    }

    public function test_complex_mapping_scenario(): void
    {
        $profile = new ComplexProfile();

        // Test that all mappings were created correctly
        $userNameMapping = $profile->getMapping('UserEntity', 'UserDTO', 'name');
        $userEmailMapping = $profile->getMapping('UserEntity', 'UserDTO', 'email');
        $orderTotalMapping = $profile->getMapping('OrderEntity', 'OrderDTO', 'total');

        $this->assertNotNull($userNameMapping);
        $this->assertNotNull($userEmailMapping);
        $this->assertNotNull($orderTotalMapping);
    }

    public function test_inheritance_support(): void
    {
        $profile = new InheritedProfile();

        // Should have mappings from both parent and child
        $parentMapping = $profile->getMapping('Parent', 'ParentDTO', 'parentProp');
        $childMapping = $profile->getMapping('Child', 'ChildDTO', 'childProp');

        $this->assertNotNull($parentMapping);
        $this->assertNotNull($childMapping);
    }

    public function test_profile_with_no_mappings(): void
    {
        $profile = new EmptyProfile();

        $result = $profile->getMapping('Any', 'Class', 'property');
        $this->assertNull($result);
    }

    public function test_performance_with_many_mappings(): void
    {
        $profile = new PerformanceProfile();

        $start = microtime(true);

        // Add many mappings
        for ($i = 0; $i < 1000; $i++) {
            $mapping = new PropertyMapping();
            $profile->addPropertyMapping("Source{$i}", "Dest{$i}", "prop{$i}", $mapping);
        }

        // Retrieve many mappings
        for ($i = 0; $i < 1000; $i++) {
            $profile->getMapping("Source{$i}", "Dest{$i}", "prop{$i}");
        }

        $elapsed = microtime(true) - $start;

        $this->assertLessThan(0.1, $elapsed, "Profile operations took too long: {$elapsed}s");
    }

    public function test_mapping_with_special_characters(): void
    {
        $profile = new PropertyMappingProfile();
        $mapping = new PropertyMapping();

        $profile->addPropertyMapping('Source-Class', 'Dest_Class', 'prop.name', $mapping);

        $result = $profile->getMapping('Source-Class', 'Dest_Class', 'prop.name');
        $this->assertSame($mapping, $result);
    }

    public function test_mapping_with_namespace_classes(): void
    {
        $profile = new PropertyMappingProfile();
        $mapping = new PropertyMapping();

        $sourceClass = 'App\\Models\\User';
        $destClass = 'App\\DTOs\\UserDTO';

        $profile->addPropertyMapping($sourceClass, $destClass, 'name', $mapping);

        $result = $profile->getMapping($sourceClass, $destClass, 'name');
        $this->assertSame($mapping, $result);
    }
}
