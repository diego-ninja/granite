<?php

namespace Tests\Unit\Mapping;

use DateTimeImmutable;
use DateTimeInterface;
use InvalidArgumentException;
use Ninja\Granite\Exceptions\GraniteException;
use Ninja\Granite\GraniteDTO;
use Ninja\Granite\Mapping\MapperConfig;
use Ninja\Granite\Mapping\ObjectMapper;
use Ninja\Granite\Mapping\Contracts\Mapper;
use Ninja\Granite\Mapping\Contracts\Transformer;
use Ninja\Granite\Mapping\Exceptions\MappingException;
use Ninja\Granite\Mapping\MappingProfile;
use Ninja\Granite\Mapping\Attributes\MapFrom;
use Ninja\Granite\Mapping\Attributes\MapWith;
use Ninja\Granite\Mapping\Attributes\Ignore;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use Tests\Fixtures\Enums\UserStatus;
use Tests\Fixtures\Enums\Priority;
use Tests\Helpers\TestCase;
use Tests\Unit\Mapping\Fixtures\Basic;

#[CoversClass(ObjectMapper::class)]
class BasicMappingTest extends TestCase
{
    private ObjectMapper $mapper;

    protected function setUp(): void
    {
        $profiles = [
            new Basic\UserMappingProfile(),
            new Basic\ComplexMappingProfile(),
            new Basic\ChainedTransformerProfile(),
            new Basic\CircularReferenceProfile(),
            new Basic\ResponseToSimpleUserProfile(),
            new Basic\ClassTransformerProfile()
        ];
        
        $this->mapper = new ObjectMapper(MapperConfig::create()->withProfiles($profiles));
        parent::setUp();
    }

    #[Test]
    public function it_implements_mapper_interface(): void
    {
        $this->assertInstanceOf(Mapper::class, $this->mapper);
    }

    #[Test]
    public function it_can_be_created_with_profiles(): void
    {
        $profile = $this->createMock(MappingProfile::class);
        $mapper = new ObjectMapper(MapperConfig::create()->withProfile($profile));

        $this->assertInstanceOf(ObjectMapper::class, $mapper);
    }

    #[Test]
    public function it_maps_simple_array_to_dto(): void
    {
        $source = [
            'id' => 1,
            'name' => 'John Doe',
            'email' => 'john@example.com'
        ];

        $result = $this->mapper->map($source, Basic\SimpleUserDTO::class);

        $this->assertInstanceOf(Basic\SimpleUserDTO::class, $result);
        $this->assertEquals(1, $result->id);
        $this->assertEquals('John Doe', $result->name);
        $this->assertEquals('john@example.com', $result->email);
    }

    // ====== ATTRIBUTE-BASED MAPPING TESTS ======

    /**
     * @throws GraniteException
     * @throws MappingException
     */
    #[Test]
    public function it_maps_with_map_from_attribute(): void
    {
        $source = [
            'user_id' => 42,
            'full_name' => 'Jane Smith',
            'email_address' => 'jane@example.com'
        ];

        $result = $this->mapper->map($source, Basic\MappedUserDTO::class);

        $this->assertEquals(42, $result->id);
        $this->assertEquals('Jane Smith', $result->name);
        $this->assertEquals('jane@example.com', $result->email);
    }

    #[Test]
    public function it_ignores_properties_with_ignore_attribute(): void
    {
        $source = [
            'id' => 1,
            'name' => 'John',
            'password' => 'secret123',
            'email' => 'john@example.com'
        ];

        $result = $this->mapper->map($source, Basic\IgnoredFieldsDTO::class);

        $this->assertEquals(1, $result->id);
        $this->assertEquals('John', $result->name);
        $this->assertEquals('john@example.com', $result->email);
        // Password should not be mapped (ignored)
    }

    #[Test]
    public function it_applies_transformer_attribute(): void
    {
        $source = [
            'id' => 1,
            'name' => 'john doe',
            'email' => 'john@example.com'
        ];

        $result = $this->mapper->map($source, Basic\TransformedUserDTO::class);

        $this->assertEquals('JOHN DOE', $result->name); // Transformed to uppercase
    }

    // ====== NESTED PROPERTY MAPPING TESTS ======

    #[Test]
    public function it_maps_nested_properties_with_dot_notation(): void
    {
        $source = [
            'user' => [
                'personal' => [
                    'firstName' => 'John',
                    'lastName' => 'Doe'
                ],
                'contact' => [
                    'email' => 'john@example.com',
                    'phone' => '+1234567890'
                ]
            ],
            'metadata' => [
                'createdAt' => '2024-01-01T10:00:00Z'
            ]
        ];

        $result = $this->mapper->map($source, Basic\NestedMappingDTO::class);

        $this->assertEquals('John', $result->firstName);
        $this->assertEquals('Doe', $result->lastName);
        $this->assertEquals('john@example.com', $result->email);
        $this->assertEquals('+1234567890', $result->phone);
        $this->assertEquals('2024-01-01T10:00:00Z', $result->createdAt);
    }

