<?php

namespace Tests\Unit\Mapping\Conventions;

use Ninja\Granite\Mapping\Contracts\NamingConvention;
use Ninja\Granite\Mapping\Conventions\AbbreviationConvention;
use PHPUnit\Framework\Attributes\CoversClass;
use Tests\Helpers\TestCase;

#[CoversClass(AbbreviationConvention::class)]
class AbbreviationConventionTest extends TestCase
{
    private AbbreviationConvention $convention;

    protected function setUp(): void
    {
        parent::setUp();
        $this->convention = new AbbreviationConvention();
    }

    public function test_implements_naming_convention_interface(): void
    {
        $this->assertInstanceOf(NamingConvention::class, $this->convention);
    }

    public function test_get_name_returns_abbreviation(): void
    {
        $result = $this->convention->getName();
        $this->assertEquals('abbreviation', $result);
    }

    public function test_matches_returns_true_for_known_abbreviations(): void
    {
        $this->assertTrue($this->convention->matches('id'));
        $this->assertTrue($this->convention->matches('desc'));
        $this->assertTrue($this->convention->matches('addr'));
        $this->assertTrue($this->convention->matches('dob'));
        $this->assertTrue($this->convention->matches('qty'));
    }

    public function test_matches_returns_true_for_abbreviations_with_underscores(): void
    {
        $this->assertTrue($this->convention->matches('user_id'));
        $this->assertTrue($this->convention->matches('id_number'));
        $this->assertTrue($this->convention->matches('temp_desc_value'));
        $this->assertTrue($this->convention->matches('product_qty'));
    }

    public function test_matches_returns_false_for_non_abbreviations(): void
    {
        $this->assertFalse($this->convention->matches('name'));
        $this->assertFalse($this->convention->matches('email'));
        $this->assertFalse($this->convention->matches('username'));
        $this->assertFalse($this->convention->matches('fullName'));
    }

    public function test_matches_is_case_insensitive(): void
    {
        $this->assertTrue($this->convention->matches('ID'));
        $this->assertTrue($this->convention->matches('DESC'));
        $this->assertTrue($this->convention->matches('User_ID'));
        $this->assertTrue($this->convention->matches('PRODUCT_QTY'));
    }

    public function test_normalize_expands_abbreviations(): void
    {
        $this->assertEquals('identifier', $this->convention->normalize('id'));
        $this->assertEquals('description', $this->convention->normalize('desc'));
        $this->assertEquals('address', $this->convention->normalize('addr'));
        $this->assertEquals('date of birth', $this->convention->normalize('dob'));
        $this->assertEquals('quantity', $this->convention->normalize('qty'));
    }

    public function test_normalize_handles_camel_case_with_abbreviations(): void
    {
        $result = $this->convention->normalize('userId');
        $this->assertStringContainsString('identifier', $result);
    }

    public function test_normalize_handles_snake_case_with_abbreviations(): void
    {
        $result = $this->convention->normalize('user_id');
        $this->assertStringContainsString('identifier', $result);
    }

    public function test_normalize_handles_multiple_abbreviations(): void
    {
        $result = $this->convention->normalize('user_id_desc');
        $this->assertStringContainsString('identifier', $result);
        $this->assertStringContainsString('description', $result);
    }

    public function test_denormalize_converts_to_camel_case(): void
    {
        $result = $this->convention->denormalize('user identifier');
        $this->assertEquals('userIdentifier', $result);
    }

    public function test_denormalize_handles_single_word(): void
    {
        $result = $this->convention->denormalize('identifier');
        $this->assertEquals('identifier', $result);
    }

    public function test_denormalize_handles_multiple_words(): void
    {
        $result = $this->convention->denormalize('user identifier description');
        $this->assertEquals('userIdentifierDescription', $result);
    }

    public function test_calculate_match_confidence_perfect_match(): void
    {
        $confidence = $this->convention->calculateMatchConfidence('id', 'identifier');
        $this->assertEquals(0.8, $confidence);
    }

    public function test_calculate_match_confidence_partial_match(): void
    {
        $confidence = $this->convention->calculateMatchConfidence('user_id', 'user_name');
        $this->assertGreaterThan(0.0, $confidence);
        $this->assertLessThan(0.8, $confidence);
    }

    public function test_calculate_match_confidence_no_match(): void
    {
        $confidence = $this->convention->calculateMatchConfidence('name', 'email');
        $this->assertEquals(0.0, $confidence);
    }

    public function test_calculate_match_confidence_same_normalization(): void
    {
        $confidence = $this->convention->calculateMatchConfidence('desc', 'description');
        $this->assertEquals(0.8, $confidence);
    }

    public function test_matches_abbreviation_at_beginning(): void
    {
        $this->assertTrue($this->convention->matches('id_number'));
        $this->assertTrue($this->convention->matches('desc_field'));
    }

    public function test_matches_abbreviation_at_end(): void
    {
        $this->assertTrue($this->convention->matches('user_id'));
        $this->assertTrue($this->convention->matches('product_desc'));
    }

    public function test_matches_abbreviation_in_middle(): void
    {
        $this->assertTrue($this->convention->matches('user_id_field'));
        $this->assertTrue($this->convention->matches('temp_desc_value'));
    }

    public function test_normalize_preserves_non_abbreviation_words(): void
    {
        $result = $this->convention->normalize('userName');
        $this->assertStringContainsString('user', $result);
        $this->assertStringContainsString('name', $result);
    }

    public function test_calculate_match_confidence_with_common_tokens(): void
    {
        $confidence = $this->convention->calculateMatchConfidence('user_id_name', 'user_identifier_title');
        $this->assertGreaterThan(0.0, $confidence);
        $this->assertLessThan(1.0, $confidence);
    }
}
