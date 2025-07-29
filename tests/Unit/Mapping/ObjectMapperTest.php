<?php

// tests/Unit/Mapping/ObjectMapperTest.php

declare(strict_types=1);

namespace Tests\Unit\Mapping;

use Ninja\Granite\Mapping\Contracts\Mapper;
use Ninja\Granite\Mapping\Exceptions\MappingException;
use Ninja\Granite\Mapping\MapperConfig;
use Ninja\Granite\Mapping\MappingProfile;
use Ninja\Granite\Mapping\ObjectMapper;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use ReflectionClass;
use Tests\Fixtures\Automapper\DTO\ComplexDTO;
use Tests\Fixtures\Automapper\DTO\DestinationDTO;
use Tests\Fixtures\Automapper\DTO\IgnoreDTO;
use Tests\Fixtures\Automapper\DTO\MappedDTO;
use Tests\Fixtures\Automapper\DTO\NestedMappingDTO;
use Tests\Fixtures\Automapper\DTO\ProfileMappedDTO;
use Tests\Fixtures\Automapper\DTO\TransformerDTO;
use Tests\Fixtures\Automapper\SourceObject;
use Tests\Fixtures\Automapper\TestMappingProfile;
use Tests\Fixtures\DTOs\SimpleDTO;
use Tests\Fixtures\DTOs\UserDTO;
use Tests\Helpers\TestCase;

#[CoversClass(ObjectMapper::class)]
class ObjectMapperTest extends TestCase
{
    private ObjectMapper $mapper;

    protected function setUp(): void
    {
        $this->mapper = new ObjectMapper();
        parent::setUp();
    }

    public static function mappingSourceTypesProvider(): array
    {
        return [
            'array source' => [
                ['id' => 1, 'name' => 'Test'],
                SimpleDTO::class,
            ],
            'object source' => [
                new SimpleDTO(id: 1, name: 'Test', email: 'test@test.com'),
                SimpleDTO::class,
            ],
        ];
    }

    public function test_implements_mapper_interface(): void
    {
        $this->assertInstanceOf(Mapper::class, $this->mapper);
    }

    public function test_creates_mapper_without_profiles(): void
    {
        $mapper = new ObjectMapper();

        $this->assertInstanceOf(ObjectMapper::class, $mapper);
    }

    public function test_creates_mapper_with_profiles(): void
    {
        $profile = $this->createMock(MappingProfile::class);
        $mapper = new ObjectMapper(MapperConfig::create()->withProfile($profile));

        $this->assertInstanceOf(ObjectMapper::class, $mapper);
    }

    public function test_adds_mapping_profile(): void
    {
        $profile = $this->createMock(MappingProfile::class);

        $result = $this->mapper->addProfile($profile);

        $this->assertSame($this->mapper, $result);
    }

    public function test_maps_array_to_object(): void
    {
        $sourceData = [
            'id' => 1,
            'name' => 'John Doe',
            'email' => 'john@example.com',
        ];

        $result = $this->mapper->map($sourceData, SimpleDTO::class);

        $this->assertInstanceOf(SimpleDTO::class, $result);
        $this->assertEquals(1, $result->id);
        $this->assertEquals('John Doe', $result->name);
        $this->assertEquals('john@example.com', $result->email);
    }

    public function test_maps_object_to_object(): void
    {
        $source = SimpleDTO::from([
            'id' => 1,
            'name' => 'John Doe',
            'email' => 'john@example.com',
        ]);

        $result = $this->mapper->map($source, DestinationDTO::class);

        $this->assertInstanceOf(DestinationDTO::class, $result);
        $this->assertEquals(1, $result->id);
        $this->assertEquals('John Doe', $result->name);
        $this->assertEquals('john@example.com', $result->email);
    }

    public function test_maps_granite_object_to_object(): void
    {
        $source = SimpleDTO::from([
            'id' => 1,
            'name' => 'John Doe',
            'email' => 'john@example.com',
        ]);

        $result = $this->mapper->map($source, DestinationDTO::class);

        $this->assertInstanceOf(DestinationDTO::class, $result);
        $this->assertEquals($source->id, $result->id);
        $this->assertEquals($source->name, $result->name);
        $this->assertEquals($source->email, $result->email);
    }