    #[Test]
    public function it_handles_missing_nested_properties(): void
    {
        $source = [
            'user' => [
                'personal' => [
                    'firstName' => 'John'
                    // lastName missing
                ],
                // contact missing entirely
            ]
        ];

        $result = $this->mapper->map($source, Basic\NestedMappingDTO::class);

        $this->assertEquals('John', $result->firstName);
        $this->assertNull($result->lastName);
        $this->assertNull($result->email);
        $this->assertNull($result->phone);
    }

    // ====== COMPLEX DATA TYPE MAPPING TESTS ======

    #[Test]
    public function it_maps_datetime_objects(): void
    {
        $date = new DateTimeImmutable('2024-01-01T10:00:00Z');
        $source = [
            'id' => 1,
            'name' => 'Event',
            'startDate' => $date,
            'endDate' => '2024-01-02T10:00:00Z'
        ];

        $result = $this->mapper->map($source, Basic\EventDTO::class);

        $this->assertInstanceOf(DateTimeInterface::class, $result->startDate);
        $this->assertEquals($date->format('c'), $result->startDate->format('c'));
        $this->assertInstanceOf(DateTimeInterface::class, $result->endDate);
    }

    #[Test]
    public function it_maps_enum_values(): void
    {
        $source = [
            'id' => 1,
            'name' => 'John',
            'status' => 'active',
            'priority' => 2
        ];

        $result = $this->mapper->map($source, Basic\UserWithEnumsDTO::class);

        $this->assertInstanceOf(UserStatus::class, $result->status);
        $this->assertEquals(UserStatus::ACTIVE, $result->status);
        $this->assertInstanceOf(Priority::class, $result->priority);
        $this->assertEquals(Priority::MEDIUM, $result->priority);
    }

    #[Test]
    public function it_maps_arrays_and_collections(): void
    {
        $source = [
            'id' => 1,
            'name' => 'Project X',
            'tags' => ['php', 'testing', 'mapping'],
            'settings' => [
                'public' => true,
                'maxUsers' => 10
            ]
        ];

        $result = $this->mapper->map($source, Basic\ProjectDTO::class);

        $this->assertEquals(1, $result->id);
        $this->assertEquals('Project X', $result->name);
        $this->assertIsArray($result->tags);
        $this->assertCount(3, $result->tags);
        $this->assertContains('php', $result->tags);
        $this->assertIsArray($result->settings);
        $this->assertTrue($result->settings['public']);
    }

    // ====== PROFILE-BASED MAPPING TESTS ======

    #[Test]
    public function it_applies_mapping_profile(): void
    {
        $profile = new Basic\UserMappingProfile();
        $mapper = new ObjectMapper(MapperConfig::create()->withProfile($profile));

        $source = [
            'firstName' => 'John',
            'lastName' => 'Doe',
            'birthDate' => '1990-01-01',
            'emailAddress' => 'john@example.com'
        ];

        $result = $mapper->map($source, Basic\ProfileMappedUserDTO::class);

        $this->assertEquals('John Doe', $result->fullName);
        $this->assertEquals('1990', $result->birthYear);
        $this->assertEquals('john@example.com', $result->email);
    }

    #[Test]
    public function it_combines_profile_and_attribute_mapping(): void
    {
        $profile = new Basic\UserMappingProfile();
        $mapper = new ObjectMapper(MapperConfig::create()->withProfile($profile));

        $source = [
            'firstName' => 'John',
            'lastName' => 'Doe',
            'user_type' => 'admin'
        ];

        $result = $mapper->map($source, Basic\HybridMappedUserDTO::class);

        $this->assertEquals('John Doe', $result->fullName); // From profile
        $this->assertEquals('ADMIN', $result->type); // From attribute
    }

    // ====== OBJECT MAPPING TESTS ======

    #[Test]
    public function it_maps_regular_object_to_dto(): void
    {
        $source = new \stdClass();
        $source->id = 1;
        $source->name = 'John Doe';
        $source->email = 'john@example.com';

        $result = $this->mapper->map($source, Basic\SimpleUserDTO::class);

        $this->assertSame(1, $result->id);
        $this->assertEquals('John Doe', $result->name);
        $this->assertEquals('john@example.com', $result->email);
    }

    #[Test]
    public function it_maps_dto_to_dto(): void
    {
        $source = new Basic\UserResponseDTO(
            id: 1,
            displayName: 'John Doe',
            email: 'john@example.com'
        );

        $result = $this->mapper->map($source, Basic\SimpleUserDTO::class);

        $this->assertEquals(1, $result->id);
        $this->assertEquals('John Doe', $result->name);
        $this->assertEquals('john@example.com', $result->email);
    }

    #[Test]
    public function it_maps_to_existing_object(): void
    {
        $source = [
            'id' => 1,
            'name' => 'John Doe',
            'email' => 'john@example.com'
        ];

        $destination = new Basic\MutableUserDTO();
        $result = $this->mapper->mapTo($source, $destination);

        $this->assertSame($destination, $result);
        $this->assertEquals(1, $result->id);
        $this->assertEquals('John Doe', $result->name);
        $this->assertEquals('john@example.com', $result->email);
    }

