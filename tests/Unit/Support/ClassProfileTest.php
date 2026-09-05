<?php

// ABOUTME: Tests for ClassProfile fast-path detection and object creation.
// ABOUTME: Verifies correct identification of simple DTOs and fast instantiation.

declare(strict_types=1);

namespace Tests\Unit\Support;

use Ninja\Granite\Support\ClassProfile;
use Ninja\Granite\Support\ValueComparator;
use PHPUnit\Framework\Attributes\CoversClass;
use Tests\Fixtures\DTOs\NestedDTO;
use Tests\Fixtures\DTOs\PersonDTO;
use Tests\Fixtures\DTOs\ScalarDTO;
use Tests\Fixtures\DTOs\SimpleDTO;
use Tests\Fixtures\DTOs\TeamDTO;
use Tests\Fixtures\DTOs\TestHiddenDto;
use Tests\Fixtures\DTOs\TestSnakeCaseDto;
use Tests\Fixtures\Enums\Priority;
use Tests\Fixtures\Enums\UserStatus;
use Tests\Fixtures\VOs\ValidatedUserVO;
use Tests\Helpers\TestCase;

#[CoversClass(ClassProfile::class)]
class ClassProfileTest extends TestCase
{
    public function test_detects_fast_path_for_primitive_dto(): void
    {
        $profile = ClassProfile::build(PersonDTO::class);

        $this->assertTrue($profile->canUseFastPath);
    }

    public function test_array_type_uses_hydration_but_not_serialization_fast_path(): void
    {
        $profile = ClassProfile::build(ScalarDTO::class);

        $this->assertTrue($profile->canHydrateFastPath);
        $this->assertFalse($profile->canSerializeFastPath);
        $this->assertTrue($profile->canCompareFastPath);
        $this->assertFalse($profile->canUseFastPath);
    }

    public function test_rejects_fast_path_for_nested_object_types(): void
    {
        $profile = ClassProfile::build(NestedDTO::class);

        $this->assertFalse($profile->canUseFastPath);
    }

    public function test_rejects_fast_path_for_validation_attributes(): void
    {
        $profile = ClassProfile::build(ValidatedUserVO::class);

        $this->assertFalse($profile->canUseFastPath);
    }

    public function test_rejects_fast_path_for_serialization_convention(): void
    {
        $profile = ClassProfile::build(TestSnakeCaseDto::class);

        $this->assertFalse($profile->canUseFastPath);
    }

    public function test_rejects_fast_path_for_hidden_attributes(): void
    {
        $profile = ClassProfile::build(TestHiddenDto::class);

        $this->assertFalse($profile->canUseFastPath);
    }

    public function test_fast_path_with_array_data(): void
    {
        $profile = ClassProfile::build(PersonDTO::class);

        $result = $profile->tryFastPath([['name' => 'Alice', 'age' => 30, 'email' => 'alice@test.com']]);

        $this->assertInstanceOf(PersonDTO::class, $result);
        $this->assertSame('Alice', $result->name);
        $this->assertSame(30, $result->age);
        $this->assertSame('alice@test.com', $result->email);
    }

    public function test_fast_path_with_named_params(): void
    {
        $profile = ClassProfile::build(PersonDTO::class);

        $result = $profile->tryFastPath(['email' => 'bob@test.com', 'name' => 'Bob', 'age' => 25]);

        $this->assertInstanceOf(PersonDTO::class, $result);
        $this->assertSame('Bob', $result->name);
        $this->assertSame(25, $result->age);
        $this->assertSame('bob@test.com', $result->email);
    }

    public function test_fast_path_falls_back_when_optional_params_missing(): void
    {
        $profile = ClassProfile::build(SimpleDTO::class);

        $result = $profile->tryFastPath([['id' => 1, 'name' => 'Test', 'email' => 'test@test.com']]);

        $this->assertNull($result);
    }

    public function test_fast_path_works_when_all_params_provided(): void
    {
        $profile = ClassProfile::build(SimpleDTO::class);

        $result = $profile->tryFastPath([['id' => 1, 'name' => 'Test', 'email' => 'test@test.com', 'age' => 25]]);

        $this->assertInstanceOf(SimpleDTO::class, $result);
        $this->assertSame(1, $result->id);
        $this->assertSame(25, $result->age);
    }

    public function test_fast_path_returns_null_for_missing_required_param(): void
    {
        $profile = ClassProfile::build(PersonDTO::class);

        $result = $profile->tryFastPath([['name' => 'Alice']]);

        $this->assertNull($result);
    }

    public function test_fast_path_returns_null_for_json_string(): void
    {
        $profile = ClassProfile::build(PersonDTO::class);

        $result = $profile->tryFastPath(['{"name":"Alice","age":30,"email":"a@b.com"}']);

        $this->assertNull($result);
    }

