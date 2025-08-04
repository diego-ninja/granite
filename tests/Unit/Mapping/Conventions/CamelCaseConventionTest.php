<?php

namespace Tests\Unit\Mapping\Conventions;

use Ninja\Granite\Mapping\Conventions\CamelCaseConvention;
use Tests\Helpers\TestCase;

class CamelCaseConventionTest extends TestCase
{
    private CamelCaseConvention $convention;

    protected function setUp(): void
    {
        parent::setUp();
        $this->convention = new CamelCaseConvention();
    }

    public function test_get_name(): void
    {
        $this->assertEquals('camelCase', $this->convention->getName());
    }

    public function test_matches_camel_case_strings(): void
    {
        $this->assertTrue($this->convention->matches('camelCase'));
        $this->assertTrue($this->convention->matches('firstName'));
        $this->assertTrue($this->convention->matches('userName'));
        $this->assertTrue($this->convention->matches('isActive'));
    }

    public function test_does_not_match_single_word(): void
    {
        // CamelCase requires at least one uppercase letter
        $this->assertFalse($this->convention->matches('user'));
        $this->assertFalse($this->convention->matches('name'));
    }

    public function test_does_not_match_non_camel_case(): void
    {
        $this->assertFalse($this->convention->matches('snake_case'));
        $this->assertFalse($this->convention->matches('kebab-case'));
        $this->assertFalse($this->convention->matches('PascalCase'));
        $this->assertFalse($this->convention->matches('UPPER_SNAKE'));
    }

    public function test_normalize_camel_case(): void
    {
        $this->assertEquals('first name', $this->convention->normalize('firstName'));
        $this->assertEquals('user name', $this->convention->normalize('userName'));
        $this->assertEquals('is active', $this->convention->normalize('isActive'));
    }

    public function test_normalize_single_word(): void
    {
        $this->assertEquals('user', $this->convention->normalize('user'));
        $this->assertEquals('name', $this->convention->normalize('name'));
    }

    public function test_denormalize_to_camel_case(): void
    {
        $this->assertEquals('firstName', $this->convention->denormalize('first name'));
        $this->assertEquals('userName', $this->convention->denormalize('user name'));
        $this->assertEquals('isActive', $this->convention->denormalize('is active'));
    }

    public function test_denormalize_single_word(): void
    {
        $this->assertEquals('user', $this->convention->denormalize('user'));
        $this->assertEquals('name', $this->convention->denormalize('name'));
    }

    public function test_calculate_match_confidence_identical(): void
    {
        $confidence = $this->convention->calculateMatchConfidence('firstName', 'firstName');
        $this->assertEquals(1.0, $confidence);
    }

    public function test_calculate_match_confidence_similar(): void
    {
        $confidence = $this->convention->calculateMatchConfidence('firstName', 'firstTitle');
        $this->assertGreaterThan(0.0, $confidence);
        $this->assertLessThan(1.0, $confidence);
    }

    public function test_calculate_match_confidence_different(): void
    {
        $confidence = $this->convention->calculateMatchConfidence('firstName', 'lastName');
        $this->assertGreaterThanOrEqual(0.0, $confidence);
    }

    public function test_round_trip_normalization(): void
    {
        $original = 'firstName';
        $normalized = $this->convention->normalize($original);
        $denormalized = $this->convention->denormalize($normalized);

        $this->assertEquals($original, $denormalized);
    }

    public function test_handles_empty_strings(): void
    {
        $this->assertFalse($this->convention->matches(''));
        $this->assertEquals('', $this->convention->normalize(''));
        $this->assertEquals('', $this->convention->denormalize(''));
    }

    public function test_does_not_match_single_word_with_numbers(): void
    {
        // These don't have the required uppercase letter
        $this->assertFalse($this->convention->matches('field1'));
        $this->assertFalse($this->convention->matches('item2'));
    }

    public function test_matches_camel_case_with_numbers(): void
    {
        $this->assertTrue($this->convention->matches('fieldName1'));
        $this->assertTrue($this->convention->matches('item2Name'));
    }

    public function test_complex_camel_case(): void
    {
        // These start with uppercase, so they're PascalCase, not camelCase
        $this->assertFalse($this->convention->matches('XMLHttpRequest'));
        $this->assertFalse($this->convention->matches('URLPattern'));

        // These are proper camelCase
        $this->assertTrue($this->convention->matches('xmlHttpRequest'));
        $this->assertTrue($this->convention->matches('urlPattern'));
    }
}
