<?php

namespace Tests\Unit\Mapping\Conventions;

use Ninja\Granite\Mapping\Conventions\AbstractNamingConvention;
use Tests\Helpers\TestCase;

class AbstractNamingConventionDirectTest extends TestCase
{
    private DirectTestNamingConvention $convention;

    protected function setUp(): void
    {
        parent::setUp();
        $this->convention = new DirectTestNamingConvention();
    }

    public function test_calculate_match_confidence_direct_call(): void
    {
        $confidence = $this->convention->calculateMatchConfidence('userName', 'userName');
        $this->assertEquals(1.0, $confidence);
    }

    public function test_calculate_match_confidence_different_names(): void
    {
        $confidence = $this->convention->calculateMatchConfidence('firstName', 'lastName');
        $this->assertGreaterThanOrEqual(0.0, $confidence);
        $this->assertLessThanOrEqual(1.0, $confidence);
    }

    public function test_calculate_match_confidence_semantic_relationships(): void
    {
        // Test email/mail relationship
        $confidence = $this->convention->calculateMatchConfidence('email', 'mail');
        $this->assertGreaterThanOrEqual(0.2, $confidence);

        // Test url/uri relationship
        $confidence = $this->convention->calculateMatchConfidence('url', 'uri');
        $this->assertGreaterThanOrEqual(0.2, $confidence);

        // Test password/pass relationship
        $confidence = $this->convention->calculateMatchConfidence('password', 'pass');
        $this->assertGreaterThanOrEqual(0.2, $confidence);
    }

    public function test_calculate_match_confidence_edge_cases(): void
    {
        // Empty strings
        $confidence = $this->convention->calculateMatchConfidence('', '');
        $this->assertEquals(1.0, $confidence);

        // One empty string
        $confidence = $this->convention->calculateMatchConfidence('name', '');
        $this->assertGreaterThanOrEqual(0.0, $confidence);

        // Very different strings
        $confidence = $this->convention->calculateMatchConfidence('completely', 'different');
        $this->assertGreaterThanOrEqual(0.0, $confidence);
    }

    public function test_all_semantic_relationships(): void
    {
        $relationships = [
            'profile' => 'avatar',
            'image' => 'picture',
            'avatar' => 'photo',
            'url' => 'uri',
            'email' => 'mail',
            'password' => 'pwd',
            'user' => 'username',
            'id' => 'identifier',
        ];

        foreach ($relationships as $source => $target) {
            $confidence = $this->convention->calculateMatchConfidence($source, $target);
            $this->assertGreaterThanOrEqual(0.2, $confidence, "Failed for {$source} -> {$target}");
        }
    }
}

class DirectTestNamingConvention extends AbstractNamingConvention
{
    public function getName(): string
    {
        return 'direct-test';
    }

    public function matches(string $name): bool
    {
        // Simple pattern - contains at least one letter
        return (bool) preg_match('/[a-zA-Z]/', $name);
    }

    public function normalize(string $name): string
    {
        // Simple normalization - convert to lowercase and replace underscores/hyphens with spaces
        return str_replace(['_', '-'], ' ', strtolower($name));
    }

    public function denormalize(string $normalized): string
    {
        // Convert spaces back to underscores
        return str_replace(' ', '_', $normalized);
    }

    public function transform(string $name): string
    {
        return $this->denormalize($this->normalize($name));
    }
}
