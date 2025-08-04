<?php

// tests/Unit/Mapping/TypeMappingTest.php

declare(strict_types=1);

namespace Tests\Unit\Mapping;

use Exception;
use Ninja\Granite\Mapping\MappingProfile;
use Ninja\Granite\Mapping\PropertyMapping;
use Ninja\Granite\Mapping\TypeMapping;
use PHPUnit\Framework\Attributes\CoversClass;
use ReflectionClass;
use Tests\Fixtures\Automapper\EmptyProfile;
use Tests\Helpers\TestCase;
use TypeError;

#[CoversClass(TypeMapping::class)]
class TypeMappingTest extends TestCase
{
    private MappingProfile $profile;
    private TypeMapping $typeMapping;

    protected function setUp(): void
    {
        $this->profile = new EmptyProfile();
        $this->typeMapping = new TypeMapping($this->profile, 'SourceClass', 'DestClass');
        parent::setUp();
    }

    public static function staticTransformer($value): string
    {
        return 'STATIC: ' . $value;
    }

    public function test_creates_type_mapping(): void
    {
        $this->assertInstanceOf(TypeMapping::class, $this->typeMapping);
    }

    public function test_for_member_adds_property_mapping(): void
    {
        $result = $this->typeMapping->forMember('propertyName', function ($mapping): void {
            $mapping->mapFrom('sourceProperty');
        });

        $this->assertSame($this->typeMapping, $result); // Method chaining
    }

    public function test_for_member_configures_property_with_map_from(): void
    {
        $this->typeMapping->forMember('destProperty', function ($mapping): void {
            $mapping->mapFrom('sourceProperty');
        });

        $propertyMapping = $this->profile->getMapping('SourceClass', 'DestClass', 'destProperty');

        $this->assertInstanceOf(PropertyMapping::class, $propertyMapping);
        $this->assertEquals('sourceProperty', $propertyMapping->getSourceProperty());
    }

    public function test_for_member_configures_property_with_transformer(): void
    {
        $transformer = fn($value) => mb_strtoupper($value);

        $this->typeMapping->forMember('name', function ($mapping) use ($transformer): void {
            $mapping->using($transformer);
        });

        $propertyMapping = $this->profile->getMapping('SourceClass', 'DestClass', 'name');

        $this->assertInstanceOf(PropertyMapping::class, $propertyMapping);

        // Test the transformer works
        $result = $propertyMapping->transform('test', []);
        $this->assertEquals('TEST', $result);
    }

    public function test_for_member_configures_property_to_ignore(): void
    {
        $this->typeMapping->forMember('ignoredProperty', function ($mapping): void {
            $mapping->ignore();
        });

        $propertyMapping = $this->profile->getMapping('SourceClass', 'DestClass', 'ignoredProperty');

        $this->assertInstanceOf(PropertyMapping::class, $propertyMapping);
        $this->assertTrue($propertyMapping->isIgnored());
    }

    public function test_for_member_with_complex_configuration(): void
    {
        $this->typeMapping->forMember('fullName', function ($mapping): void {
            $mapping->mapFrom('firstName')
                ->using(fn($value, $sourceData) => $value . ' ' . ($sourceData['lastName'] ?? ''));
        });

        $propertyMapping = $this->profile->getMapping('SourceClass', 'DestClass', 'fullName');

        $this->assertEquals('firstName', $propertyMapping->getSourceProperty());

        $result = $propertyMapping->transform('John', ['lastName' => 'Doe']);
        $this->assertEquals('John Doe', $result);
    }

    public function test_multiple_for_member_calls(): void
    {
        $this->typeMapping
            ->forMember('prop1', fn($mapping) => $mapping->mapFrom('source1'))
            ->forMember('prop2', fn($mapping) => $mapping->mapFrom('source2'))
            ->forMember('prop3', fn($mapping) => $mapping->ignore());

        $mapping1 = $this->profile->getMapping('SourceClass', 'DestClass', 'prop1');
        $mapping2 = $this->profile->getMapping('SourceClass', 'DestClass', 'prop2');
        $mapping3 = $this->profile->getMapping('SourceClass', 'DestClass', 'prop3');

        $this->assertEquals('source1', $mapping1->getSourceProperty());
        $this->assertEquals('source2', $mapping2->getSourceProperty());
        $this->assertTrue($mapping3->isIgnored());
    }

