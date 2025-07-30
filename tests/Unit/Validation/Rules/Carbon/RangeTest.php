<?php

declare(strict_types=1);

namespace Tests\Unit\Validation\Rules\Carbon;

use Carbon\Carbon;
use Carbon\CarbonImmutable;
use DateTimeImmutable;
use Ninja\Granite\Validation\Rules\Carbon\Range;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use stdClass;

#[CoversClass(\Ninja\Granite\Validation\Rules\Carbon\Range::class)]
final class RangeTest extends TestCase
{
    /**
     * @return array<string, array{mixed, bool}>
     */
    public static function edgeCaseProvider(): array
    {
        return [
            'empty string' => ['', false],
            'boolean true' => [true, false],
            'boolean false' => [false, false],
            'array' => [[], false],
            'object' => [new stdClass(), false],
        ];
    }
    public function testValidateNullValue(): void
    {
        $rule = new Range();
        $this->assertTrue($rule->validate(null));
    }

    public function testValidateWithinRange(): void
    {
        $min = Carbon::parse('2023-01-01');
        $max = Carbon::parse('2023-12-31');
        $rule = new Range(min: $min, max: $max);

        $validDate = Carbon::parse('2023-06-15');
        $this->assertTrue($rule->validate($validDate));
    }

    public function testValidateBeforeMin(): void
    {
        $min = Carbon::parse('2023-01-01');
        $max = Carbon::parse('2023-12-31');
        $rule = new Range(min: $min, max: $max);

        $invalidDate = Carbon::parse('2022-12-31');
        $this->assertFalse($rule->validate($invalidDate));
    }

    public function testValidateAfterMax(): void
    {
        $min = Carbon::parse('2023-01-01');
        $max = Carbon::parse('2023-12-31');
        $rule = new Range(min: $min, max: $max);

        $invalidDate = Carbon::parse('2024-01-01');
        $this->assertFalse($rule->validate($invalidDate));
    }

    public function testValidateExactMin(): void
    {
        $min = Carbon::parse('2023-01-01 12:00:00');
        $rule = new Range(min: $min);

        $exactDate = Carbon::parse('2023-01-01 12:00:00');
        $this->assertTrue($rule->validate($exactDate));
    }

    public function testValidateExactMax(): void
    {
        $max = Carbon::parse('2023-12-31 12:00:00');
        $rule = new Range(max: $max);

        $exactDate = Carbon::parse('2023-12-31 12:00:00');
        $this->assertTrue($rule->validate($exactDate));
    }

    public function testValidateMinOnly(): void
    {
        $min = Carbon::parse('2023-01-01');
        $rule = new Range(min: $min);

        $validDate = Carbon::parse('2023-06-15');
        $this->assertTrue($rule->validate($validDate));

        $invalidDate = Carbon::parse('2022-12-31');
        $this->assertFalse($rule->validate($invalidDate));
    }

    public function testValidateMaxOnly(): void
    {
        $max = Carbon::parse('2023-12-31');
        $rule = new Range(max: $max);

        $validDate = Carbon::parse('2023-06-15');
        $this->assertTrue($rule->validate($validDate));

        $invalidDate = Carbon::parse('2024-01-01');
        $this->assertFalse($rule->validate($invalidDate));
    }

    public function testValidateWithStringDates(): void
    {
        $rule = new Range(min: '2023-01-01', max: '2023-12-31');

        $validDate = Carbon::parse('2023-06-15');
        $this->assertTrue($rule->validate($validDate));

        $invalidDate = Carbon::parse('2022-12-31');
        $this->assertFalse($rule->validate($invalidDate));
    }

    public function testValidateWithTimezone(): void
    {
        $min = Carbon::parse('2023-01-01', 'America/New_York');
        $max = Carbon::parse('2023-12-31', 'America/New_York');
        $rule = new Range(min: $min, max: $max, timezone: 'America/New_York');

        $validDate = Carbon::parse('2023-06-15', 'America/New_York');
        $this->assertTrue($rule->validate($validDate));
    }

    public function testValidateStringInput(): void
    {
        $rule = new Range(min: '2023-01-01', max: '2023-12-31');

        $this->assertTrue($rule->validate('2023-06-15'));
        $this->assertFalse($rule->validate('2022-12-31'));
        $this->assertFalse($rule->validate('2024-01-01'));
    }

    public function testValidateInvalidDateString(): void
    {
        $rule = new Range(min: '2023-01-01', max: '2023-12-31');

        $this->assertFalse($rule->validate('invalid-date'));
    }

