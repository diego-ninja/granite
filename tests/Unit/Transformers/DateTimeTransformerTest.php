<?php

// ABOUTME: Verifies strict format parsing for DateTimeTransformer.
// ABOUTME: Ensures existing date objects remain untouched.

declare(strict_types=1);

namespace Tests\Unit\Transformers;

use DateTimeImmutable;
use Ninja\Granite\Transformers\DateTimeTransformer;
use PHPUnit\Framework\TestCase;

final class DateTimeTransformerTest extends TestCase
{
    public function testTransformUsesConfiguredFormat(): void
    {
        $transformer = new DateTimeTransformer('d/m/Y');

        $result = $transformer->transform('31/12/2024');

        $this->assertInstanceOf(DateTimeImmutable::class, $result);
        $this->assertSame('2024-12-31', $result->format('Y-m-d'));
    }

    public function testTransformRejectsInputThatOnlyFreeParserAccepts(): void
    {
        $transformer = new DateTimeTransformer('d/m/Y');

        $this->assertNull($transformer->transform('tomorrow'));
        $this->assertNull($transformer->transform('2024-12-31'));
    }

    public function testTransformPreservesExistingDateTime(): void
    {
        $date = new DateTimeImmutable('2024-12-31T12:00:00+00:00');

        $this->assertSame($date, (new DateTimeTransformer('d/m/Y'))->transform($date));
    }
}