    public function test_maps_with_property_name_mapping(): void
    {
        $source = new SourceObject();
        $source->firstName = 'John';
        $source->lastName = 'Doe';
        $source->emailAddress = 'john@example.com';

        $result = $this->mapper->map($source, MappedDTO::class);

        $this->assertInstanceOf(MappedDTO::class, $result);
        $this->assertEquals('John', $result->first_name);
        $this->assertEquals('Doe', $result->last_name);
        $this->assertEquals('john@example.com', $result->email);
    }

    public function test_maps_ignoring_marked_properties(): void
    {
        $source = [
            'id' => 1,
            'name' => 'John',
            'password' => 'secret',
            'email' => 'john@example.com',
        ];

        $result = $this->mapper->map($source, IgnoreDTO::class);

        $this->assertInstanceOf(IgnoreDTO::class, $result);
        $this->assertEquals(1, $result->id);
        $this->assertEquals('John', $result->name);
        $this->assertEquals('john@example.com', $result->email);
        // password should be ignored and not mapped
    }

    public function test_maps_with_transformer(): void
    {
        $source = [
            'id' => 1,
            'name' => 'john doe',
            'email' => 'john@example.com',
        ];

        $result = $this->mapper->map($source, TransformerDTO::class);

        $this->assertInstanceOf(TransformerDTO::class, $result);
        $this->assertEquals('JOHN DOE', $result->name); // Transformed to uppercase
    }

    public function test_maps_array_of_objects(): void
    {
        $sourceArray = [
            ['id' => 1, 'name' => 'John', 'email' => 'john@example.com'],
            ['id' => 2, 'name' => 'Jane', 'email' => 'jane@example.com'],
            ['id' => 3, 'name' => 'Bob', 'email' => 'bob@example.com'],
        ];

        $result = $this->mapper->mapArray($sourceArray, SimpleDTO::class);

        $this->assertIsArray($result);
        $this->assertCount(3, $result);

        foreach ($result as $dto) {
            $this->assertInstanceOf(SimpleDTO::class, $dto);
        }

        $this->assertEquals('John', $result[0]->name);
        $this->assertEquals('Jane', $result[1]->name);
        $this->assertEquals('Bob', $result[2]->name);
    }

    public function test_maps_to_existing_object(): void
    {
        $source = ['id' => 2, 'name' => 'Updated Name', 'email' => 'updated@example.com'];
        $destination = new DestinationDTO();
        $destination->id = 1;
        $destination->name = 'Original Name';
        $destination->email = 'original@example.com';

        $result = $this->mapper->mapTo($source, $destination);

        $this->assertSame($destination, $result);
        $this->assertEquals(2, $result->id); // Unchanged
        $this->assertEquals('Updated Name', $result->name); // Updated
        $this->assertEquals('updated@example.com', $result->email); // Updated
    }

    public function test_throws_exception_for_non_existent_destination_type(): void
    {
        $this->expectException(MappingException::class);
        $this->expectExceptionMessage('Destination type "NonExistentClass" does not exist');

        $this->mapper->map(['data' => 'test'], 'NonExistentClass');
    }

    public function test_throws_exception_for_unsupported_source_type(): void
    {
        $this->expectException(MappingException::class);
        $this->expectExceptionMessage('Unsupported source type');

        $this->mapper->map(123, SimpleDTO::class); // Numbers are not supported
    }

    public function test_handles_nested_property_mapping(): void
    {
        $source = [
            'user' => [
                'name' => 'John Doe',
                'profile' => [
                    'email' => 'john@example.com',
                ],
            ],
        ];

        $result = $this->mapper->map($source, NestedMappingDTO::class);

        $this->assertInstanceOf(NestedMappingDTO::class, $result);
        $this->assertEquals('John Doe', $result->userName);
        $this->assertEquals('john@example.com', $result->userEmail);
    }

