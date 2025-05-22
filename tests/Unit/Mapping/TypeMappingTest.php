<?php
// tests/Unit/Mapping/TypeMappingTest.php

declare(strict_types=1);

namespace Tests\Unit\Mapping;

use Tests\Fixtures\Automapper\EmptyProfile;
use Ninja\Granite\Mapping\MappingProfile;
use Ninja\Granite\Mapping\PropertyMapping;
use Ninja\Granite\Mapping\TypeMapping;
use PHPUnit\Framework\Attributes\CoversClass;
use Tests\Helpers\TestCase;

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

    public function test_creates_type_mapping(): void
    {
        $this->assertInstanceOf(TypeMapping::class, $this->typeMapping);
    }

    public function test_for_member_adds_property_mapping(): void
    {
        $result = $this->typeMapping->forMember('propertyName', function($mapping) {
            $mapping->mapFrom('sourceProperty');
        });

        $this->assertSame($this->typeMapping, $result); // Method chaining
    }

    public function test_for_member_configures_property_with_map_from(): void
    {
        $this->typeMapping->forMember('destProperty', function($mapping) {
            $mapping->mapFrom('sourceProperty');
        });

        $propertyMapping = $this->profile->getMapping('SourceClass', 'DestClass', 'destProperty');

        $this->assertInstanceOf(PropertyMapping::class, $propertyMapping);
        $this->assertEquals('sourceProperty', $propertyMapping->getSourceProperty());
    }

    public function test_for_member_configures_property_with_transformer(): void
    {
        $transformer = fn($value) => strtoupper($value);

        $this->typeMapping->forMember('name', function($mapping) use ($transformer) {
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
        $this->typeMapping->forMember('ignoredProperty', function($mapping) {
            $mapping->ignore();
        });

        $propertyMapping = $this->profile->getMapping('SourceClass', 'DestClass', 'ignoredProperty');

        $this->assertInstanceOf(PropertyMapping::class, $propertyMapping);
        $this->assertTrue($propertyMapping->isIgnored());
    }

    public function test_for_member_with_complex_configuration(): void
    {
        $this->typeMapping->forMember('fullName', function($mapping) {
            $mapping->mapFrom('firstName')
                ->using(function($value, $sourceData) {
                    return $value . ' ' . ($sourceData['lastName'] ?? '');
                });
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
        $this->typeMapping->forMember('property', function($mapping) {
            $mapping->mapFrom('firstSource');
        });

        // Second configuration should overwrite
        $this->typeMapping->forMember('property', function($mapping) {
            $mapping->mapFrom('secondSource');
        });

        $propertyMapping = $this->profile->getMapping('SourceClass', 'DestClass', 'property');
        $this->assertEquals('secondSource', $propertyMapping->getSourceProperty());
    }

    public function test_configuration_callback_receives_property_mapping(): void
    {
        $receivedMapping = null;

        $this->typeMapping->forMember('test', function($mapping) use (&$receivedMapping) {
            $receivedMapping = $mapping;
            $mapping->mapFrom('source');
        });

        $this->assertInstanceOf(PropertyMapping::class, $receivedMapping);
    }

    public function test_handles_empty_configuration(): void
    {
        $this->typeMapping->forMember('emptyConfig', function($mapping) {
            // No configuration
        });

        $propertyMapping = $this->profile->getMapping('SourceClass', 'DestClass', 'emptyConfig');

        $this->assertInstanceOf(PropertyMapping::class, $propertyMapping);
        $this->assertNull($propertyMapping->getSourceProperty());
        $this->assertFalse($propertyMapping->isIgnored());
    }

    public function test_configuration_with_static_method(): void
    {
        $this->typeMapping->forMember('staticTransform', function($mapping) {
            $mapping->using([self::class, 'staticTransformer']);
        });

        $propertyMapping = $this->profile->getMapping('SourceClass', 'DestClass', 'staticTransform');

        $result = $propertyMapping->transform('test', []);
        $this->assertEquals('STATIC: test', $result);
    }

    public static function staticTransformer($value): string
    {
        return 'STATIC: ' . $value;
    }

    public function test_configuration_with_closure_capturing_variables(): void
    {
        $prefix = 'PREFIX';
        $suffix = 'SUFFIX';

        $this->typeMapping->forMember('captured', function($mapping) use ($prefix, $suffix) {
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
            '123property'
        ];

        foreach ($specialNames as $name) {
            $this->typeMapping->forMember($name, function($mapping) {
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
            $this->typeMapping->forMember("property$i", function($mapping) use ($i) {
                $mapping->mapFrom("source$i")
                    ->using(fn($value) => "transformed_$i: $value");
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
            ->forMember('fullName', function($mapping) {
                $mapping->using(function($value, $sourceData) {
                    return ($sourceData['firstName'] ?? '') . ' ' . ($sourceData['lastName'] ?? '');
                });
            })
            ->forMember('email', function($mapping) {
                $mapping->mapFrom('emailAddress');
            })
            ->forMember('displayAge', function($mapping) {
                $mapping->mapFrom('age')
                    ->using(fn($value) => $value . ' years old');
            })
            ->forMember('password', function($mapping) {
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
        $this->typeMapping->forMember('userName', function($mapping) {
            $mapping->mapFrom('user.name');
        });

        $propertyMapping = $this->profile->getMapping('SourceClass', 'DestClass', 'userName');
        $this->assertEquals('user.name', $propertyMapping->getSourceProperty());
    }

    public function test_configuration_error_handling(): void
    {
        $this->expectException(\TypeError::class);

        // Pass invalid callback (not callable)
        $this->typeMapping->forMember('invalid', 'not-a-callable');
    }

    public function test_readonly_properties_configuration(): void
    {
        // This test ensures TypeMapping works with readonly properties
        $this->typeMapping->forMember('readonlyProp', function($mapping) {
            $mapping->mapFrom('source')
                ->using(fn($value) => 'readonly: ' . $value);
        });

        $propertyMapping = $this->profile->getMapping('SourceClass', 'DestClass', 'readonlyProp');

        $this->assertInstanceOf(PropertyMapping::class, $propertyMapping);
        $this->assertEquals('source', $propertyMapping->getSourceProperty());
    }
}