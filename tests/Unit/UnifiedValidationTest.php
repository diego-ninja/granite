<?php

declare(strict_types=1);

namespace Tests\Unit;

use Ninja\Granite\Exceptions\ValidationException;
use Ninja\Granite\Granite;
use Ninja\Granite\Validation\Attributes\Email;
use Ninja\Granite\Validation\Attributes\Min;
use Ninja\Granite\Validation\Attributes\Required;
use PHPUnit\Framework\TestCase;

// Test fixtures
final readonly class ValidatedTestUser extends Granite
{
    public function __construct(
        #[Required]
        #[Min(2)]
        public string $name,
        #[Required]
        #[Email]
        public string $email,
        public ?int $age = null,
    ) {}
}

final readonly class RegularTestUser extends Granite
{
    public function __construct(
        public string $name,
        public string $email,
    ) {}
}

final readonly class MethodValidatedTestUser extends Granite
{
    public function __construct(
        public string $name,
        public string $email,
        public ?int $age = null,
    ) {}

    protected static function rules(): array
    {
        return [
            'name' => [new \Ninja\Granite\Validation\Rules\Required(), new \Ninja\Granite\Validation\Rules\Min(2)],
            'email' => [new \Ninja\Granite\Validation\Rules\Required(), new \Ninja\Granite\Validation\Rules\Email()],
            'age' => [new \Ninja\Granite\Validation\Rules\Min(18)],
        ];
    }
}

final readonly class MinAgeValidatedTestUser extends Granite
{
    public function __construct(
        public string $name,
        public string $email,
        public ?int $age = null,
    ) {}

    protected static function rules(): array
    {
        return [
            'name' => [new \Ninja\Granite\Validation\Rules\Required()],
            'age' => [new \Ninja\Granite\Validation\Rules\Min(18)],
        ];
    }
}

/**
 * Test unified validation system in Granite objects.
 */
class UnifiedValidationTest extends TestCase
{
    public function test_granite_object_with_validation_rules_validates_automatically(): void
    {
        // Valid data should work
        $user = ValidatedTestUser::from([
            'name' => 'John Doe',
            'email' => 'john@example.com',
            'age' => 30,
        ]);

        $this->assertEquals('John Doe', $user->name);
        $this->assertEquals('john@example.com', $user->email);
        $this->assertEquals(30, $user->age);
    }

    public function test_granite_object_with_validation_rules_throws_exception_for_invalid_data(): void
    {
        $this->expectException(ValidationException::class);

        ValidatedTestUser::from([
            'name' => '', // Invalid: required and min 2
            'email' => 'invalid-email', // Invalid: not email format
        ]);
    }

    public function test_granite_object_without_validation_rules_does_not_validate(): void
    {
        // Should work even with "invalid" data because no validation rules
        $user = RegularTestUser::from([
            'name' => '',
            'email' => 'not-an-email',
        ]);

        $this->assertEquals('', $user->name);
        $this->assertEquals('not-an-email', $user->email);
    }

    public function test_granite_object_with_method_based_rules(): void
    {
        // Valid data should work
        $user = MethodValidatedTestUser::from([
            'name' => 'John Doe',
            'email' => 'john@example.com',
            'age' => 25,
        ]);

        $this->assertEquals('John Doe', $user->name);
        $this->assertEquals('john@example.com', $user->email);
        $this->assertEquals(25, $user->age);
    }

    public function test_granite_object_with_method_based_rules_validates(): void
    {
        $this->expectException(ValidationException::class);

        MinAgeValidatedTestUser::from([
            'name' => 'John Doe',
            'email' => 'john@example.com',
            'age' => 15, // Invalid: below minimum age of 18
        ]);
    }

    public function test_named_parameters_work_with_validation(): void
    {
        // Should work with named parameters
        $user = ValidatedTestUser::from(
            name: 'John Doe',
            email: 'john@example.com',
        );

        $this->assertEquals('John Doe', $user->name);
        $this->assertEquals('john@example.com', $user->email);
    }

    public function test_mixed_usage_works_with_validation(): void
    {
        $baseData = ['name' => 'John Doe', 'email' => 'john@example.com'];

        // Should work with mixed usage
        $user = ValidatedTestUser::from(
            $baseData,
            age: 30,
        );

        $this->assertEquals('John Doe', $user->name);
        $this->assertEquals('john@example.com', $user->email);
        $this->assertEquals(30, $user->age);
    }
}
