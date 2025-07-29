<?php

// tests/Unit/Validation/Rules/WhenTest.php

declare(strict_types=1);

namespace Tests\Unit\Validation\Rules;

use Ninja\Granite\Validation\Rules\Email;
use Ninja\Granite\Validation\Rules\Min;
use Ninja\Granite\Validation\Rules\Required;
use Ninja\Granite\Validation\Rules\StringType;
use Ninja\Granite\Validation\Rules\When;
use PHPUnit\Framework\Attributes\CoversClass;
use Tests\Helpers\TestCase;

#[CoversClass(When::class)]
class WhenTest extends TestCase
{
    public function test_validates_when_condition_is_true(): void
    {
        $condition = fn($value, $allData) => 'premium' === $allData['type'];
        $rule = new When($condition, new Required());

        $allData = ['type' => 'premium'];

        $this->assertTrue($rule->validate('value', $allData));
        $this->assertFalse($rule->validate(null, $allData)); // Required rule should fail
    }

    public function test_passes_when_condition_is_false(): void
    {
        $condition = fn($value, $allData) => 'premium' === $allData['type'];
        $rule = new When($condition, new Required());

        $allData = ['type' => 'basic'];

        // Should pass regardless of value since condition is false
        $this->assertTrue($rule->validate('value', $allData));
        $this->assertTrue($rule->validate(null, $allData));
        $this->assertTrue($rule->validate('', $allData));
    }

    public function test_validates_with_string_condition(): void
    {
        $condition = fn($value, $allData) => isset($allData['require_email']) && $allData['require_email'];
        $rule = new When($condition, new Email());

        // When email is required
        $requireEmail = ['require_email' => true];
        $this->assertTrue($rule->validate('test@example.com', $requireEmail));
        $this->assertFalse($rule->validate('invalid-email', $requireEmail));

        // When email is not required
        $noRequireEmail = ['require_email' => false];
        $this->assertTrue($rule->validate('invalid-email', $noRequireEmail));
        $this->assertTrue($rule->validate('', $noRequireEmail));
    }

    public function test_validates_with_numeric_condition(): void
    {
        $condition = fn($value, $allData) => ($allData['age'] ?? 0) >= 18;
        $rule = new When($condition, new StringType());

        // Adult - rule should apply
        $adultData = ['age' => 25];
        $this->assertTrue($rule->validate('valid string', $adultData));
        $this->assertFalse($rule->validate(123, $adultData));

        // Minor - rule should not apply
        $minorData = ['age' => 16];
        $this->assertTrue($rule->validate('valid string', $minorData));
        $this->assertTrue($rule->validate(123, $minorData)); // Passes because condition is false
    }

    public function test_validates_with_array_condition(): void
    {
        $condition = fn($value, $allData) => in_array('admin', $allData['roles'] ?? []);
        $rule = new When($condition, new Min(10));

        // Admin user - rule applies
        $adminData = ['roles' => ['user', 'admin']];
        $this->assertTrue($rule->validate('long enough string', $adminData));
        $this->assertFalse($rule->validate('short', $adminData));

        // Regular user - rule doesn't apply
        $userData = ['roles' => ['user']];
        $this->assertTrue($rule->validate('short', $userData));
        $this->assertTrue($rule->validate('', $userData));
    }

    public function test_validates_with_complex_condition(): void
    {
        $condition = fn($value, $allData) => isset($allData['subscription_type']) &&
                'enterprise' === $allData['subscription_type'] &&
                ($allData['user_count'] ?? 0) > 100;

        $rule = new When($condition, new Required());

        // Enterprise with high user count - rule applies
        $enterpriseData = ['subscription_type' => 'enterprise', 'user_count' => 150];
        $this->assertTrue($rule->validate('value', $enterpriseData));
        $this->assertFalse($rule->validate(null, $enterpriseData));

        // Enterprise with low user count - rule doesn't apply
        $smallEnterpriseData = ['subscription_type' => 'enterprise', 'user_count' => 50];
        $this->assertTrue($rule->validate(null, $smallEnterpriseData));

        // Non-enterprise - rule doesn't apply
        $basicData = ['subscription_type' => 'basic', 'user_count' => 200];
        $this->assertTrue($rule->validate(null, $basicData));
    }

    public function test_validates_when_condition_uses_current_value(): void
    {
        $condition = fn($value, $allData) => is_string($value) && str_starts_with($value, 'special_');
        $rule = new When($condition, new Min(15));

        // Value starts with 'special_' - rule applies
        $this->assertTrue($rule->validate('special_long_enough', []));
        $this->assertFalse($rule->validate('special_short', []));

        // Value doesn't start with 'special_' - rule doesn't apply
        $this->assertTrue($rule->validate('short', []));
        $this->assertTrue($rule->validate('regular_value', []));
    }

    public function test_validates_with_chained_rules(): void
    {
        $condition = fn($value, $allData) => $allData['strict'] ?? false;
        $stringRule = new StringType();
        $rule = new When($condition, $stringRule);

        // Strict mode - rule applies
        $strictData = ['strict' => true];
        $this->assertTrue($rule->validate('string value', $strictData));
        $this->assertFalse($rule->validate(123, $strictData));

        // Non-strict mode - rule doesn't apply
        $lenientData = ['strict' => false];
        $this->assertTrue($rule->validate(123, $lenientData));
        $this->assertTrue($rule->validate('string value', $lenientData));
    }

    public function test_validates_without_all_data(): void
    {
        $condition = fn($value, $allData) => ($allData['enabled'] ?? false);
        $rule = new When($condition, new Required());

        // No allData provided - condition should be false
        $this->assertTrue($rule->validate(null)); // Should pass because condition is false
        $this->assertTrue($rule->validate('value')); // Should pass
    }

