<?php

namespace Tests\Unit\Mapping;

use Ninja\Granite\Mapping\BidirectionalTypeMapping;
use Ninja\Granite\Mapping\Contracts\MappingStorage;
use Ninja\Granite\Mapping\PropertyMapping;
use Ninja\Granite\Mapping\TypeMapping;
use PHPUnit\Framework\Attributes\CoversClass;
use RuntimeException;
use Tests\Fixtures\DTOs\SimpleDTO;
use Tests\Fixtures\DTOs\UserDTO;
use Tests\Helpers\TestCase;

#[CoversClass(BidirectionalTypeMapping::class)]
class BidirectionalTypeMappingTest extends TestCase
{
    private MockMappingStorage $mockStorage;
    private BidirectionalTypeMapping $bidirectionalMapping;

    protected function setUp(): void
    {
        parent::setUp();
        $this->mockStorage = new MockMappingStorage();
        $this->bidirectionalMapping = new BidirectionalTypeMapping(
            $this->mockStorage,
            SimpleDTO::class,
            UserDTO::class,
        );
    }

    public function test_constructor_creates_forward_and_reverse_mappings(): void
    {
        $forward = $this->bidirectionalMapping->getForwardMapping();
        $reverse = $this->bidirectionalMapping->getReverseMapping();

        $this->assertInstanceOf(TypeMapping::class, $forward);
        $this->assertInstanceOf(TypeMapping::class, $reverse);
    }

    public function test_get_type_a(): void
    {
        $result = $this->bidirectionalMapping->getTypeA();
        $this->assertEquals(SimpleDTO::class, $result);
    }

    public function test_get_type_b(): void
    {
        $result = $this->bidirectionalMapping->getTypeB();
        $this->assertEquals(UserDTO::class, $result);
    }

    public function test_get_storage(): void
    {
        $result = $this->bidirectionalMapping->getStorage();
        $this->assertSame($this->mockStorage, $result);
    }

    public function test_for_members_configures_property_pairs(): void
    {
        $result = $this->bidirectionalMapping->forMembers('name', 'name');

        $this->assertSame($this->bidirectionalMapping, $result);

        $reverseMappings = $this->bidirectionalMapping->getReverseMemberMappings();
        $this->assertArrayHasKey('name', $reverseMappings);
        $this->assertEquals('name', $reverseMappings['name']);
    }

    public function test_for_member_pairs_configures_multiple_pairs(): void
    {
        $pairs = [
            'name' => 'name',
            'email' => 'email',
            'id' => 'id',
        ];

        $result = $this->bidirectionalMapping->forMemberPairs($pairs);

        $this->assertSame($this->bidirectionalMapping, $result);

        $reverseMappings = $this->bidirectionalMapping->getReverseMemberMappings();
        $this->assertArrayHasKey('name', $reverseMappings);
        $this->assertArrayHasKey('email', $reverseMappings);
        $this->assertArrayHasKey('id', $reverseMappings);
        $this->assertEquals('name', $reverseMappings['name']);
        $this->assertEquals('email', $reverseMappings['email']);
        $this->assertEquals('id', $reverseMappings['id']);
    }

    public function test_for_forward_member_applies_configuration(): void
    {
        $result = $this->bidirectionalMapping->forForwardMember('name', function ($m): void {
            $m->mapFrom('name');
        });

        $this->assertSame($this->bidirectionalMapping, $result);
    }

    public function test_for_reverse_member_applies_configuration(): void
    {
        $result = $this->bidirectionalMapping->forReverseMember('name', function ($m): void {
            $m->mapFrom('name');
        });

        $this->assertSame($this->bidirectionalMapping, $result);
    }

    public function test_seal_applies_bidirectional_mappings(): void
    {
        $this->bidirectionalMapping->forMembers('name', 'name');
        $this->bidirectionalMapping->forMembers('email', 'email');

        $result = $this->bidirectionalMapping->seal();

        $this->assertSame($this->bidirectionalMapping, $result);
    }

    public function test_seal_is_idempotent(): void
    {
        $this->bidirectionalMapping->forMembers('name', 'name');

        $first = $this->bidirectionalMapping->seal();
        $second = $this->bidirectionalMapping->seal();

        $this->assertSame($this->bidirectionalMapping, $first);
        $this->assertSame($this->bidirectionalMapping, $second);
    }

    public function test_for_members_throws_exception_when_sealed(): void
    {
        $this->bidirectionalMapping->seal();

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Cannot modify bidirectional mapping after it has been sealed');

        $this->bidirectionalMapping->forMembers('name', 'name');
    }

    public function test_for_member_pairs_throws_exception_when_sealed(): void
    {
        $this->bidirectionalMapping->seal();

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Cannot modify bidirectional mapping after it has been sealed');

        $this->bidirectionalMapping->forMemberPairs(['name' => 'name']);
    }

    public function test_for_forward_member_throws_exception_when_sealed(): void
    {
        $this->bidirectionalMapping->seal();

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Cannot modify bidirectional mapping after it has been sealed');

        $this->bidirectionalMapping->forForwardMember('name', fn($m) => $m->mapFrom('name'));
    }

    public function test_for_reverse_member_throws_exception_when_sealed(): void
    {
        $this->bidirectionalMapping->seal();

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Cannot modify bidirectional mapping after it has been sealed');

        $this->bidirectionalMapping->forReverseMember('name', fn($m) => $m->mapFrom('name'));
    }

    public function test_complex_bidirectional_mapping_configuration(): void
    {
        $this->bidirectionalMapping
            ->forMembers('name', 'name')
            ->forMembers('email', 'email')
            ->forForwardMember('name', fn($m) => $m->mapFrom('name'))
            ->forReverseMember('name', fn($m) => $m->mapFrom('name'))
            ->seal();

        $reverseMappings = $this->bidirectionalMapping->getReverseMemberMappings();
        $this->assertCount(2, $reverseMappings);
        $this->assertEquals('name', $reverseMappings['name']);
        $this->assertEquals('email', $reverseMappings['email']);
    }

    public function test_get_reverse_member_mappings_empty_by_default(): void
    {
        $result = $this->bidirectionalMapping->getReverseMemberMappings();
        $this->assertIsArray($result);
        $this->assertEmpty($result);
    }
}

class MockMappingStorage implements MappingStorage
{
    private array $mappings = [];

    public function addPropertyMapping(string $sourceType, string $destinationType, string $property, PropertyMapping $mapping): void
    {
        $key = "{$sourceType}::{$destinationType}::{$property}";
        $this->mappings[$key] = $mapping;
    }

    public function getMapping(string $sourceType, string $destinationType, string $property): ?PropertyMapping
    {
        $key = "{$sourceType}::{$destinationType}::{$property}";
        return $this->mappings[$key] ?? null;
    }

    public function getMappingsForTypes(string $sourceType, string $destinationType): array
    {
        $result = [];
        $prefix = "{$sourceType}::{$destinationType}::";

        foreach ($this->mappings as $key => $mapping) {
            if (str_starts_with($key, $prefix)) {
                $property = substr($key, strlen($prefix));
                $result[$property] = $mapping;
            }
        }

        return $result;
    }
}
