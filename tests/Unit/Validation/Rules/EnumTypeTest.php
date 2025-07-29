<?php

// tests/Unit/Validation/Rules/EnumTypeTest.php

declare(strict_types=1);

namespace Tests\Unit\Validation\Rules;

use Ninja\Granite\Validation\Rules\EnumType;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use stdClass;
use Tests\Fixtures\Enums\Color;
use Tests\Fixtures\Enums\Priority;
use Tests\Fixtures\Enums\Size;
use Tests\Fixtures\Enums\UserStatus;
use Tests\Helpers\TestCase;

#[CoversClass(EnumType::class)]
class EnumTypeTest extends TestCase
{
    public static function validEnumScenariosProvider(): array
    {
        return [
            // UserStatus (string backed)
            'valid user status enum' => [UserStatus::class, UserStatus::ACTIVE, true],
            'valid user status string' => [UserStatus::class, 'active', true],
            'invalid user status string' => [UserStatus::class, 'unknown', false],
            'user status wrong type' => [UserStatus::class, 1, false],

            // Priority (int backed)
            'valid priority enum' => [Priority::class, Priority::HIGH, true],
            'valid priority int' => [Priority::class, 3, true],
            'invalid priority int' => [Priority::class, 0, false],
            'priority wrong type' => [Priority::class, 'high', false],

            // Color (unit enum)
            'valid color enum' => [Color::class, Color::RED, true],
            'valid color name' => [Color::class, 'RED', true],
            'invalid color name' => [Color::class, 'PURPLE', false],
            'color wrong case' => [Color::class, 'red', false],

            // Size (string backed)
            'valid size enum' => [Size::class, Size::LARGE, true],
            'valid size string' => [Size::class, 'L', true],
            'invalid size string' => [Size::class, 'LARGE', false],
            'size wrong type' => [Size::class, 3, false],
        ];
    }
    public function test_validates_backed_enum_instances(): void
    {
        $rule = new EnumType(UserStatus::class);

        $this->assertTrue($rule->validate(UserStatus::ACTIVE));
        $this->assertTrue($rule->validate(UserStatus::INACTIVE));
        $this->assertTrue($rule->validate(UserStatus::PENDING));
        $this->assertTrue($rule->validate(UserStatus::SUSPENDED));
    }

    public function test_validates_unit_enum_instances(): void
    {
        $rule = new EnumType(Color::class);

        $this->assertTrue($rule->validate(Color::RED));
        $this->assertTrue($rule->validate(Color::GREEN));
        $this->assertTrue($rule->validate(Color::BLUE));
        $this->assertTrue($rule->validate(Color::YELLOW));
    }

    public function test_validates_backed_enum_from_string_values(): void
    {
        $rule = new EnumType(UserStatus::class);

        $this->assertTrue($rule->validate('active'));
        $this->assertTrue($rule->validate('inactive'));
        $this->assertTrue($rule->validate('pending'));
        $this->assertTrue($rule->validate('suspended'));

        $this->assertFalse($rule->validate('unknown'));
        $this->assertFalse($rule->validate('deleted'));
        $this->assertFalse($rule->validate(''));
    }

    public function test_validates_backed_enum_from_integer_values(): void
    {
        $rule = new EnumType(Priority::class);

        $this->assertTrue($rule->validate(1)); // Priority::LOW
        $this->assertTrue($rule->validate(2)); // Priority::MEDIUM
        $this->assertTrue($rule->validate(3)); // Priority::HIGH
        $this->assertTrue($rule->validate(4)); // Priority::URGENT

        $this->assertFalse($rule->validate(0));
        $this->assertFalse($rule->validate(5));
        $this->assertFalse($rule->validate(-1));
    }