    public function test_validates_with_null_condition_result(): void
    {
        $condition = fn($value, $allData) => null; // Returns null (falsy)
        $rule = new When($condition, new Required());

        // Null condition result should be treated as false
        $this->assertTrue($rule->validate(null, []));
        $this->assertTrue($rule->validate('value', []));
    }

    public function test_validates_with_truthy_falsy_condition(): void
    {
        $condition = fn($value, $allData) => $allData['count'] ?? 0; // Returns number
        $rule = new When($condition, new Required());

        // Truthy count - rule applies
        $this->assertTrue($rule->validate('value', ['count' => 5]));
        $this->assertFalse($rule->validate(null, ['count' => 5]));

        // Falsy count - rule doesn't apply
        $this->assertTrue($rule->validate(null, ['count' => 0]));
        $this->assertTrue($rule->validate(null, []));
    }

    public function test_returns_underlying_rule_message(): void
    {
        $condition = fn($value, $allData) => true;
        $requiredRule = new Required();
        $rule = new When($condition, $requiredRule);

        $message = $rule->message('field');
        $expectedMessage = $requiredRule->message('field');

        $this->assertEquals($expectedMessage, $message);
    }

    public function test_returns_custom_message_when_set(): void
    {
        $condition = fn($value, $allData) => true;
        $rule = new When($condition, new Required());

        $customMessage = 'Custom conditional message';
        $rule->withMessage($customMessage);

        $message = $rule->message('field');
        $this->assertEquals($customMessage, $message);
    }

    public function test_with_message_returns_same_instance(): void
    {
        $condition = fn($value, $allData) => true;
        $rule = new When($condition, new Required());

        $result = $rule->withMessage('Custom message');

        $this->assertSame($rule, $result);
    }

    public function test_condition_receives_both_parameters(): void
    {
        $receivedValue = null;
        $receivedAllData = null;

        $condition = function ($value, $allData) use (&$receivedValue, &$receivedAllData) {
            $receivedValue = $value;
            $receivedAllData = $allData;
            return false;
        };

        $rule = new When($condition, new Required());

        $testValue = 'test-value';
        $testData = ['key' => 'value'];

        $rule->validate($testValue, $testData);

        $this->assertEquals($testValue, $receivedValue);
        $this->assertEquals($testData, $receivedAllData);
    }

    public function test_nested_conditional_validation(): void
    {
        // Nested When rules
        $innerCondition = fn($value, $allData) => ($allData['level'] ?? 0) > 2;
        $innerRule = new When($innerCondition, new Min(10));

        $outerCondition = fn($value, $allData) => 'advanced' === $allData['type'];
        $outerRule = new When($outerCondition, $innerRule);

        // Both conditions true
        $advancedHighLevel = ['type' => 'advanced', 'level' => 3];
        $this->assertTrue($outerRule->validate('long enough text', $advancedHighLevel));
        $this->assertFalse($outerRule->validate('short', $advancedHighLevel));

        // Outer true, inner false
        $advancedLowLevel = ['type' => 'advanced', 'level' => 1];
        $this->assertTrue($outerRule->validate('short', $advancedLowLevel));

        // Outer false
        $basicData = ['type' => 'basic', 'level' => 3];
        $this->assertTrue($outerRule->validate('short', $basicData));
    }

    public function test_rule_implements_validation_rule_interface(): void
    {
        $condition = fn($value, $allData) => true;
        $rule = new When($condition, new Required());

        $this->assertInstanceOf(\Ninja\Granite\Validation\ValidationRule::class, $rule);
    }

    public function test_extends_abstract_rule(): void
    {
        $condition = fn($value, $allData) => true;
        $rule = new When($condition, new Required());

        $this->assertInstanceOf(\Ninja\Granite\Validation\Rules\AbstractRule::class, $rule);
    }

    public function test_realistic_subscription_scenario(): void
    {
        // Real-world scenario: API key required for paid plans
        $condition = fn($value, $allData) =>
            isset($allData['plan']) &&
            in_array($allData['plan'], ['pro', 'enterprise']);

        $rule = new When($condition, new Required());

        // Free plan - API key not required
        $freeData = ['plan' => 'free'];
        $this->assertTrue($rule->validate(null, $freeData));
        $this->assertTrue($rule->validate('', $freeData));

        // Pro plan - API key required
        $proData = ['plan' => 'pro'];
        $this->assertTrue($rule->validate('api-key-123', $proData));
        $this->assertFalse($rule->validate(null, $proData));

        // Enterprise plan - API key required
        $enterpriseData = ['plan' => 'enterprise'];
        $this->assertTrue($rule->validate('enterprise-key-456', $enterpriseData));
        $this->assertFalse($rule->validate(null, $enterpriseData));
    }

    public function test_performance_with_complex_conditions(): void
    {
        $condition = function ($value, $allData) {
            // Simulate complex condition evaluation
            $checks = [
                isset($allData['user_type']),
                isset($allData['permissions']),
                is_array($allData['permissions'] ?? null),
                in_array('admin', $allData['permissions'] ?? []),
                ($allData['experience_level'] ?? 0) > 5,
            ];

            return array_reduce($checks, fn($carry, $check) => $carry && $check, true);
        };

        $rule = new When($condition, new StringType());

        $testData = [
            'user_type' => 'advanced',
            'permissions' => ['user', 'admin', 'moderator'],
            'experience_level' => 8,
        ];

        $start = microtime(true);

        for ($i = 0; $i < 1000; $i++) {
            $rule->validate('test value', $testData);
        }

        $elapsed = microtime(true) - $start;

        // Should complete 1000 validations in reasonable time (less than 50ms)
        $this->assertLessThan(0.05, $elapsed, "When validation took too long: {$elapsed}s");
    }
}