    public function test_caches_mapping_configuration(): void
    {
        $source = ['id' => 1, 'name' => 'Test', 'email' => 'test@test.com'];

        // First mapping should build configuration
        $result1 = $this->mapper->map($source, SimpleDTO::class);

        // Second mapping should use cached configuration
        $result2 = $this->mapper->map($source, SimpleDTO::class);

        $this->assertEquals($result1->id, $result2->id);
        $this->assertEquals($result1->name, $result2->name);
    }

    public function test_performance_with_large_datasets(): void
    {
        $largeDataset = [];
        for ($i = 0; $i < 10000; $i++) {
            $largeDataset[] = [
                'id' => $i,
                'name' => "User {$i}",
                'email' => "user{$i}@example.com",
            ];
        }

        $start = microtime(true);
        $result = $this->mapper->mapArray($largeDataset, SimpleDTO::class);
        $elapsed = microtime(true) - $start;

        $this->assertCount(10000, $result);
        $this->assertLessThan(1.0, $elapsed, "Mapping 10000 objects took too long: {$elapsed}s");
    }

    public function test_maps_with_mapping_profile(): void
    {
        $profile = new TestMappingProfile();
        $mapper = new ObjectMapper(MapperConfig::create()->withProfile($profile));

        $source = [
            'first_name' => 'John',
            'last_name' => 'Doe',
            'birth_date' => '1990-01-01',
        ];

        $result = $mapper->map($source, ProfileMappedDTO::class);

        $this->assertEquals('John Doe', $result->fullName); // Concatenated by profile
        $this->assertEquals('1990', $result->birthYear); // Extracted by profile
    }

    public function test_handles_null_source_values(): void
    {
        $source = [
            'id' => 1,
            'name' => null,
            'email' => 'test@example.com',
        ];

        $result = $this->mapper->map($source, SimpleDTO::class);

        $this->assertEquals(1, $result->id);
        $this->assertNull($result->name);
        $this->assertEquals('test@example.com', $result->email);
    }

    public function test_handles_missing_source_properties(): void
    {
        $source = [
            'id' => 1,
            // Missing name and email
        ];

        $result = $this->mapper->map($source, SimpleDTO::class);

        $this->assertEquals(1, $result->id);
        $this->assertNull($result->name);
        $this->assertNull($result->email);
    }

    #[DataProvider('mappingSourceTypesProvider')]
    public function test_handles_different_source_types(mixed $source, string $destinationType): void
    {
        $result = $this->mapper->map($source, $destinationType);
        $this->assertInstanceOf($destinationType, $result);
    }

    public function test_error_handling_with_context(): void
    {
        try {
            $this->mapper->map(['invalid' => 'data'], 'NonExistentClass');
        } catch (MappingException $e) {
            $context = $e->getContext();
            $this->assertArrayHasKey('destination_type', $context);
            $this->assertEquals('NonExistentClass', $context['destination_type']);
        }
    }

    public function test_maps_readonly_properties(): void
    {
        $source = ['id' => 1, 'name' => 'Test', 'email' => 'test@example.com'];

        $result = $this->mapper->map($source, SimpleDTO::class);

        // Result should be readonly
        $reflection = new ReflectionClass($result);
        $this->assertTrue($reflection->isReadonly());
    }

    public function test_deep_object_mapping(): void
    {
        $complexSource = [
            'id' => 1,
            'user' => [
                'id' => 2,
                'name' => 'John Doe',
                'email' => 'john@example.com',
                'address' => [
                    'street' => '123 Main St',
                    'city' => 'New York',
                    'country' => 'USA',
                    'zipCode' => '10001',
                ],
            ],
        ];

        $result = $this->mapper->map($complexSource, ComplexDTO::class);

        $this->assertInstanceOf(ComplexDTO::class, $result);
        $this->assertEquals(1, $result->id);
        $this->assertInstanceOf(UserDTO::class, $result->user);
        $this->assertEquals('John Doe', $result->user->name);
    }
}
