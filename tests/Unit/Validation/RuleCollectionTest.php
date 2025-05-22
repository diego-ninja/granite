<?php
// tests/Unit/Validation/RuleCollectionTest.php

declare(strict_types=1);

namespace Tests\Unit\Validation;

use Ninja\Granite\Validation\RuleCollection;
use Ninja\Granite\Validation\Rules\Required;
use Ninja\Granite\Validation\Rules\StringType;
use Ninja\Granite\Validation\Rules\Min;
use Ninja\Granite\Validation\Rules\Max;
use Ninja\Granite\Validation\Rules\Email;
use PHPUnit\Framework\Attributes\CoversClass;
use Tests\Helpers\TestCase;

#[CoversClass(RuleCollection::class)]
class RuleCollectionTest extends TestCase
{
    public function test_creates_collection_with_property_name(): void
    {
        $collection = new RuleCollection('email');

        $this->assertEquals('email', $collection->getProperty());
        $this->assertEmpty($collection->getRules());
    }

    public function test_creates_collection_with_single_rule(): void
    {
        $rule = new Required();
        $collection = new RuleCollection('name', $rule);

        $this->assertEquals('name', $collection->getProperty());
        $this->assertCount(1, $collection->getRules());
        $this->assertSame($rule, $collection->getRules()[0]);
    }

    public function test_creates_collection_with_array_of_rules(): void
    {
        $rules = [new Required(), new StringType(), new Min(3)];
        $collection = new RuleCollection('username', $rules);

        $this->assertEquals('username', $collection->getProperty());
        $this->assertCount(3, $collection->getRules());

        foreach ($rules as $index => $rule) {
            $this->assertSame($rule, $collection->getRules()[$index]);
        }
    }

    public function test_creates_collection_with_mixed_valid_invalid_rules(): void
    {
        $validRule = new Required();
        $invalidItem = 'not-a-rule';
        $anotherValidRule = new StringType();

        $collection = new RuleCollection('field', [$validRule, $invalidItem, $anotherValidRule]);

        // Should only include valid ValidationRule instances
        $this->assertCount(2, $collection->getRules());
        $this->assertSame($validRule, $collection->getRules()[0]);
        $this->assertSame($anotherValidRule, $collection->getRules()[1]);
    }

    public function test_adds_single_rule(): void
    {
        $collection = new RuleCollection('email');
        $rule = new Email();

        $result = $collection->add($rule);

        $this->assertSame($collection, $result); // Method chaining
        $this->assertCount(1, $collection->getRules());
        $this->assertSame($rule, $collection->getRules()[0]);
    }

    public function test_adds_multiple_rules_sequentially(): void
    {
        $collection = new RuleCollection('password');

        $required = new Required();
        $stringType = new StringType();
        $minLength = new Min(8);

        $collection->add($required)
            ->add($stringType)
            ->add($minLength);

        $rules = $collection->getRules();
        $this->assertCount(3, $rules);
        $this->assertSame($required, $rules[0]);
        $this->assertSame($stringType, $rules[1]);
        $this->assertSame($minLength, $rules[2]);
    }

    public function test_validates_value_against_all_rules_success(): void
    {
        $collection = new RuleCollection('username', [
            new Required(),
            new StringType(),
            new Min(3),
            new Max(20)
        ]);

        $errors = $collection->validate('validuser');

        $this->assertEmpty($errors);
    }

    public function test_validates_value_against_all_rules_with_failures(): void
    {
        $collection = new RuleCollection('username', [
            new Required(),
            new StringType(),
            new Min(10),      // Will fail
            new Max(5)        // Will also fail
        ]);

        $errors = $collection->validate('short');

        $this->assertCount(1, $errors);
        $this->assertStringContainsString('at least 10', $errors[0]);
    }

    public function test_validates_null_value(): void
    {
        $collection = new RuleCollection('optional', [
            new StringType(), // Should pass for null
            new Min(5)        // Should pass for null
        ]);

        $errors = $collection->validate(null);

        $this->assertEmpty($errors);
    }

    public function test_validates_null_value_with_required_rule(): void
    {
        $collection = new RuleCollection('required_field', [
            new Required(),   // Should fail for null
            new StringType()  // Should pass for null
        ]);

        $errors = $collection->validate(null);

        $this->assertCount(1, $errors);
        $this->assertStringContainsString('required', $errors[0]);
    }

    public function test_validates_with_all_data_parameter(): void
    {
        // Create a mock rule that uses allData
        $mockRule = $this->createMock(\Ninja\Granite\Validation\ValidationRule::class);
        $mockRule->expects($this->once())
            ->method('validate')
            ->with(
                $this->equalTo('test'),
                $this->equalTo(['username' => 'test', 'email' => 'test@example.com'])
            )
            ->willReturn(true);

        $collection = new RuleCollection('username', [$mockRule]);
        $allData = ['username' => 'test', 'email' => 'test@example.com'];

        $errors = $collection->validate('test', $allData);

        $this->assertEmpty($errors);
    }

    public function test_validates_stops_on_first_failure_per_rule(): void
    {
        // Each rule is evaluated independently - all rules run even if some fail
        $mockRule1 = $this->createMock(\Ninja\Granite\Validation\ValidationRule::class);
        $mockRule1->method('validate')->willReturn(false);
        $mockRule1->method('message')->willReturn('Rule 1 failed');

        $mockRule2 = $this->createMock(\Ninja\Granite\Validation\ValidationRule::class);
        $mockRule2->expects($this->once())->method('validate')->willReturn(false);
        $mockRule2->method('message')->willReturn('Rule 2 failed');

        $collection = new RuleCollection('field', [$mockRule1, $mockRule2]);

        $errors = $collection->validate('test');

        $this->assertCount(2, $errors);
        $this->assertEquals('Rule 1 failed', $errors[0]);
        $this->assertEquals('Rule 2 failed', $errors[1]);
    }

