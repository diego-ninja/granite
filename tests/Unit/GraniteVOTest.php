<?php

declare(strict_types=1);

namespace Tests\Unit;

use DateTimeImmutable;
use Ninja\Granite\GraniteVO;
use Ninja\Granite\Serialization\Attributes\Hidden;
use Ninja\Granite\Serialization\Attributes\SerializedName;
use PHPUnit\Framework\Attributes\CoversClass;
use Tests\Helpers\TestCase;

#[CoversClass(GraniteVO::class)]
final class GraniteVOTest extends TestCase
{
    public function test_equals_compares_hidden_raw_state(): void
    {
        $occurredAt = new DateTimeImmutable('2024-01-15 10:00:00.123456+00:00');
        $left = new RawStateGraniteVO('Alice', 'first-secret', $occurredAt);
        $right = new RawStateGraniteVO('Alice', 'second-secret', $occurredAt);

        $this->assertSame($left->array(), $right->array());
        $this->assertFalse($left->equals($right));
    }

    public function test_equals_compares_raw_datetime_microseconds(): void
    {
        $left = new RawStateGraniteVO(
            'Alice',
            'secret',
            new DateTimeImmutable('2024-01-15 10:00:00.123456+00:00'),
        );
        $right = new RawStateGraniteVO(
            'Alice',
            'secret',
            new DateTimeImmutable('2024-01-15 10:00:00.123457+00:00'),
        );

        $this->assertSame($left->array(), $right->array());
        $this->assertFalse($left->equals($right));
    }

    public function test_equals_keeps_legacy_serialized_array_compatibility(): void
    {
        $value = new RawStateGraniteVO(
            'Alice',
            'secret',
            new DateTimeImmutable('2024-01-15 10:00:00.123456+00:00'),
        );

        $this->assertTrue($value->equals($value->array()));
    }
}

final readonly class RawStateGraniteVO extends GraniteVO
{
    public function __construct(
        #[SerializedName('display_name')]
        public string $name,
        #[Hidden]
        public string $secret,
        public DateTimeImmutable $occurredAt,
    ) {}
}