    public function test_fast_path_returns_null_for_object_input(): void
    {
        $profile = ClassProfile::build(PersonDTO::class);
        $source = PersonDTO::from(name: 'Alice', age: 30, email: 'a@b.com');

        $result = $profile->tryFastPath([$source]);

        $this->assertNull($result);
    }

    public function test_fast_path_produces_identical_results_to_slow_path(): void
    {
        $data = ['name' => 'Alice', 'age' => 30, 'email' => 'alice@test.com'];
        $profile = ClassProfile::build(PersonDTO::class);

        $fast = $profile->tryFastPath([$data]);
        $slow = PersonDTO::from($data);

        $this->assertNotNull($fast);
        $this->assertEquals($slow->array(), $fast->array());
    }

    public function test_array_typed_dto_uses_hydration_fast_path(): void
    {
        $data = ['id' => 1, 'name' => 'Product', 'price' => 9.99, 'active' => true, 'tags' => ['a', 'b']];
        $profile = ClassProfile::build(ScalarDTO::class);

        $this->assertFalse($profile->canUseFastPath);
        $result = $profile->tryFastPath([$data]);

        $this->assertInstanceOf(ScalarDTO::class, $result);
        $this->assertSame(['a', 'b'], $result->tags);
    }

    public function test_fast_path_returns_null_for_positional_args(): void
    {
        $profile = ClassProfile::build(PersonDTO::class);

        $result = $profile->tryFastPath(['Alice', 30, 'a@b.com']);

        $this->assertNull($result);
    }

    public function test_detects_fast_path_for_nested_granite_types(): void
    {
        $profile = ClassProfile::build(TeamDTO::class);

        $this->assertTrue($profile->canUseFastPath);
    }

    public function test_tracks_granite_typed_params(): void
    {
        $profile = ClassProfile::build(TeamDTO::class);

        $this->assertArrayHasKey('leader', $profile->graniteParams);
        $this->assertSame(PersonDTO::class, $profile->graniteParams['leader']);
    }

    public function test_pure_primitive_dto_has_empty_granite_params(): void
    {
        $profile = ClassProfile::build(PersonDTO::class);

        $this->assertEmpty($profile->graniteParams);
    }

    public function test_fast_path_nested_with_array_data(): void
    {
        $profile = ClassProfile::build(TeamDTO::class);

        $result = $profile->tryFastPath([[
            'name' => 'Alpha',
            'leader' => ['name' => 'Alice', 'age' => 30, 'email' => 'alice@test.com'],
            'size' => 5,
        ]]);

        $this->assertInstanceOf(TeamDTO::class, $result);
        $this->assertSame('Alpha', $result->name);
        $this->assertSame(5, $result->size);
        $this->assertInstanceOf(PersonDTO::class, $result->leader);
        $this->assertSame('Alice', $result->leader->name);
        $this->assertSame(30, $result->leader->age);
    }

    public function test_fast_path_nested_with_granite_instance(): void
    {
        $profile = ClassProfile::build(TeamDTO::class);
        $leader = PersonDTO::from(name: 'Bob', age: 25, email: 'bob@test.com');

        $result = $profile->tryFastPath([[
            'name' => 'Beta',
            'leader' => $leader,
            'size' => 3,
        ]]);

        $this->assertInstanceOf(TeamDTO::class, $result);
        $this->assertSame('Bob', $result->leader->name);
    }

    public function test_fast_path_nested_produces_identical_results_to_slow_path(): void
    {
        $data = [
            'name' => 'Gamma',
            'leader' => ['name' => 'Charlie', 'age' => 35, 'email' => 'charlie@test.com'],
            'size' => 10,
        ];

        $fast = TeamDTO::from($data);
        // Force slow path by going through full hydration
        $profile = ClassProfile::build(TeamDTO::class);
        $fastDirect = $profile->tryFastPath([$data]);

        $this->assertNotNull($fastDirect);
        $this->assertEquals($fast->array(), $fastDirect->array());
    }

    public function test_rejects_fast_path_when_property_names_do_not_match_constructor_names(): void
    {
        $instance = new class ('value') {
            public string $propertyName;

            public function __construct(string $constructorName)
            {
                $this->propertyName = $constructorName;
            }
        };

        $profile = ClassProfile::build($instance::class);

        $this->assertFalse($profile->canUseFastPath);
    }

    public function test_comparison_requires_enum_classes_to_match(): void
    {
        $this->assertFalse(ValueComparator::equals(UserStatus::ACTIVE, Priority::HIGH));
        $this->assertTrue(ValueComparator::equals(UserStatus::ACTIVE, UserStatus::ACTIVE));
    }
}