    public function test_returns_empty_rules_array_initially(): void
    {
        $collection = new RuleCollection('field');

        $this->assertIsArray($collection->getRules());
        $this->assertEmpty($collection->getRules());
    }

    public function test_validates_empty_rules_collection(): void
    {
        $collection = new RuleCollection('field');

        $errors = $collection->validate('any-value');

        $this->assertEmpty($errors);
    }

    public function test_preserves_rule_order(): void
    {
        $collection = new RuleCollection('field');

        $rule1 = new Required();
        $rule2 = new StringType();
        $rule3 = new Min(5);
        $rule4 = new Max(10);

        $collection->add($rule1)
            ->add($rule2)
            ->add($rule3)
            ->add($rule4);

        $rules = $collection->getRules();
        $this->assertSame($rule1, $rules[0]);
        $this->assertSame($rule2, $rules[1]);
        $this->assertSame($rule3, $rules[2]);
        $this->assertSame($rule4, $rules[3]);
    }

    public function test_validates_complex_scenario(): void
    {
        $collection = new RuleCollection('email', [
            new Required(),
            new StringType(),
            new Email(),
            new Max(255)
        ]);

        // Valid email
        $this->assertEmpty($collection->validate('user@example.com'));

        // Invalid email - multiple failures
        $errors = $collection->validate(123);
        $this->assertNotEmpty($errors);
        $this->assertStringContainsString('string', $errors[0]);
        $this->assertStringContainsString('email', $errors[1]);
    }

    public function test_handles_rule_exceptions_gracefully(): void
    {
        $mockRule = $this->createMock(\Ninja\Granite\Validation\ValidationRule::class);
        $mockRule->method('validate')
            ->willThrowException(new \Exception('Rule exception'));
        $mockRule->method('message')
            ->willReturn('Rule failed');

        $collection = new RuleCollection('field', [$mockRule]);

        // Should handle exceptions gracefully (behavior may vary by implementation)
        try {
            $errors = $collection->validate('test');
            // If no exception is thrown, the rule should be treated as failed
            $this->assertNotEmpty($errors);
        } catch (\Exception $e) {
            // If exception bubbles up, that's also acceptable
            $this->assertEquals('Rule exception', $e->getMessage());
        }
    }

    public function test_property_name_is_immutable(): void
    {
        $collection = new RuleCollection('original_name');

        $this->assertEquals('original_name', $collection->getProperty());

        // Property name should not be changeable after construction
        // (No setter method should exist)
        $this->assertFalse(method_exists($collection, 'setProperty'));
    }

    public function test_validates_with_custom_error_messages(): void
    {
        $required = new Required();
        $required->withMessage('This field is mandatory');

        $minLength = new Min(10);
        $minLength->withMessage('Must be at least 10 characters');

        $collection = new RuleCollection('field', [$required, $minLength]);

        $errors = $collection->validate('short');

        $this->assertCount(1, $errors);
        $this->assertEquals('Must be at least 10 characters', $errors[0]);
    }

    public function test_performance_with_many_rules(): void
    {
        $rules = [
            new Required(),
            new StringType(),
            new Min(5),
            new Max(100),
        ];

        // Add some duplicate rules to simulate larger collections
        for ($i = 0; $i < 10; $i++) {
            $rules[] = new Min(1);
            $rules[] = new Max(200);
        }

        $collection = new RuleCollection('field', $rules);

        $start = microtime(true);

        for ($i = 0; $i < 1000; $i++) {
            $collection->validate('valid test string');
        }

        $elapsed = microtime(true) - $start;

        // Should complete 1000 validations with many rules in reasonable time
        $this->assertLessThan(0.1, $elapsed, "RuleCollection validation took too long: {$elapsed}s");
    }

    public function test_validates_different_value_types(): void
    {
        $collection = new RuleCollection('mixed_field');

        // Should handle different value types gracefully
        $this->assertEmpty($collection->validate('string'));
        $this->assertEmpty($collection->validate(123));
        $this->assertEmpty($collection->validate(3.14));
        $this->assertEmpty($collection->validate(true));
        $this->assertEmpty($collection->validate([]));
        $this->assertEmpty($collection->validate(null));
        $this->assertEmpty($collection->validate(new \stdClass()));
    }

    public function test_rule_collection_with_realistic_email_validation(): void
    {
        $collection = new RuleCollection('email', [
            new Required(),
            new StringType(),
            new Email(),
            new Max(254) // RFC 5321 limit
        ]);

        $validEmails = [
            'user@example.com',
            'test.email@domain.org',
            'admin@sub.domain.co.uk'
        ];

        foreach ($validEmails as $email) {
            $errors = $collection->validate($email);
            $this->assertEmpty($errors, "Valid email '$email' should not have errors");
        }

        $invalidEmails = [
            '',                    // Required fails
            123,                   // StringType fails
            'invalid-email',       // Email fails
            str_repeat('a', 250) . '@example.com' // Max length fails
        ];

        foreach ($invalidEmails as $email) {
            $errors = $collection->validate($email);
            $this->assertNotEmpty($errors, "Invalid email should have errors: " . var_export($email, true));
        }
    }
}