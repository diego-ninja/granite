<?php

namespace Tests\Unit\Mapping\Conventions;

use Ninja\Granite\Mapping\Contracts\NamingConvention;
use Ninja\Granite\Mapping\Conventions\PrefixConvention;
use PHPUnit\Framework\Attributes\CoversClass;
use Tests\Helpers\TestCase;

#[CoversClass(PrefixConvention::class)]
class PrefixConventionTest extends TestCase
{
    private PrefixConvention $convention;

    protected function setUp(): void
    {
        parent::setUp();
        $this->convention = new PrefixConvention();
    }

    public function test_implements_naming_convention_interface(): void
    {
        $this->assertInstanceOf(NamingConvention::class, $this->convention);
    }

    public function test_get_name_returns_prefix(): void
    {
        $result = $this->convention->getName();
        $this->assertEquals('prefix', $result);
    }

    public function test_matches_returns_true_for_getter_methods(): void
    {
        $this->assertTrue($this->convention->matches('getName'));
        $this->assertTrue($this->convention->matches('getEmail'));
        $this->assertTrue($this->convention->matches('getUserId'));
    }

    public function test_matches_returns_true_for_setter_methods(): void
    {
        $this->assertTrue($this->convention->matches('setName'));
        $this->assertTrue($this->convention->matches('setEmail'));
        $this->assertTrue($this->convention->matches('setUserId'));
    }

    public function test_matches_returns_true_for_boolean_methods(): void
    {
        $this->assertTrue($this->convention->matches('isActive'));
        $this->assertTrue($this->convention->matches('hasPermission'));
        $this->assertTrue($this->convention->matches('isValid'));
    }

    public function test_matches_returns_true_for_action_methods(): void
    {
        $this->assertTrue($this->convention->matches('findUser'));
        $this->assertTrue($this->convention->matches('fetchData'));
        $this->assertTrue($this->convention->matches('createRecord'));
        $this->assertTrue($this->convention->matches('updateUser'));
        $this->assertTrue($this->convention->matches('deleteItem'));
    }

    public function test_matches_returns_false_for_non_prefixed_names(): void
    {
        $this->assertFalse($this->convention->matches('name'));
        $this->assertFalse($this->convention->matches('email'));
        $this->assertFalse($this->convention->matches('userId'));
        $this->assertFalse($this->convention->matches('camelCase'));
    }

    public function test_matches_returns_false_for_lowercase_prefixes(): void
    {
        $this->assertFalse($this->convention->matches('getname'));
        $this->assertFalse($this->convention->matches('setname'));
        $this->assertFalse($this->convention->matches('isactive'));
    }

    public function test_normalize_removes_get_prefix(): void
    {
        $result = $this->convention->normalize('getName');
        $this->assertEquals('name', $result);
    }

    public function test_normalize_removes_set_prefix(): void
    {
        $result = $this->convention->normalize('setEmail');
        $this->assertEquals('email', $result);
    }

    public function test_normalize_removes_is_prefix(): void
    {
        $result = $this->convention->normalize('isActive');
        $this->assertEquals('active', $result);
    }

    public function test_normalize_removes_has_prefix(): void
    {
        $result = $this->convention->normalize('hasPermission');
        $this->assertEquals('permission', $result);
    }

    public function test_normalize_handles_complex_names(): void
    {
        $result = $this->convention->normalize('getUserId');
        $this->assertEquals('user id', $result);
    }

    public function test_normalize_returns_original_for_non_prefixed(): void
    {
        $result = $this->convention->normalize('name');
        $this->assertEquals('name', $result);
    }

    public function test_denormalize_adds_get_prefix(): void
    {
        $result = $this->convention->denormalize('name');
        $this->assertEquals('getName', $result);
    }

    public function test_denormalize_handles_multi_word(): void
    {
        $result = $this->convention->denormalize('user id');
        $this->assertEquals('getUserId', $result);
    }

    public function test_calculate_match_confidence_source_with_prefix_destination_without(): void
    {
        $confidence = $this->convention->calculateMatchConfidence('getName', 'name');
        $this->assertEquals(0.9, $confidence);
    }

    public function test_calculate_match_confidence_source_without_prefix_destination_with(): void
    {
        $confidence = $this->convention->calculateMatchConfidence('name', 'getName');
        $this->assertEquals(0.9, $confidence);
    }

    public function test_calculate_match_confidence_both_with_prefix_matching(): void
    {
        $confidence = $this->convention->calculateMatchConfidence('getName', 'fetchName');
        $this->assertEquals(1.0, $confidence);
    }

    public function test_calculate_match_confidence_both_with_prefix_not_matching(): void
    {
        $confidence = $this->convention->calculateMatchConfidence('getName', 'getEmail');
        $this->assertEquals(0.0, $confidence);
    }

    public function test_calculate_match_confidence_neither_with_prefix(): void
    {
        $confidence = $this->convention->calculateMatchConfidence('name', 'email');
        $this->assertEquals(0.0, $confidence);
    }

    public function test_calculate_match_confidence_complex_camel_case(): void
    {
        $confidence = $this->convention->calculateMatchConfidence('getUserId', 'userId');
        $this->assertEquals(0.9, $confidence);
    }

    public function test_calculate_match_confidence_snake_case_destination(): void
    {
        $confidence = $this->convention->calculateMatchConfidence('getUserId', 'user_id');
        $this->assertEquals(0.9, $confidence);
    }

    public function test_normalize_with_all_common_prefixes(): void
    {
        $testCases = [
            'getName' => 'name',
            'setEmail' => 'email',
            'isActive' => 'active',
            'hasPermission' => 'permission',
            'findUser' => 'user',
            'fetchData' => 'data',
            'retrieveRecord' => 'record',
            'updateUser' => 'user',
            'createItem' => 'item',
            'deleteRecord' => 'record',
            'removeEntry' => 'entry',
            'buildQuery' => 'query',
            'parseJson' => 'json',
            'formatDate' => 'date',
            'convertValue' => 'value',
            'validateInput' => 'input',
            'makeRequest' => 'request',
        ];

        foreach ($testCases as $input => $expected) {
            $result = $this->convention->normalize($input);
            $this->assertEquals($expected, $result, "Failed normalizing '{$input}'");
        }
    }

    public function test_matches_with_all_common_prefixes(): void
    {
        $prefixedNames = [
            'getName', 'setEmail', 'isActive', 'hasPermission',
            'findUser', 'fetchData', 'retrieveRecord', 'updateUser',
            'createItem', 'deleteRecord', 'removeEntry', 'buildQuery',
            'parseJson', 'formatDate', 'convertValue', 'validateInput',
            'makeRequest',
        ];

        foreach ($prefixedNames as $name) {
            $this->assertTrue($this->convention->matches($name), "Failed matching '{$name}'");
        }
    }
}