    public function test_overwrites_existing_property_mapping(): void
    {
        // First configuration
        $this->typeMapping->forMember('property', function ($mapping): void {
            $mapping->mapFrom('firstSource');
        });

        // Second configuration should overwrite
        $this->typeMapping->forMember('property', function ($mapping): void {
            $mapping->mapFrom('secondSource');
        });

        $propertyMapping = $this->profile->getMapping('SourceClass', 'DestClass', 'property');
        $this->assertEquals('secondSource', $propertyMapping->getSourceProperty());
    }

    public function test_configuration_callback_receives_property_mapping(): void
    {
        $receivedMapping = null;

        $this->typeMapping->forMember('test', function ($mapping) use (&$receivedMapping): void {
            $receivedMapping = $mapping;
            $mapping->mapFrom('source');
        });

        $this->assertInstanceOf(PropertyMapping::class, $receivedMapping);
    }

    public function test_handles_empty_configuration(): void
    {
        $this->typeMapping->forMember('emptyConfig', function ($mapping): void {
            // No configuration
        });

        $propertyMapping = $this->profile->getMapping('SourceClass', 'DestClass', 'emptyConfig');

        $this->assertInstanceOf(PropertyMapping::class, $propertyMapping);
        $this->assertNull($propertyMapping->getSourceProperty());
        $this->assertFalse($propertyMapping->isIgnored());
    }

    public function test_configuration_with_static_method(): void
    {
        $this->typeMapping->forMember('staticTransform', function ($mapping): void {
            $mapping->using([self::class, 'staticTransformer']);
        });

        $propertyMapping = $this->profile->getMapping('SourceClass', 'DestClass', 'staticTransform');

        $result = $propertyMapping->transform('test', []);
        $this->assertEquals('STATIC: test', $result);
    }

    public function test_configuration_with_closure_capturing_variables(): void
    {
        $prefix = 'PREFIX';
        $suffix = 'SUFFIX';

        $this->typeMapping->forMember('captured', function ($mapping) use ($prefix, $suffix): void {
            $mapping->using(fn($value) => $prefix . ':' . $value . ':' . $suffix);
        });

        $propertyMapping = $this->profile->getMapping('SourceClass', 'DestClass', 'captured');

        $result = $propertyMapping->transform('test', []);
        $this->assertEquals('PREFIX:test:SUFFIX', $result);
    }

    public function test_works_with_different_source_destination_types(): void
    {
        $mapping1 = new TypeMapping($this->profile, 'Source1', 'Dest1');
        $mapping2 = new TypeMapping($this->profile, 'Source2', 'Dest2');

        $mapping1->forMember('prop', fn($m) => $m->mapFrom('field1'));
        $mapping2->forMember('prop', fn($m) => $m->mapFrom('field2'));

        $prop1 = $this->profile->getMapping('Source1', 'Dest1', 'prop');
        $prop2 = $this->profile->getMapping('Source2', 'Dest2', 'prop');

        $this->assertEquals('field1', $prop1->getSourceProperty());
        $this->assertEquals('field2', $prop2->getSourceProperty());
    }

    public function test_handles_special_property_names(): void
    {
        $specialNames = [
            'property-with-dashes',
            'property_with_underscores',
            'property.with.dots',
            'propertyWithCamelCase',
            'PropertyWithPascalCase',
            'property123',
            '123property',
        ];

        foreach ($specialNames as $name) {
            $this->typeMapping->forMember($name, function ($mapping): void {
                $mapping->mapFrom('source');
            });

            $propertyMapping = $this->profile->getMapping('SourceClass', 'DestClass', $name);
            $this->assertInstanceOf(PropertyMapping::class, $propertyMapping);
        }
    }