    public function test_validates_unit_enum_from_string_names(): void
    {
        $rule = new EnumType(Color::class);

        $this->assertTrue($rule->validate('RED'));
        $this->assertTrue($rule->validate('GREEN'));
        $this->assertTrue($rule->validate('BLUE'));
        $this->assertTrue($rule->validate('YELLOW'));
        $this->assertTrue($rule->validate('BLACK'));
        $this->assertTrue($rule->validate('WHITE'));

        $this->assertFalse($rule->validate('red'));      // Case sensitive
        $this->assertFalse($rule->validate('PURPLE'));
        $this->assertFalse($rule->validate(''));
        $this->assertFalse($rule->validate('ORANGE'));
    }

    public function test_validates_without_specific_enum_class(): void
    {
        $rule = new EnumType(); // No specific enum class

        // Should accept any enum instance
        $this->assertTrue($rule->validate(UserStatus::ACTIVE));
        $this->assertTrue($rule->validate(Priority::HIGH));
        $this->assertTrue($rule->validate(Color::RED));

        // Should reject string/int values when no class specified
        $this->assertFalse($rule->validate('active'));
        $this->assertFalse($rule->validate(1));
        $this->assertFalse($rule->validate('RED'));
    }

    public function test_validates_null_as_valid(): void
    {
        $rule = new EnumType(UserStatus::class);
        $this->assertTrue($rule->validate(null));
    }

    public function test_rejects_wrong_enum_type(): void
    {
        $rule = new EnumType(UserStatus::class);

        // Should reject enums from different classes
        $this->assertFalse($rule->validate(Priority::HIGH));
        $this->assertFalse($rule->validate(Color::RED));
        $this->assertFalse($rule->validate(Size::LARGE));
    }

    public function test_rejects_non_enum_values(): void
    {
        $rule = new EnumType(UserStatus::class);

        $this->assertFalse($rule->validate('not-enum'));
        $this->assertFalse($rule->validate(123));
        $this->assertFalse($rule->validate(true));
        $this->assertFalse($rule->validate([]));
        $this->assertFalse($rule->validate(new stdClass()));
        $this->assertFalse($rule->validate(3.14));
    }

    public function test_validates_string_enum_with_various_types(): void
    {
        $rule = new EnumType(Size::class);

        // Valid string enum values
        $this->assertTrue($rule->validate('S'));
        $this->assertTrue($rule->validate('M'));
        $this->assertTrue($rule->validate('L'));
        $this->assertTrue($rule->validate('XL'));

        // Invalid values
        $this->assertFalse($rule->validate('SMALL'));
        $this->assertFalse($rule->validate('s'));
        $this->assertFalse($rule->validate('XXL'));
        $this->assertFalse($rule->validate(1));
    }

    public function test_strict_type_validation_for_backed_enums(): void
    {
        $stringRule = new EnumType(UserStatus::class);
        $intRule = new EnumType(Priority::class);

        // String enum should not accept integers that could be cast
        $this->assertFalse($stringRule->validate(1));
        $this->assertFalse($stringRule->validate(0));

        // Integer enum should not accept strings that could be cast
        $this->assertFalse($intRule->validate('1'));
        $this->assertFalse($intRule->validate('2'));
    }

    public function test_validates_case_sensitivity_for_unit_enums(): void
    {
        $rule = new EnumType(Color::class);

        // Exact case should work
        $this->assertTrue($rule->validate('RED'));
        $this->assertTrue($rule->validate('GREEN'));

        // Different case should fail
        $this->assertFalse($rule->validate('red'));
        $this->assertFalse($rule->validate('Red'));
        $this->assertFalse($rule->validate('rEd'));
    }

    public function test_returns_default_message_with_specific_enum(): void
    {
        $rule = new EnumType(UserStatus::class);
        $message = $rule->message('status');

        $this->assertEquals('status must be a valid case of ' . UserStatus::class, $message);
    }

    public function test_returns_default_message_without_specific_enum(): void
    {
        $rule = new EnumType();
        $message = $rule->message('value');

        $this->assertEquals('value must be a valid enum', $message);
    }

    public function test_returns_custom_message_when_set(): void
    {
        $rule = new EnumType(UserStatus::class);
        $customMessage = 'Status must be one of the allowed values';
        $rule->withMessage($customMessage);

        $message = $rule->message('status');
        $this->assertEquals($customMessage, $message);
    }

