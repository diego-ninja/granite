<?php

// tests/Unit/Validation/GraniteValidatorTest.php

declare(strict_types=1);

namespace Tests\Unit\Validation;

use Ninja\Granite\Exceptions\ValidationException;
use Ninja\Granite\Validation\GraniteValidator;
use Ninja\Granite\Validation\RuleCollection;
use Ninja\Granite\Validation\Rules\Email;
use Ninja\Granite\Validation\Rules\Max;
use Ninja\Granite\Validation\Rules\Min;
use Ninja\Granite\Validation\Rules\Required;
use Ninja\Granite\Validation\Rules\StringType;
use PHPUnit\Framework\Attributes\CoversClass;
use Tests\Helpers\TestCase;

#[CoversClass(GraniteValidator::class)] class GraniteValidatorTest extends TestCase
{
    public function test_creates_empty_validator(): void
    {
        $validator = new GraniteValidator();

        $this->assertInstanceOf(GraniteValidator::class, $validator);
    }

    public function test_creates_validator_with_single_rule_collection(): void
    {
        $collection = new RuleCollection('name', new Required());
        $validator = new GraniteValidator($collection);

        $this->assertInstanceOf(GraniteValidator::class, $validator);
    }

    public function test_creates_validator_with_multiple_rule_collections(): void
    {
        $collections = [
            new RuleCollection('name', new Required()),
            new RuleCollection('email', [new Required(), new Email()]),
        ];
        $validator = new GraniteValidator($collections);

        $this->assertInstanceOf(GraniteValidator::class, $validator);
    }

    public function test_adds_rule_collection(): void
    {
        $validator = new GraniteValidator();
        $collection = new RuleCollection('name', new Required());

        $result = $validator->addRules($collection);

        $this->assertSame($validator, $result);
    }

    public function test_adds_single_rule_for_property(): void
    {
        $validator = new GraniteValidator();

        $result = $validator->addRule('name', new Required());

        $this->assertSame($validator, $result);
    }

    public function test_adds_multiple_rules_for_same_property(): void
    {
        $validator = new GraniteValidator();

        $validator->addRule('name', new Required())
            ->addRule('name', new StringType())
            ->addRule('name', new Min(2));

        // Should not throw exception - rules are accumulated
        $this->assertTrue(true);
    }

    public function test_creates_rule_collection_for_property(): void
    {
        $validator = new GraniteValidator();

        $collection = $validator->forProperty('name');

        $this->assertInstanceOf(RuleCollection::class, $collection);
        $this->assertEquals('name', $collection->getProperty());
    }

    public function test_returns_same_collection_for_same_property(): void
    {
        $validator = new GraniteValidator();

        $collection1 = $validator->forProperty('name');
        $collection2 = $validator->forProperty('name');

        $this->assertSame($collection1, $collection2);
    }

    public function test_validates_data_successfully(): void
    {
        $validator = new GraniteValidator();
        $validator->addRule('name', new Required())
            ->addRule('name', new StringType());

        $data = ['name' => 'John Doe'];

        // Should not throw exception
        $validator->validate($data);
        $this->assertTrue(true);
    }

    public function test_throws_validation_exception_on_failure(): void
    {
        $validator = new GraniteValidator();
        $validator->addRule('name', new Required());

        $data = ['name' => null];

        $this->expectException(ValidationException::class);
        $this->expectExceptionMessage('Validation failed for Object');

        $validator->validate($data);
    }

    public function test_validates_multiple_fields(): void
    {
        $validator = new GraniteValidator();
        $validator->addRule('name', new Required())
            ->addRule('email', new Required())
            ->addRule('email', new Email());

        $validData = [
            'name' => 'John Doe',
            'email' => 'john@example.com',
        ];

        $validator->validate($validData);
        $this->assertTrue(true);
    }

    public function test_collects_all_validation_errors(): void
    {
        $validator = new GraniteValidator();
        $validator->addRule('name', new Required())
            ->addRule('email', new Required())
            ->addRule('email', new Email());

        $invalidData = [
            'name' => null,
            'email' => 'invalid-email',
        ];

        try {
            $validator->validate($invalidData);
            $this->fail('Expected ValidationException');
        } catch (ValidationException $e) {
            $errors = $e->getErrors();

            $this->assertArrayHasKey('name', $errors);
            $this->assertArrayHasKey('email', $errors);
            $this->assertTrue($e->hasFieldErrors('name'));
            $this->assertTrue($e->hasFieldErrors('email'));
        }
    }

    public function test_handles_missing_fields_with_required_rule(): void
    {
        $validator = new GraniteValidator();
        $validator->addRule('name', new Required());

        $data = []; // Missing 'name' field

        try {
            $validator->validate($data);
            $this->fail('Expected ValidationException');
        } catch (ValidationException $e) {
            $this->assertTrue($e->hasFieldErrors('name'));
            $errors = $e->getFieldErrors('name');
            $this->assertStringContainsString('required', $errors[0]);
        }
    }

    public function test_ignores_missing_fields_without_required_rule(): void
    {
        $validator = new GraniteValidator();
        $validator->addRule('name', new StringType()); // No Required rule

        $data = []; // Missing 'name' field

        // Should not throw exception
        $validator->validate($data);
        $this->assertTrue(true);
    }

    public function test_validates_with_custom_object_name(): void
    {
        $validator = new GraniteValidator();
        $validator->addRule('name', new Required());

        $data = ['name' => null];

        try {
            $validator->validate($data, 'UserVO');
            $this->fail('Expected ValidationException');
        } catch (ValidationException $e) {
            $this->assertEquals('UserVO', $e->getObjectType());
            $this->assertStringContainsString('UserVO', $e->getMessage());
        }
    }

    public function test_creates_validator_from_array_with_string_rules(): void
    {
        $rulesArray = [
            'name' => 'required|string|min:3',
            'email' => 'required|email',
            'age' => 'integer|min:18',
        ];

        $validator = GraniteValidator::fromArray($rulesArray);

        $this->assertInstanceOf(GraniteValidator::class, $validator);
    }

    public function test_validates_data_from_array_rules(): void
    {
        $rulesArray = [
            'name' => 'required|string|min:3',
            'email' => 'required|email',
        ];

        $validator = GraniteValidator::fromArray($rulesArray);

        $validData = [
            'name' => 'John Doe',
            'email' => 'john@example.com',
        ];

        $validator->validate($validData);
        $this->assertTrue(true);
    }

    public function test_from_array_with_mixed_rule_formats(): void
    {
        $rulesArray = [
            'name' => 'required|string',
            'tags' => ['required|array', 'min:1'], // Array of string rules
        ];

        $validator = GraniteValidator::fromArray($rulesArray);

        $validData = [
            'name' => 'Test',
            'tags' => ['tag1', 'tag2'],
        ];

        $validator->validate($validData);
        $this->assertTrue(true);
    }

    public function test_validates_complex_scenario(): void
    {
        $validator = new GraniteValidator();
        $validator->addRule('name', new Required())
            ->addRule('name', new StringType())
            ->addRule('name', new Min(2))
            ->addRule('name', new Max(50))
            ->addRule('email', new Required())
            ->addRule('email', new Email())
            ->addRule('age', new Min(18));

        $validData = [
            'name' => 'John Doe',
            'email' => 'john@example.com',
            'age' => 25,
        ];

        $validator->validate($validData);
        $this->assertTrue(true);
    }

    public function test_passes_all_data_to_rules(): void
    {
        // Create a mock rule that checks if allData is passed
        $mockRule = $this->createMock(\Ninja\Granite\Validation\ValidationRule::class);
        $mockRule->expects($this->once())
            ->method('validate')
            ->with(
                $this->equalTo('test value'),
                $this->equalTo(['name' => 'test value', 'email' => 'test@example.com']),
            )
            ->willReturn(true);

        $validator = new GraniteValidator();
        $validator->addRule('name', $mockRule);

        $data = ['name' => 'test value', 'email' => 'test@example.com'];
        $validator->validate($data);
    }

    public function test_method_chaining(): void
    {
        $validator = new GraniteValidator();

        $result = $validator->addRule('name', new Required())
            ->addRule('email', new Email())
            ->forProperty('age')
            ->add(new Min(18));

        // forProperty returns RuleCollection, so we test that the validator itself supports chaining
        $this->assertInstanceOf(RuleCollection::class, $result);
    }

    public function test_handles_empty_rules_array(): void
    {
        $validator = GraniteValidator::fromArray([]);

        $validator->validate(['any' => 'data']);
        $this->assertTrue(true);
    }

    public function test_handles_invalid_rule_format_gracefully(): void
    {
        $rulesArray = [
            'name' => 'required|string',
            'invalid' => 'unknown_rule|another_unknown',
        ];

        $validator = GraniteValidator::fromArray($rulesArray);

        // Should create validator without errors (unknown rules are ignored)
        $this->assertInstanceOf(GraniteValidator::class, $validator);
    }
}
