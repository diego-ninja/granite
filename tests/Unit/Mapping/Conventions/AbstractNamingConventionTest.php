<?php

namespace Tests\Unit\Mapping\Conventions;

use Ninja\Granite\Mapping\Conventions\AbstractNamingConvention;
use Tests\Helpers\TestCase;

class AbstractNamingConventionTest extends TestCase
{
    private TestAbstractNamingConvention $convention;

    protected function setUp(): void
    {
        parent::setUp();
        $this->convention = new TestAbstractNamingConvention();
    }

    public function test_calculate_match_confidence_identical_names(): void
    {
        $confidence = $this->convention->calculateMatchConfidence('userName', 'userName');
        $this->assertEquals(1.0, $confidence);
    }

    public function test_calculate_match_confidence_same_convention(): void
    {
        $this->convention->setMatchesResponse(true);
        $confidence = $this->convention->calculateMatchConfidence('userName', 'userEmail');

        // Should be at least 0.2 for different properties in same convention
        $this->assertGreaterThanOrEqual(0.2, $confidence);
        $this->assertLessThan(1.0, $confidence);
    }

    public function test_calculate_match_confidence_cross_convention(): void
    {
        $this->convention->setMatchesResponse(false);
        $this->convention->setNormalizeResult('user name');

        $confidence = $this->convention->calculateMatchConfidence('user_name', 'userName');

        // Cross-convention should give 0.85 for normalized identical forms
        $this->assertEquals(0.85, $confidence);
    }

    public function test_calculate_match_confidence_semantic_relationship(): void
    {
        $this->convention->setMatchesResponse(false);

        // Test profile/avatar relationship
        $confidence = $this->convention->calculateMatchConfidence('profile', 'avatar');
        $this->assertGreaterThanOrEqual(0.75, $confidence);
    }

    public function test_calculate_match_confidence_user_id_relationship(): void
    {
        $this->convention->setMatchesResponse(false);
        $this->convention->setNormalizeResult('user id');

        // Test user id/id relationship - need to set up proper normalization
        $confidence = $this->convention->calculateMatchConfidence('user id', 'id');
        $this->assertGreaterThanOrEqual(0.2, $confidence);
    }

    public function test_calculate_match_confidence_low_similarity(): void
    {
        $this->convention->setMatchesResponse(false);

        $confidence = $this->convention->calculateMatchConfidence('completelydifferent', 'anothername');
        $this->assertEquals(0.2, $confidence);
    }

    public function test_calculate_same_convention_confidence_identical(): void
    {
        $confidence = $this->convention->calculateSameConventionConfidence('userName', 'userName');
        $this->assertEquals(1.0, $confidence);
    }

    public function test_calculate_same_convention_confidence_normalized_identical(): void
    {
        $this->convention->setNormalizeResult('user name');

        $confidence = $this->convention->calculateSameConventionConfidence('userName', 'user_name');
        $this->assertEquals(0.9, $confidence);
    }

    public function test_apply_semantic_relationship_bonus_profile_avatar(): void
    {
        $bonus = $this->convention->applySemanticRelationshipBonus('profile', 'avatar', 0.5);
        $this->assertGreaterThanOrEqual(0.75, $bonus);
    }

    public function test_apply_semantic_relationship_bonus_no_relationship(): void
    {
        $bonus = $this->convention->applySemanticRelationshipBonus('name', 'age', 0.5);
        $this->assertEquals(0.5, $bonus);
    }

    public function test_calculate_match_confidence_empty_strings(): void
    {
        $confidence = $this->convention->calculateMatchConfidence('', '');
        $this->assertEquals(1.0, $confidence);
    }
}

class TestAbstractNamingConvention extends AbstractNamingConvention
{
    private bool $matchesResponse = false;
    private ?string $normalizeResult = null;

    public function getName(): string
    {
        return 'test';
    }

    public function matches(string $name): bool
    {
        return $this->matchesResponse;
    }

    public function normalize(string $name): string
    {
        return $this->normalizeResult ?? $name;
    }

    public function denormalize(string $normalized): string
    {
        return $normalized;
    }

    public function transform(string $name): string
    {
        return $name;
    }

    public function setMatchesResponse(bool $matches): void
    {
        $this->matchesResponse = $matches;
    }

    public function setNormalizeResult(string $result): void
    {
        $this->normalizeResult = $result;
    }

    // Expose protected methods for testing
    public function calculateSameConventionConfidence(string $sourceName, string $destinationName): float
    {
        return parent::calculateSameConventionConfidence($sourceName, $destinationName);
    }

    public function applySemanticRelationshipBonus(string $source, string $destination, float $baseSimilarity): float
    {
        return parent::applySemanticRelationshipBonus($source, $destination, $baseSimilarity);
    }
}