    public function test_with_message_returns_same_instance(): void
    {
        $rule = new EnumType(UserStatus::class);
        $result = $rule->withMessage('Custom message');

        $this->assertSame($rule, $result);
    }

    #[DataProvider('validEnumScenariosProvider')]
    public function test_validates_various_enum_scenarios(string $enumClass, mixed $value, bool $expected): void
    {
        $rule = new EnumType($enumClass);
        $this->assertEquals($expected, $rule->validate($value));
    }

    public function test_rule_implements_validation_rule_interface(): void
    {
        $rule = new EnumType(UserStatus::class);
        $this->assertInstanceOf(\Ninja\Granite\Validation\ValidationRule::class, $rule);
    }

    public function test_extends_abstract_rule(): void
    {
        $rule = new EnumType(UserStatus::class);
        $this->assertInstanceOf(\Ninja\Granite\Validation\Rules\AbstractRule::class, $rule);
    }

    public function test_validates_mixed_enum_types_without_class(): void
    {
        $rule = new EnumType(); // No specific class

        // Should accept any enum instance
        $this->assertTrue($rule->validate(UserStatus::ACTIVE));
        $this->assertTrue($rule->validate(Priority::MEDIUM));
        $this->assertTrue($rule->validate(Color::BLUE));
        $this->assertTrue($rule->validate(Size::MEDIUM));

        // Should reject non-enum values
        $this->assertFalse($rule->validate('active'));
        $this->assertFalse($rule->validate(2));
        $this->assertFalse($rule->validate('BLUE'));
        $this->assertFalse($rule->validate('M'));
    }

    public function test_performance_with_large_enum(): void
    {
        // Create a mock large enum scenario
        $rule = new EnumType(UserStatus::class);

        $testValues = [
            UserStatus::ACTIVE,
            'active',
            'inactive',
            'invalid',
            123,
            'unknown',
            UserStatus::PENDING,
            Priority::HIGH, // Wrong enum type
        ];

        $start = microtime(true);

        for ($i = 0; $i < 1000; $i++) {
            foreach ($testValues as $value) {
                $rule->validate($value);
            }
        }

        $elapsed = microtime(true) - $start;

        // Should complete 8000 validations in reasonable time (less than 100ms)
        $this->assertLessThan(0.1, $elapsed, "Enum validation took too long: {$elapsed}s");
    }

    public function test_enum_edge_cases(): void
    {
        $rule = new EnumType(UserStatus::class);

        // Empty string should not match any enum
        $this->assertFalse($rule->validate(''));

        // Numeric strings should not match string enums
        $this->assertFalse($rule->validate('0'));
        $this->assertFalse($rule->validate('1'));

        // Boolean values should not match
        $this->assertFalse($rule->validate(true));
        $this->assertFalse($rule->validate(false));

        // Array/object should not match
        $this->assertFalse($rule->validate(['active']));
        $this->assertFalse($rule->validate((object) ['status' => 'active']));
    }

    public function test_validates_all_cases_of_enum(): void
    {
        $userRule = new EnumType(UserStatus::class);
        $priorityRule = new EnumType(Priority::class);
        $colorRule = new EnumType(Color::class);
        $sizeRule = new EnumType(Size::class);

        // Test all UserStatus cases
        foreach (UserStatus::cases() as $case) {
            $this->assertTrue($userRule->validate($case));
            $this->assertTrue($userRule->validate($case->value));
        }

        // Test all Priority cases
        foreach (Priority::cases() as $case) {
            $this->assertTrue($priorityRule->validate($case));
            $this->assertTrue($priorityRule->validate($case->value));
        }

        // Test all Color cases (unit enum - no values)
        foreach (Color::cases() as $case) {
            $this->assertTrue($colorRule->validate($case));
            $this->assertTrue($colorRule->validate($case->name));
        }

        // Test all Size cases
        foreach (Size::cases() as $case) {
            $this->assertTrue($sizeRule->validate($case));
            $this->assertTrue($sizeRule->validate($case->value));
        }
    }
}