    public function testValidateCarbonImmutable(): void
    {
        $min = CarbonImmutable::parse('2023-01-01');
        $max = CarbonImmutable::parse('2023-12-31');
        $rule = new Range(min: $min, max: $max);

        $validDate = CarbonImmutable::parse('2023-06-15');
        $this->assertTrue($rule->validate($validDate));
    }

    public function testValidateRegularDateTime(): void
    {
        $min = new DateTimeImmutable('2023-01-01');
        $max = new DateTimeImmutable('2023-12-31');
        $rule = new Range(min: $min, max: $max);

        $validDate = new DateTimeImmutable('2023-06-15');
        $this->assertTrue($rule->validate($validDate));
    }

    public function testValidateTimestamp(): void
    {
        $min = Carbon::parse('2023-01-01');
        $max = Carbon::parse('2023-12-31');
        $rule = new Range(min: $min, max: $max);

        $validTimestamp = Carbon::parse('2023-06-15')->getTimestamp();
        $this->assertTrue($rule->validate($validTimestamp));
    }

    public function testDefaultMessageWithBothBounds(): void
    {
        $rule = new Range(min: '2023-01-01', max: '2023-12-31');
        $message = $rule->message('eventDate');

        $this->assertEquals(
            'eventDate must be between 2023-01-01 and 2023-12-31',
            $message,
        );
    }

    public function testDefaultMessageWithMinOnly(): void
    {
        $rule = new Range(min: '2023-01-01');
        $message = $rule->message('eventDate');

        $this->assertEquals(
            'eventDate must be after 2023-01-01',
            $message,
        );
    }

    public function testDefaultMessageWithMaxOnly(): void
    {
        $rule = new Range(max: '2023-12-31');
        $message = $rule->message('eventDate');

        $this->assertEquals(
            'eventDate must be before 2023-12-31',
            $message,
        );
    }

    public function testDefaultMessageWithoutBounds(): void
    {
        $rule = new Range();
        $message = $rule->message('eventDate');

        $this->assertEquals(
            'eventDate must be a valid date',
            $message,
        );
    }

    public function testDefaultMessageWithDateTimeInterface(): void
    {
        $min = new DateTimeImmutable('2023-01-01 10:30:45');
        $max = new DateTimeImmutable('2023-12-31 15:20:10');
        $rule = new Range(min: $min, max: $max);
        $message = $rule->message('eventDate');

        $this->assertEquals(
            'eventDate must be between 2023-01-01 10:30:45 and 2023-12-31 15:20:10',
            $message,
        );
    }

    public function testCustomMessage(): void
    {
        $rule = new Range(min: '2023-01-01', max: '2023-12-31');
        $rule->withMessage('Custom range validation message');

        $this->assertEquals('Custom range validation message', $rule->message('field'));
    }

    public function testValidateWithRelativeStrings(): void
    {
        $rule = new Range(min: 'yesterday', max: 'tomorrow');

        $this->assertTrue($rule->validate('today'));
        $this->assertFalse($rule->validate('last week'));
        $this->assertFalse($rule->validate('next week'));
    }

    /**
     * @dataProvider edgeCaseProvider
     */
    public function testEdgeCases(mixed $input, bool $expected): void
    {
        $rule = new Range(min: '2023-01-01', max: '2023-12-31');
        $this->assertEquals($expected, $rule->validate($input));
    }

    public function testValidateWithMicroseconds(): void
    {
        $min = Carbon::parse('2023-01-01 12:00:00.000000');
        $max = Carbon::parse('2023-01-01 12:00:00.999999');
        $rule = new Range(min: $min, max: $max);

        $validDate = Carbon::parse('2023-01-01 12:00:00.500000');
        $this->assertTrue($rule->validate($validDate));

        $invalidDate = Carbon::parse('2023-01-01 12:00:01.000000');
        $this->assertFalse($rule->validate($invalidDate));
    }

    public function testValidateTimeZoneConversions(): void
    {
        // Create min/max in UTC
        $minUtc = Carbon::parse('2023-01-01 00:00:00', 'UTC');
        $maxUtc = Carbon::parse('2023-01-01 23:59:59', 'UTC');

        $rule = new Range(min: $minUtc, max: $maxUtc);

        // Test with a date in different timezone that should be valid when converted
        $nyDate = Carbon::parse('2023-01-01 12:00:00', 'America/New_York');
        $this->assertTrue($rule->validate($nyDate));
    }

    public function testValidateInvalidRangeBounds(): void
    {
        // Test with invalid string range bounds
        $rule = new Range(min: 'invalid-min-date', max: 'invalid-max-date');
        $validDate = Carbon::parse('2023-06-15');

        // Should validate successfully when range parsing fails
        $this->assertTrue($rule->validate($validDate));
    }
}
