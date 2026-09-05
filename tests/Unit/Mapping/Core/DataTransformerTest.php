<?php

namespace Tests\Unit\Mapping\Core;

use Ninja\Granite\Mapping\Contracts\Mapper;
use Ninja\Granite\Mapping\Core\DataTransformer;
use Ninja\Granite\Mapping\Exceptions\MappingException;
use Ninja\Granite\Transformers\CollectionTransformer;
use stdClass;
use Tests\Helpers\TestCase;

class DataTransformerTest extends TestCase
{
    private DataTransformer $transformer;

    protected function setUp(): void
    {
        parent::setUp();
        $this->transformer = new DataTransformer();
    }

    public static function titleCase(string $value): string
    {
        return ucwords($value);
    }

    public function test_transform_basic_mapping(): void
    {
        $sourceData = ['name' => 'John', 'age' => 30];
        $mappingConfig = [
            'fullName' => ['source' => 'name'],
            'years' => ['source' => 'age'],
        ];

        $result = $this->transformer->transform($sourceData, $mappingConfig);

        $this->assertEquals(['fullName' => 'John', 'years' => 30], $result);
    }

    public function test_transform_ignores_ignored_properties(): void
    {
        $sourceData = ['name' => 'John', 'password' => 'secret'];
        $mappingConfig = [
            'name' => ['source' => 'name'],
            'password' => ['source' => 'password', 'ignore' => true],
        ];

        $result = $this->transformer->transform($sourceData, $mappingConfig);

        $this->assertEquals(['name' => 'John'], $result);
        $this->assertArrayNotHasKey('password', $result);
    }

    public function test_transform_applies_transformer(): void
    {
        $sourceData = ['name' => 'john'];
        $mappingConfig = [
            'name' => [
                'source' => 'name',
                'transformer' => fn($value) => strtoupper($value),
            ],
        ];

        $result = $this->transformer->transform($sourceData, $mappingConfig);

        $this->assertEquals(['name' => 'JOHN'], $result);
    }

    public function test_transform_applies_condition(): void
    {
        $sourceData = ['name' => 'John', 'age' => 25, 'status' => 'active'];
        $mappingConfig = [
            'name' => [
                'source' => 'name',
                'condition' => fn($data) => 'active' === $data['status'],
            ],
            'age' => [
                'source' => 'age',
                'condition' => fn($data) => 'inactive' === $data['status'],
            ],
        ];

        $result = $this->transformer->transform($sourceData, $mappingConfig);

        $this->assertEquals(['name' => 'John'], $result);
        $this->assertArrayNotHasKey('age', $result);
    }

    public function test_transform_handles_nested_source_values(): void
    {
        $sourceData = [
            'user' => [
                'profile' => [
                    'firstName' => 'John',
                    'lastName' => 'Doe',
                ],
            ],
        ];
        $mappingConfig = [
            'firstName' => ['source' => 'user.profile.firstName'],
            'lastName' => ['source' => 'user.profile.lastName'],
        ];

        $result = $this->transformer->transform($sourceData, $mappingConfig);

        $this->assertEquals(['firstName' => 'John', 'lastName' => 'Doe'], $result);
    }

    public function test_transform_handles_missing_nested_values(): void
    {
        $sourceData = ['user' => ['name' => 'John']];
        $mappingConfig = [
            'email' => ['source' => 'user.profile.email'],
        ];

        $result = $this->transformer->transform($sourceData, $mappingConfig);

        $this->assertEquals(['email' => null], $result);
    }

    public function test_transform_applies_default_values(): void
    {
        $sourceData = ['name' => 'John'];
        $mappingConfig = [
            'name' => ['source' => 'name'],
            'status' => ['source' => 'status', 'default' => 'active', 'hasDefault' => true],
        ];

        $result = $this->transformer->transform($sourceData, $mappingConfig);

        $this->assertEquals(['name' => 'John', 'status' => 'active'], $result);
    }

    public function test_transform_applies_default_only_when_value_is_null(): void
    {
        $sourceData = ['name' => 'John', 'status' => 'inactive'];
        $mappingConfig = [
            'name' => ['source' => 'name'],
            'status' => ['source' => 'status', 'default' => 'active', 'hasDefault' => true],
        ];

        $result = $this->transformer->transform($sourceData, $mappingConfig);

        $this->assertEquals(['name' => 'John', 'status' => 'inactive'], $result);
    }

    public function test_transform_handles_array_callable_transformer(): void
    {
        $sourceData = ['name' => 'john doe'];
        $mappingConfig = [
            'name' => [
                'source' => 'name',
                'transformer' => [self::class, 'titleCase'],
            ],
        ];

        $result = $this->transformer->transform($sourceData, $mappingConfig);

        $this->assertEquals(['name' => 'John Doe'], $result);
    }

    public function test_transform_handles_invokable_object_transformer(): void
    {
        $result = $this->transformer->transform(
            ['name' => 'john'],
            [
                'name' => [
                    'source' => 'name',
                    'transformer' => new InvokableTransformer(),
                ],
            ],
        );

        $this->assertEquals(['name' => 'JOHN'], $result);
    }