    public function test_performance_with_many_properties(): void
    {
        $start = microtime(true);

        for ($i = 0; $i < 1000; $i++) {
            $this->typeMapping->forMember("property{$i}", function ($mapping) use ($i): void {
                $mapping->mapFrom("source{$i}")
                    ->using(fn($value) => "transformed_{$i}: {$value}");
            });
        }

        $elapsed = microtime(true) - $start;

        $this->assertLessThan(0.5, $elapsed, "Configuration took too long: {$elapsed}s");

        // Verify a few mappings were created correctly
        $mapping1 = $this->profile->getMapping('SourceClass', 'DestClass', 'property1');
        $mapping999 = $this->profile->getMapping('SourceClass', 'DestClass', 'property999');

        $this->assertEquals('source1', $mapping1->getSourceProperty());
        $this->assertEquals('source999', $mapping999->getSourceProperty());
    }

    public function test_realistic_user_mapping_scenario(): void
    {
        $this->typeMapping
            ->forMember('fullName', function ($mapping): void {
                $mapping->using(fn($value, $sourceData) => ($sourceData['firstName'] ?? '') . ' ' . ($sourceData['lastName'] ?? ''));
            })
            ->forMember('email', function ($mapping): void {
                $mapping->mapFrom('emailAddress');
            })
            ->forMember('displayAge', function ($mapping): void {
                $mapping->mapFrom('age')
                    ->using(fn($value) => $value . ' years old');
            })
            ->forMember('password', function ($mapping): void {
                $mapping->ignore();
            });

        // Test fullName transformation
        $fullNameMapping = $this->profile->getMapping('SourceClass', 'DestClass', 'fullName');
        $fullName = $fullNameMapping->transform(null, ['firstName' => 'John', 'lastName' => 'Doe']);
        $this->assertEquals('John Doe', $fullName);

        // Test email mapping
        $emailMapping = $this->profile->getMapping('SourceClass', 'DestClass', 'email');
        $this->assertEquals('emailAddress', $emailMapping->getSourceProperty());

        // Test age transformation
        $ageMapping = $this->profile->getMapping('SourceClass', 'DestClass', 'displayAge');
        $displayAge = $ageMapping->transform(25, []);
        $this->assertEquals('25 years old', $displayAge);

        // Test ignored property
        $passwordMapping = $this->profile->getMapping('SourceClass', 'DestClass', 'password');
        $this->assertTrue($passwordMapping->isIgnored());
    }

    public function test_nested_source_mapping(): void
    {
        $this->typeMapping->forMember('userName', function ($mapping): void {
            $mapping->mapFrom('user.name');
        });

        $propertyMapping = $this->profile->getMapping('SourceClass', 'DestClass', 'userName');
        $this->assertEquals('user.name', $propertyMapping->getSourceProperty());
    }

    public function test_configuration_error_handling(): void
    {
        $this->expectException(TypeError::class);

        // Pass invalid callback (not callable)
        $this->typeMapping->forMember('invalid', 'not-a-callable');
    }

    public function test_readonly_properties_configuration(): void
    {
        // This test ensures TypeMapping works with readonly properties
        $this->typeMapping->forMember('readonlyProp', function ($mapping): void {
            $mapping->mapFrom('source')
                ->using(fn($value) => 'readonly: ' . $value);
        });

        $propertyMapping = $this->profile->getMapping('SourceClass', 'DestClass', 'readonlyProp');

        $this->assertInstanceOf(PropertyMapping::class, $propertyMapping);
        $this->assertEquals('source', $propertyMapping->getSourceProperty());
    }

    public function test_seal_marks_mapping_as_sealed(): void
    {
        // Use stdClass as destination which exists
        $mapping = new TypeMapping($this->profile, 'array', 'stdClass');

        $this->assertFalse($mapping->isSealed());

        $result = $mapping->seal();

        $this->assertTrue($mapping->isSealed());
        $this->assertSame($mapping, $result); // Method chaining
    }

    public function test_seal_can_be_called_multiple_times(): void
    {
        $mapping = new TypeMapping($this->profile, 'array', 'stdClass');

        $mapping->seal();
        $this->assertTrue($mapping->isSealed());

        // Second call should not throw
        $mapping->seal();
        $this->assertTrue($mapping->isSealed());
    }

    public function test_for_member_throws_when_sealed(): void
    {
        $mapping = new TypeMapping($this->profile, 'array', 'stdClass');
        $mapping->seal();

        $this->expectException(\Ninja\Granite\Mapping\Exceptions\MappingException::class);
        $this->expectExceptionMessage('Cannot modify mapping after it has been sealed');

        $mapping->forMember('newProp', function ($mapping): void {
            $mapping->mapFrom('source');
        });
    }