    // ====== TRANSFORMER TESTS ======

    #[Test]
    public function it_applies_callable_transformer(): void
    {
        $source = [
            'name' => 'john doe',
            'age' => 30
        ];

        $result = $this->mapper->map($source, Basic\CallableTransformerDTO::class);

        $this->assertEquals('JOHN DOE', $result->name);
        $this->assertEquals('30 years old', $result->displayAge);
    }

    #[Test]
    public function it_applies_class_transformer(): void
    {
        $source = [
            'value' => 'test'
        ];

        $result = $this->mapper->map($source, Basic\ClassTransformerDTO::class);

        $this->assertEquals('TEST_TRANSFORMED', $result->value);
    }

    #[Test]
    public function it_handles_transformer_exceptions(): void
    {
        $source = [
            'value' => 'trigger_error'
        ];

        $this->expectException(MappingException::class);
        $this->mapper->map($source, Basic\FailingTransformerDTO::class);
    }

    // ====== EDGE CASE TESTS ======

    #[Test]
    public function it_handles_null_values(): void
    {
        $source = [
            'id' => 1,
            'name' => null,
            'email' => null
        ];

        $result = $this->mapper->map($source, Basic\NullableUserDTO::class);

        $this->assertEquals(1, $result->id);
        $this->assertNull($result->name);
        $this->assertNull($result->email);
    }

    #[Test]
    public function it_maps_primitive_types(): void
    {
        $values = [42, 'string', true, 3.14];

        foreach ($values as $value) {
            $result = $this->mapper->map(['value' => $value], Basic\PrimitiveTypeDTO::class);
            $this->assertEquals($value, $result->value);
        }
    }

    #[Test]
    public function it_handles_readonly_properties(): void
    {
        $source = [
            'id' => 1,
            'name' => 'John Doe',
            'email' => 'john@example.com'
        ];

        $result = $this->mapper->map($source, Basic\ReadonlyUserDTO::class);

        $this->assertEquals(1, $result->id);
        $this->assertEquals('John Doe', $result->name);
        $this->assertEquals('john@example.com', $result->email);
    }

    #[Test]
    public function it_handles_inheritance(): void
    {
        $source = [
            'id' => 1,
            'name' => 'Admin User',
            'email' => 'admin@example.com',
            'permissions' => ['read', 'write', 'delete']
        ];

        $result = $this->mapper->map($source, Basic\AdminUserDTO::class);

        $this->assertEquals(1, $result->id);
        $this->assertEquals('Admin User', $result->name);
        $this->assertEquals('admin@example.com', $result->email);
        $this->assertEquals(['read', 'write', 'delete'], $result->permissions);
    }

    // ====== PROFILE TESTS ======

    #[Test]
    public function it_applies_chained_transformers(): void
    {
        $profile = new Basic\ChainedTransformerProfile();
        $mapper = new ObjectMapper(MapperConfig::create()->withProfile($profile));

        $source = ['text' => 'hello world'];
        $result = $mapper->map($source, Basic\ChainedTransformerDTO::class);

        $this->assertEquals('HELLO WORLD!', $result->text);
    }

    #[Test]
    public function it_handles_complex_mapping_configurations(): void
    {
        $profile = new Basic\ComplexMappingProfile();
        $mapper = new ObjectMapper(MapperConfig::create()->withProfile($profile));

        $source = [
            'user' => [
                'id' => 1,
                'firstName' => 'John',
                'lastName' => 'Doe'
            ],
            'contact' => [
                'email' => 'john@example.com',
                'phone' => '123-456-7890'
            ]
        ];

        $result = $mapper->map($source, Basic\ComplexMappedDTO::class);

        $this->assertEquals(1, $result->userId);
        $this->assertEquals('John Doe', $result->fullName);
        $this->assertEquals([
            'email' => 'john@example.com',
            'phone' => '123-456-7890'
        ], $result->contactInfo);
    }

    #[Test]
    public function it_handles_circular_references(): void
    {
        $profile = new Basic\CircularReferenceProfile();
        $mapper = new ObjectMapper(MapperConfig::create()->withProfile($profile));

        $source = [
            'id' => 1,
            'name' => 'Parent',
            'parent' => null
        ];

        $result = $mapper->map($source, Basic\CircularReferenceDTO::class);

        $this->assertEquals(1, $result->id);
        $this->assertEquals('Parent', $result->name);
        $this->assertNull($result->parent);

        // Now with a parent
        $source['parent'] = [
            'id' => 2,
            'name' => 'Child',
            'parent' => null
        ];

        $result = $mapper->map($source, Basic\CircularReferenceDTO::class);

        $this->assertEquals(1, $result->id);
        $this->assertEquals('Parent', $result->name);
        $this->assertInstanceOf(Basic\CircularReferenceDTO::class, $result->parent);
        $this->assertEquals(2, $result->parent->id);
        $this->assertEquals('Child', $result->parent->name);
    }
}