    public function test_transform_handles_single_argument_internal_callable(): void
    {
        $result = $this->transformer->transform(
            ['name' => 'john'],
            [
                'name' => [
                    'source' => 'name',
                    'transformer' => 'strtoupper',
                ],
            ],
        );

        $this->assertEquals(['name' => 'JOHN'], $result);
    }

    public function test_transform_passes_source_data_to_transformer(): void
    {
        $sourceData = ['firstName' => 'John', 'lastName' => 'Doe'];
        $mappingConfig = [
            'fullName' => [
                'source' => 'firstName',
                'transformer' => fn($value, $data) => $value . ' ' . $data['lastName'],
            ],
        ];

        $result = $this->transformer->transform($sourceData, $mappingConfig);

        $this->assertEquals(['fullName' => 'John Doe'], $result);
    }

    public function test_transform_uses_default_when_condition_is_false(): void
    {
        $result = $this->transformer->transform(
            ['enabled' => false, 'status' => 'source'],
            [
                'status' => [
                    'source' => 'status',
                    'condition' => fn(array $data): bool => $data['enabled'],
                    'default' => 'fallback',
                    'hasDefault' => true,
                ],
                'optional' => [
                    'source' => 'status',
                    'condition' => fn(array $data): bool => $data['enabled'],
                ],
            ],
        );

        $this->assertSame(['status' => 'fallback'], $result);
    }

    public function test_transform_rejects_invalid_transformer(): void
    {
        $this->expectException(MappingException::class);
        $this->expectExceptionMessage('Failed to transform property "name"');

        $this->transformer->transform(
            ['name' => 'John'],
            [
                'name' => [
                    'source' => 'name',
                    'transformer' => new stdClass(),
                ],
            ],
        );
    }

    public function test_transform_injects_mapper_into_collection_transformer(): void
    {
        $mapper = $this->createMock(Mapper::class);
        $mapper->method('map')->willReturn((object) ['name' => 'John']);
        $transformer = new DataTransformer($mapper);
        $collectionTransformer = new CollectionTransformer('stdClass');

        $result = $transformer->transform(
            ['members' => [['name' => 'John']]],
            [
                'members' => [
                    'source' => 'members',
                    'transformer' => $collectionTransformer,
                ],
            ],
        );

        $this->assertCount(1, $result['members']);
        $this->assertSame('John', $result['members'][0]->name);
    }

    public function test_transform_skips_invalid_config(): void
    {
        $sourceData = ['name' => 'John', 'age' => 30];
        $mappingConfig = [
            'name' => ['source' => 'name'],
            'invalid1' => 'not_an_array',
            'invalid2' => ['source' => 123], // non-string source
            'age' => ['source' => 'age'],
        ];

        $result = $this->transformer->transform($sourceData, $mappingConfig);

        $this->assertEquals(['name' => 'John', 'age' => 30], $result);
    }

    public function test_transform_skips_config_with_non_string_keys(): void
    {
        $result = $this->transformer->transform(
            ['name' => 'John'],
            [
                'invalid' => [0 => 'unexpected', 'source' => 'name'],
                'name' => ['source' => 'name'],
            ],
        );

        $this->assertSame(['name' => 'John'], $result);
    }

    public function test_transform_handles_missing_source_keys(): void
    {
        $sourceData = ['name' => 'John'];
        $mappingConfig = [
            'name' => ['source' => 'name'],
            'age' => ['source' => 'age'], // missing in source
        ];

        $result = $this->transformer->transform($sourceData, $mappingConfig);

        $this->assertEquals(['name' => 'John', 'age' => null], $result);
    }

    public function test_transform_with_complex_scenario(): void
    {
        $sourceData = [
            'user' => [
                'personal' => ['firstName' => 'john', 'lastName' => 'doe'],
                'contact' => ['email' => 'john@example.com'],
            ],
            'settings' => ['theme' => 'dark'],
            'status' => 'active',
        ];

        $mappingConfig = [
            'fullName' => [
                'source' => 'user.personal.firstName',
                'transformer' => fn($value, $data) => ucwords($value . ' ' . $data['user']['personal']['lastName']),
            ],
            'email' => ['source' => 'user.contact.email'],
            'theme' => [
                'source' => 'settings.theme',
                'condition' => fn($data) => 'active' === $data['status'],
            ],
            'isActive' => [
                'source' => 'status',
                'transformer' => fn($value) => 'active' === $value,
            ],
            'defaultRole' => [
                'source' => 'role',
                'default' => 'user',
                'hasDefault' => true,
            ],
        ];

        $result = $this->transformer->transform($sourceData, $mappingConfig);

        $expected = [
            'fullName' => 'John Doe',
            'email' => 'john@example.com',
            'theme' => 'dark',
            'isActive' => true,
            'defaultRole' => 'user',
        ];

        $this->assertEquals($expected, $result);
    }
}

final class InvokableTransformer
{
    public function __invoke(string $value): string
    {
        return strtoupper($value);
    }
}