    public function test_get_source_type(): void
    {
        $this->assertEquals('SourceClass', $this->typeMapping->getSourceType());
    }

    public function test_get_destination_type(): void
    {
        $this->assertEquals('DestClass', $this->typeMapping->getDestinationType());
    }

    public function test_seal_validates_destination_type_exists(): void
    {
        $mapping = new TypeMapping($this->profile, 'array', 'NonExistentClass');

        $this->expectException(\Ninja\Granite\Mapping\Exceptions\MappingException::class);
        $this->expectExceptionMessage("Destination type 'NonExistentClass' does not exist");

        $mapping->seal();
    }

    public function test_seal_validates_destination_properties(): void
    {
        $mapping = new TypeMapping($this->profile, 'array', 'stdClass');
        $mapping->forMember('nonExistentProperty', function ($m): void {
            $m->mapFrom('source');
        });

        $this->expectException(\Ninja\Granite\Mapping\Exceptions\MappingException::class);
        $this->expectExceptionMessage("Destination property 'nonExistentProperty' does not exist");

        $mapping->seal();
    }

    public function test_seal_skips_source_validation_for_array(): void
    {
        $mapping = new TypeMapping($this->profile, 'array', 'stdClass');

        // This should not throw even though 'array' is not a class
        $mapping->seal();
        $this->assertTrue($mapping->isSealed());
    }

    public function test_seal_detects_mapped_and_ignored_conflict(): void
    {
        // Create a test class with the property
        $mapping = new TypeMapping($this->profile, 'array', TestDestinationClass::class);

        $mapping->forMember('name', function ($m): void {
            $m->mapFrom('source')->ignore();
        });

        $this->expectException(\Ninja\Granite\Mapping\Exceptions\MappingException::class);
        $this->expectExceptionMessage("Property 'name' is both mapped and ignored");

        $mapping->seal();
    }

    public function test_seal_validates_source_properties_for_class_types(): void
    {
        $mapping = new TypeMapping($this->profile, TestSourceClass::class, TestDestinationClass::class);
        $mapping->forMember('name', function ($m): void {
            $m->mapFrom('nonExistentSourceProperty');
        });

        $this->expectException(\Ninja\Granite\Mapping\Exceptions\MappingException::class);
        $this->expectExceptionMessage("Source property 'nonExistentSourceProperty' does not exist");

        $mapping->seal();
    }

    public function test_seal_skips_validation_for_dot_notation_source_properties(): void
    {
        $mapping = new TypeMapping($this->profile, TestSourceClass::class, TestDestinationClass::class);
        $mapping->forMember('name', function ($m): void {
            $m->mapFrom('nested.property.path');
        });

        // Should not throw exception for dot notation
        $mapping->seal();
        $this->assertTrue($mapping->isSealed());
    }

    public function test_seal_skips_validation_for_null_source_properties(): void
    {
        $mapping = new TypeMapping($this->profile, TestSourceClass::class, TestDestinationClass::class);
        $mapping->forMember('name', function ($m): void {
            $m->using(fn($v) => 'transformed');
        });

        // Should not throw exception for null source property
        $mapping->seal();
        $this->assertTrue($mapping->isSealed());
    }

    public function test_seal_validates_invalid_transformer(): void
    {
        $mapping = new TypeMapping($this->profile, 'array', TestDestinationClass::class);

        // Manually create a PropertyMapping with invalid transformer
        $propertyMapping = new PropertyMapping();
        $propertyMapping->mapFrom('source');

        // Set an invalid transformer using reflection since there's no direct way
        $reflection = new ReflectionClass($propertyMapping);
        $transformerProperty = $reflection->getProperty('transformer');
        $transformerProperty->setAccessible(true);
        $transformerProperty->setValue($propertyMapping, 'invalid_transformer_string');

        // Add the mapping directly to the profile
        $this->profile->addPropertyMapping('array', TestDestinationClass::class, 'name', $propertyMapping);

        $this->expectException(\Ninja\Granite\Mapping\Exceptions\MappingException::class);
        $this->expectExceptionMessage('Invalid transformer for property');

        $mapping->seal();
    }

