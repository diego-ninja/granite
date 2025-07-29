<?php

// tests/Unit/Validation/RuleExtractorTest.php

declare(strict_types=1);

namespace Tests\Unit\Validation;

use Attribute;
use Ninja\Granite\Serialization\Attributes\Hidden;
use Ninja\Granite\Serialization\Attributes\SerializedName;
use Ninja\Granite\Validation\RuleExtractor;
use Ninja\Granite\Validation\Rules\Email;
use Ninja\Granite\Validation\Rules\Max;
use Ninja\Granite\Validation\Rules\Min;
use Ninja\Granite\Validation\Rules\Required;
use Ninja\Granite\Validation\Rules\StringType;
use PHPUnit\Framework\Attributes\CoversClass;
use ReflectionMethod;
use Tests\Fixtures\VOs\ValidatedUserVO;
use Tests\Helpers\TestCase;

#[CoversClass(RuleExtractor::class)]
class RuleExtractorTest extends TestCase
{
    public function test_extracts_rules_from_class_with_validation_attributes(): void
    {
        $rules = RuleExtractor::extractRules(ValidatedUserVO::class);

        $this->assertIsArray($rules);
        $this->assertArrayHasKey('name', $rules);
        $this->assertArrayHasKey('email', $rules);
        $this->assertArrayHasKey('age', $rules);
    }

    public function test_extracts_required_rule_from_name_property(): void
    {
        $rules = RuleExtractor::extractRules(ValidatedUserVO::class);

        $nameRules = $rules['name'];
        $this->assertNotEmpty($nameRules);

        $requiredRule = null;
        $stringTypeRule = null;
        $minRule = null;

        foreach ($nameRules as $rule) {
            if ($rule instanceof Required) {
                $requiredRule = $rule;
            }
            if ($rule instanceof StringType) {
                $stringTypeRule = $rule;
            }
            if ($rule instanceof Min) {
                $minRule = $rule;
            }
        }

        $this->assertInstanceOf(Required::class, $requiredRule);
        $this->assertInstanceOf(StringType::class, $stringTypeRule);
        $this->assertInstanceOf(Min::class, $minRule);
    }

    public function test_extracts_email_rules_from_email_property(): void
    {
        $rules = RuleExtractor::extractRules(ValidatedUserVO::class);

        $emailRules = $rules['email'];
        $this->assertNotEmpty($emailRules);

        $requiredRule = null;
        $emailRule = null;

        foreach ($emailRules as $rule) {
            if ($rule instanceof Required) {
                $requiredRule = $rule;
            }
            if ($rule instanceof Email) {
                $emailRule = $rule;
            }
        }

        $this->assertInstanceOf(Required::class, $requiredRule);
        $this->assertInstanceOf(Email::class, $emailRule);
    }

    public function test_extracts_min_max_rules_from_age_property(): void
    {
        $rules = RuleExtractor::extractRules(ValidatedUserVO::class);

        $ageRules = $rules['age'];
        $this->assertNotEmpty($ageRules);

        $minRule = null;
        $maxRule = null;

        foreach ($ageRules as $rule) {
            if ($rule instanceof Min) {
                $minRule = $rule;
            }
            if ($rule instanceof Max) {
                $maxRule = $rule;
            }
        }

        $this->assertInstanceOf(Min::class, $minRule);
        $this->assertInstanceOf(Max::class, $maxRule);
    }

    public function test_extracts_custom_error_messages(): void
    {
        $rules = RuleExtractor::extractRules(ValidatedUserVO::class);

        $nameRules = $rules['name'];
        $requiredRule = null;
        $minRule = null;

        foreach ($nameRules as $rule) {
            if ($rule instanceof Required) {
                $requiredRule = $rule;
            }
            if ($rule instanceof Min) {
                $minRule = $rule;
            }
        }

        // Check that custom messages are preserved
        $this->assertEquals('Please provide a name', $requiredRule->message('name'));
        $this->assertEquals('Name must be at least 2 characters', $minRule->message('name'));
    }

    public function test_extracts_rules_from_class_without_attributes(): void
    {
        $testClass = new class () {
            public string $plainProperty;
            public int $anotherProperty;
        };

        $rules = RuleExtractor::extractRules($testClass::class);

        $this->assertIsArray($rules);
        $this->assertEmpty($rules);
    }

    public function test_extracts_rules_from_class_with_mixed_attributes(): void
    {
        $testClass = new class () {
            #[\Ninja\Granite\Validation\Attributes\Required]
            #[\Ninja\Granite\Validation\Attributes\StringType]
            public string $validatedProperty;

            public string $plainProperty;

            #[\Ninja\Granite\Validation\Attributes\Min(1)]
            #[\Ninja\Granite\Validation\Attributes\Max(100)]
            public int $numericProperty;
        };

        $rules = RuleExtractor::extractRules($testClass::class);

        $this->assertArrayHasKey('validatedProperty', $rules);
        $this->assertArrayNotHasKey('plainProperty', $rules);
        $this->assertArrayHasKey('numericProperty', $rules);

        $this->assertCount(2, $rules['validatedProperty']); // Required + StringType
        $this->assertCount(2, $rules['numericProperty']); // Min + Max
    }

    public function test_extracts_rules_from_properties_with_non_validation_attributes(): void
    {
        $testClass = new class () {
            #[\Ninja\Granite\Validation\Attributes\Required]
            #[SerializedName('custom_name')]
            public string $mixedAttributesProperty;

            #[Hidden]
            public string $onlySerializationAttribute;
        };

        $rules = RuleExtractor::extractRules($testClass::class);

        $this->assertArrayHasKey('mixedAttributesProperty', $rules);
        $this->assertArrayNotHasKey('onlySerializationAttribute', $rules);

        $this->assertCount(1, $rules['mixedAttributesProperty']); // Only Required
    }

