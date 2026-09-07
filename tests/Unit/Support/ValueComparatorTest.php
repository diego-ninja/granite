<?php

declare(strict_types=1);

namespace Tests\Unit\Support;

use Ninja\Granite\Granite;
use Ninja\Granite\Support\ValueComparator;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(ValueComparator::class)]
final class ValueComparatorTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        MagicStringComparisonValue::$calls = 0;
        MagicSerializationComparisonValue::$calls = 0;
    }

    public function test_compares_nested_arbitrary_objects_structurally(): void
    {
        $left = new ComparisonContainer([
            'nested' => new PrivateComparisonValue('same'),
            'items' => [1, 2, 3],
        ]);
        $equal = new ComparisonContainer([
            'nested' => new PrivateComparisonValue('same'),
            'items' => [1, 2, 3],
        ]);
        $different = new ComparisonContainer([
            'nested' => new PrivateComparisonValue('different'),
            'items' => [1, 2, 3],
        ]);

        $this->assertTrue(ValueComparator::equals($left, $equal));
        $this->assertFalse(ValueComparator::equals($left, $different));
    }

    public function test_does_not_invoke_string_conversion_while_comparing(): void
    {
        $left = new MagicStringComparisonValue('same');
        $right = new MagicStringComparisonValue('same');

        $this->assertTrue(ValueComparator::equals($left, $right));
        $this->assertSame(0, MagicStringComparisonValue::$calls);
    }

    public function test_does_not_invoke_serialization_hooks_while_comparing(): void
    {
        $left = new MagicSerializationComparisonValue('same');
        $right = new MagicSerializationComparisonValue('same');

        $this->assertTrue(ValueComparator::equals($left, $right));
        $this->assertSame(0, MagicSerializationComparisonValue::$calls);
    }

    public function test_compares_equivalent_and_different_cyclic_objects(): void
    {
        $left = CyclicComparisonValue::selfReferential('same');
        $equal = CyclicComparisonValue::selfReferential('same');
        $different = CyclicComparisonValue::selfReferential('different');

        $this->assertTrue(ValueComparator::equals($left, $equal));
        $this->assertFalse(ValueComparator::equals($left, $different));
    }

    public function test_rejects_non_isomorphic_object_cycles(): void
    {
        $selfCycle = CyclicComparisonValue::selfReferential('same');
        $twoNodeCycle = CyclicComparisonValue::twoNodeCycle('same');

        $this->assertFalse(ValueComparator::equals($selfCycle, $twoNodeCycle));
    }

    public function test_compares_distinct_stdclass_instances_structurally(): void
    {
        $left = (object) ['name' => 'same', 'nested' => (object) ['value' => 1]];
        $equal = (object) ['name' => 'same', 'nested' => (object) ['value' => 1]];
        $different = (object) ['name' => 'different', 'nested' => (object) ['value' => 1]];

        $this->assertTrue(ValueComparator::equals($left, $equal));
        $this->assertFalse(ValueComparator::equals($left, $different));
    }

    public function test_compares_recursive_arrays_without_recursing_indefinitely(): void
    {
        $left = ['value' => 'same'];
        $left['self'] = &$left;
        $equal = ['value' => 'same'];
        $equal['self'] = &$equal;
        $different = ['value' => 'different'];
        $different['self'] = &$different;

        $this->assertTrue(ValueComparator::equals($left, $equal));
        $this->assertFalse(ValueComparator::equals($left, $different));
    }

    public function test_compares_cyclic_granite_graphs_with_one_shared_context(): void
    {
        $leftNode = new ComparisonContainer(null);
        $left = new CyclicGraniteComparisonValue($leftNode);
        $leftNode->value = $left;

        $equalNode = new ComparisonContainer(null);
        $equal = new CyclicGraniteComparisonValue($equalNode);
        $equalNode->value = $equal;

        $differentNode = new ComparisonContainer('different');
        $different = new CyclicGraniteComparisonValue($differentNode);

        $this->assertTrue(ValueComparator::equals($left, $equal));
        $this->assertFalse(ValueComparator::equals($left, $different));
    }

    public function test_compares_closures_by_strict_identity(): void
    {
        $shared = static fn(): string => 'same';

        $this->assertTrue(ValueComparator::equals($shared, $shared));
        $this->assertFalse(ValueComparator::equals(
            static fn(): string => 'same',
            static fn(): string => 'same',
        ));
    }

    public function test_compares_resources_by_strict_identity_even_when_nested(): void
    {
        $left = fopen('php://memory', 'r+');
        $right = fopen('php://memory', 'r+');

        $this->assertIsResource($left);
        $this->assertIsResource($right);

        try {
            $this->assertTrue(ValueComparator::equals($left, $left));
            $this->assertFalse(ValueComparator::equals($left, $right));
            $this->assertTrue(ValueComparator::equals(
                new ComparisonContainer($left),
                new ComparisonContainer($left),
            ));
            $this->assertFalse(ValueComparator::equals(
                new ComparisonContainer($left),
                new ComparisonContainer($right),
            ));
        } finally {
            fclose($left);
            fclose($right);
        }
    }
}

final class ComparisonContainer
{
    public function __construct(public mixed $value) {}
}

final class PrivateComparisonValue
{
    public function __construct(private string $value) {}
}

final class MagicStringComparisonValue
{
    public static int $calls = 0;

    public function __construct(public string $value) {}

    public function __toString(): string
    {
        self::$calls++;

        return $this->value;
    }
}

final class MagicSerializationComparisonValue
{
    public static int $calls = 0;

    public function __construct(public string $value) {}

    /** @return array{value: string} */
    public function __serialize(): array
    {
        self::$calls++;

        return ['value' => $this->value];
    }
}

final class CyclicComparisonValue
{
    public ?self $next = null;

    public function __construct(public string $value) {}

    public static function selfReferential(string $value): self
    {
        $object = new self($value);
        $object->next = $object;

        return $object;
    }

    public static function twoNodeCycle(string $value): self
    {
        $first = new self($value);
        $second = new self($value);
        $first->next = $second;
        $second->next = $first;

        return $first;
    }
}

final readonly class CyclicGraniteComparisonValue extends Granite
{
    public function __construct(public ComparisonContainer $node) {}
}