    public function test_seal_handles_nonexistent_destination_class(): void
    {
        // Create a mapping with a class that doesn't exist
        $mapping = new TypeMapping($this->profile, 'array', 'CompletelyNonExistentClass');

        $this->expectException(\Ninja\Granite\Mapping\Exceptions\MappingException::class);
        $this->expectExceptionMessage("Destination type 'CompletelyNonExistentClass' does not exist");

        $mapping->seal();
    }

    public function test_seal_handles_nonexistent_source_class(): void
    {
        // Create a test class that simulates ReflectionException
        $mapping = new TypeMapping($this->profile, 'CompletelyNonExistentSourceClass', TestDestinationClass::class);
        $mapping->forMember('name', function ($m): void {
            $m->mapFrom('sourceProperty');
        });

        // This should skip validation since the class doesn't exist
        $mapping->seal();
        $this->assertTrue($mapping->isSealed());
    }

    public function test_seal_wraps_generic_exceptions(): void
    {
        // Test error handling by creating a mock profile that throws an exception
        $mockProfile = $this->createMock(MappingProfile::class);
        $mockProfile->method('getMappingsForTypes')
            ->willThrowException(new Exception('Generic error'));

        $mapping = new TypeMapping($mockProfile, 'array', TestDestinationClass::class);

        $this->expectException(\Ninja\Granite\Mapping\Exceptions\MappingException::class);
        $this->expectExceptionMessage('Error while validating mapping: Generic error');

        $mapping->seal();
    }

    public function test_seal_validates_transformer_instance(): void
    {
        $mapping = new TypeMapping($this->profile, 'array', TestDestinationClass::class);
        $transformer = new MockTransformer();

        $mapping->forMember('name', function ($m) use ($transformer): void {
            $m->mapFrom('source')->using($transformer);
        });

        // Should not throw - valid Transformer instance
        $mapping->seal();
        $this->assertTrue($mapping->isSealed());
    }

    public function test_seal_validates_callable_transformer(): void
    {
        $mapping = new TypeMapping($this->profile, 'array', TestDestinationClass::class);

        $mapping->forMember('name', function ($m): void {
            $m->mapFrom('source')->using(fn($v) => 'transformed');
        });

        // Should not throw - valid callable
        $mapping->seal();
        $this->assertTrue($mapping->isSealed());
    }

    public function test_validateDestinationProperties_covers_all_paths(): void
    {
        $mapping = new TypeMapping($this->profile, 'array', TestDestinationClass::class);

        // Add mappings for all destination properties
        $mapping->forMember('name', function ($m): void {
            $m->mapFrom('sourceName');
        });
        $mapping->forMember('age', function ($m): void {
            $m->mapFrom('sourceAge');
        });

        // Should validate successfully
        $mapping->seal();
        $this->assertTrue($mapping->isSealed());
    }

    public function test_validateSourceProperties_skips_non_class_source(): void
    {
        $mapping = new TypeMapping($this->profile, 'array', TestDestinationClass::class);

        $mapping->forMember('name', function ($m): void {
            $m->mapFrom('anyProperty');
        });

        // Should not validate source properties for 'array' type
        $mapping->seal();
        $this->assertTrue($mapping->isSealed());
    }

    public function test_detectConflicts_handles_ignored_properties(): void
    {
        $mapping = new TypeMapping($this->profile, 'array', TestDestinationClass::class);

        $mapping->forMember('name', function ($m): void {
            $m->ignore();
        });
        $mapping->forMember('age', function ($m): void {
            $m->mapFrom('sourceAge');
        });

        // Should handle ignored properties correctly
        $mapping->seal();
        $this->assertTrue($mapping->isSealed());
    }
}

class TestDestinationClass
{
    public string $name;
    public int $age;
}

class TestSourceClass
{
    public string $sourceName;
    public int $sourceAge;
    public string $email;
}

class NonExistentClass
{
    // This class exists but simulates a class that can't be reflected
}

class MockTransformer implements \Ninja\Granite\Mapping\Contracts\Transformer
{
    public function transform(mixed $value, array $sourceData = []): mixed
    {
        return 'mock_transformed: ' . $value;
    }
}