    public function test_extracts_rules_only_from_attributes_with_as_rule_method(): void
    {
        $testClass = new class () {
            #[\Ninja\Granite\Validation\Attributes\Required]
            public string $validAttribute;

            #[TestInvalidAttribute('test')]
            public string $invalidAttribute;
        };

        $rules = RuleExtractor::extractRules($testClass::class);

        $this->assertArrayHasKey('validAttribute', $rules);
        $this->assertArrayNotHasKey('invalidAttribute', $rules);
    }

    public function test_handles_attributes_that_return_non_validation_rules(): void
    {
        $testClass = new class () {
            #[\Ninja\Granite\Validation\Attributes\Required]
            #[TestAttributeWithWrongReturnType]
            public string $property;
        };

        $rules = RuleExtractor::extractRules($testClass::class);

        $this->assertArrayHasKey('property', $rules);
        $this->assertCount(1, $rules['property']); // Only the Required rule
    }

    public function test_extracts_rules_from_private_and_protected_properties(): void
    {
        // RuleExtractor should only work with public properties based on ReflectionCache usage
        $testClass = new class () {
            #[\Ninja\Granite\Validation\Attributes\Required]
            public string $publicProperty;

            #[\Ninja\Granite\Validation\Attributes\Required]
            protected string $protectedProperty;

            #[\Ninja\Granite\Validation\Attributes\Required]
            private string $privateProperty;
        };

        $rules = RuleExtractor::extractRules($testClass::class);

        $this->assertArrayHasKey('publicProperty', $rules);
        $this->assertArrayNotHasKey('protectedProperty', $rules);
        $this->assertArrayNotHasKey('privateProperty', $rules);
    }

    public function test_static_method_is_callable(): void
    {
        $this->assertTrue(method_exists(RuleExtractor::class, 'extractRules'));

        $reflection = new ReflectionMethod(RuleExtractor::class, 'extractRules');
        $this->assertTrue($reflection->isStatic());
        $this->assertTrue($reflection->isPublic());
    }

    public function test_handles_reflection_exceptions_gracefully(): void
    {
        // Test with non-existent class should throw ReflectionException
        $this->expectException(\Ninja\Granite\Exceptions\ReflectionException::class);

        RuleExtractor::extractRules('NonExistentClass');
    }


    public function test_performance_with_large_class(): void
    {
        // Create a class with many properties
        $classCode = '
        $testClass = new class {
        ';

        for ($i = 0; $i < 50; $i++) {
            $classCode .= "
            #[\Ninja\Granite\Validation\Attributes\Required]
            #[\Ninja\Granite\Validation\Attributes\StringType]
            public string \$property{$i};
            ";
        }

        $classCode .= '};';

        eval($classCode);

        $start = microtime(true);

        for ($j = 0; $j < 100; $j++) {
            RuleExtractor::extractRules($testClass::class);
        }

        $elapsed = microtime(true) - $start;

        // Should complete 100 extractions of 50 properties in reasonable time
        $this->assertLessThan(0.5, $elapsed, "Rule extraction took too long: {$elapsed}s");
    }

    public function test_caching_behavior(): void
    {
        // First extraction
        $start1 = microtime(true);
        $rules1 = RuleExtractor::extractRules(ValidatedUserVO::class);
        $time1 = microtime(true) - $start1;

        // Second extraction (should potentially benefit from reflection caching)
        $start2 = microtime(true);
        $rules2 = RuleExtractor::extractRules(ValidatedUserVO::class);
        $time2 = microtime(true) - $start2;

        // Results should be the same
        $this->assertEquals($rules1, $rules2);

        // Second call might be faster due to reflection caching
        // (This is not guaranteed but is likely in most cases)
        $this->assertLessThanOrEqual($time1 * 2, $time2); // Allow some variance
    }

    public function test_returns_validation_rule_instances(): void
    {
        $rules = RuleExtractor::extractRules(ValidatedUserVO::class);

        foreach ($rules as $propertyName => $propertyRules) {
            foreach ($propertyRules as $rule) {
                $this->assertInstanceOf(
                    \Ninja\Granite\Validation\ValidationRule::class,
                    $rule,
                    "Rule for property '{$propertyName}' should implement ValidationRule interface",
                );
            }
        }
    }

    public function test_preserves_rule_order(): void
    {
        $testClass = new class () {
            #[\Ninja\Granite\Validation\Attributes\Required]
            #[\Ninja\Granite\Validation\Attributes\StringType]
            #[\Ninja\Granite\Validation\Attributes\Min(5)]
            #[\Ninja\Granite\Validation\Attributes\Max(100)]
            public string $orderedProperty;
        };

        $rules = RuleExtractor::extractRules($testClass::class);
        $propertyRules = $rules['orderedProperty'];

        // Rules should be in the order they appear on the property
        $this->assertInstanceOf(Required::class, $propertyRules[0]);
        $this->assertInstanceOf(StringType::class, $propertyRules[1]);
        $this->assertInstanceOf(Min::class, $propertyRules[2]);
        $this->assertInstanceOf(Max::class, $propertyRules[3]);
    }
}

// Helper attributes for testing
#[Attribute]
class TestInvalidAttribute
{
    public function __construct(public string $value) {}

    // This attribute doesn't have asRule() method
}

#[Attribute]
class TestAttributeWithWrongReturnType
{
    public function asRule(): string // Wrong return type
    {
        return 'not-a-rule';
    }
}
