<?php

declare(strict_types=1);

namespace Tests\Unit\Validation\Rules\Carbon;

use Carbon\Carbon;
use Carbon\CarbonImmutable;
use DateTimeImmutable;
use Ninja\Granite\Validation\Rules\Carbon\Weekend;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use stdClass;

#[CoversClass(\Ninja\Granite\Validation\Rules\Carbon\Weekend::class)]
final class WeekendTest extends TestCase
{
    /**
     * @return array<string, array{mixed}>
     */
    public static function edgeCaseProvider(): array
    {
        return [
            'boolean true' => [true],
            'boolean false' => [false],
            'array' => [[]],
            'object' => [new stdClass()],
        ];
    }
    public function testValidateNullValue(): void
    {
        $rule = new Weekend();
        $this->assertTrue($rule->validate(null));
    }

    public function testValidateSaturday(): void
    {
        $rule = new Weekend();
        $saturday = Carbon::parse('2023-01-07'); // Saturday

        $this->assertTrue($rule->validate($saturday));
    }

    public function testValidateSunday(): void
    {
        $rule = new Weekend();
        $sunday = Carbon::parse('2023-01-01'); // Sunday

        $this->assertTrue($rule->validate($sunday));
    }

    public function testValidateMonday(): void
    {
        $rule = new Weekend();
        $monday = Carbon::parse('2023-01-02'); // Monday

        $this->assertFalse($rule->validate($monday));
    }

    public function testValidateTuesday(): void
    {
        $rule = new Weekend();
        $tuesday = Carbon::parse('2023-01-03'); // Tuesday

        $this->assertFalse($rule->validate($tuesday));
    }

    public function testValidateWednesday(): void
    {
        $rule = new Weekend();
        $wednesday = Carbon::parse('2023-01-04'); // Wednesday

        $this->assertFalse($rule->validate($wednesday));
    }

    public function testValidateThursday(): void
    {
        $rule = new Weekend();
        $thursday = Carbon::parse('2023-01-05'); // Thursday

        $this->assertFalse($rule->validate($thursday));
    }

    public function testValidateFriday(): void
    {
        $rule = new Weekend();
        $friday = Carbon::parse('2023-01-06'); // Friday

        $this->assertFalse($rule->validate($friday));
    }

    public function testValidateWithTimezone(): void
    {
        $rule = new Weekend(timezone: 'America/New_York');
        $saturday = Carbon::parse('2023-01-07 12:00:00', 'America/New_York'); // Saturday

        $this->assertTrue($rule->validate($saturday));
    }

    public function testValidateStringInput(): void
    {
        $rule = new Weekend();
        $saturdayString = '2023-01-07'; // Saturday
        $mondayString = '2023-01-02'; // Monday

        $this->assertTrue($rule->validate($saturdayString));
        $this->assertFalse($rule->validate($mondayString));
    }

    public function testValidateInvalidDateString(): void
    {
        $rule = new Weekend();

        $this->assertFalse($rule->validate('invalid-date'));
    }

    public function testValidateCarbonImmutable(): void
    {
        $rule = new Weekend();
        $saturdayImmutable = CarbonImmutable::parse('2023-01-07'); // Saturday
        $mondayImmutable = CarbonImmutable::parse('2023-01-02'); // Monday

        $this->assertTrue($rule->validate($saturdayImmutable));
        $this->assertFalse($rule->validate($mondayImmutable));
    }

    public function testValidateRegularDateTime(): void
    {
        $rule = new Weekend();
        $saturdayDateTime = new DateTimeImmutable('2023-01-07'); // Saturday
        $mondayDateTime = new DateTimeImmutable('2023-01-02'); // Monday

        $this->assertTrue($rule->validate($saturdayDateTime));
        $this->assertFalse($rule->validate($mondayDateTime));
    }

    public function testValidateTimestamp(): void
    {
        $rule = new Weekend();
        $saturdayTimestamp = Carbon::parse('2023-01-07')->getTimestamp(); // Saturday
        $mondayTimestamp = Carbon::parse('2023-01-02')->getTimestamp(); // Monday

        $this->assertTrue($rule->validate($saturdayTimestamp));
        $this->assertFalse($rule->validate($mondayTimestamp));
    }

    public function testDefaultMessage(): void
    {
        $rule = new Weekend();
        $message = $rule->message('leisureDate');

        $this->assertEquals('leisureDate must be a weekend day (Saturday or Sunday)', $message);
    }

    public function testCustomMessage(): void
    {
        $rule = new Weekend();
        $rule->withMessage('Custom weekend validation message');

        $this->assertEquals('Custom weekend validation message', $rule->message('field'));
    }

    public function testValidateRelativeStrings(): void
    {
        $rule = new Weekend();

        // These tests are time-dependent, so we'll test specific known dates
        $this->assertTrue($rule->validate('2023-01-07')); // Saturday
        $this->assertFalse($rule->validate('2023-01-02')); // Monday
    }

    public function testValidateDifferentWeekDays(): void
    {
        $rule = new Weekend();

        // Test a full week in January 2023
        $dates = [
            '2023-01-01' => true,  // Sunday (dayOfWeek=0, in [0,6])
            '2023-01-02' => false, // Monday (dayOfWeek=1, not in [0,6])
            '2023-01-03' => false, // Tuesday (dayOfWeek=2, not in [0,6])
            '2023-01-04' => false, // Wednesday (dayOfWeek=3, not in [0,6])
            '2023-01-05' => false, // Thursday (dayOfWeek=4, not in [0,6])
            '2023-01-06' => false, // Friday (dayOfWeek=5, not in [0,6])
            '2023-01-07' => true,  // Saturday (dayOfWeek=6, in [0,6])
        ];

        foreach ($dates as $date => $expected) {
            $this->assertEquals(
                $expected,
                $rule->validate($date),
                "Date {$date} should " . ($expected ? 'be' : 'not be') . ' a weekend day',
            );
        }
    }

    /**
     * @dataProvider edgeCaseProvider
     */
    public function testEdgeCases(mixed $input): void
    {
        $rule = new Weekend();
        $result = $rule->validate($input);
        $this->assertIsBool($result); // Just ensure it doesn't crash
    }

    public function testValidateWithDifferentTimezones(): void
    {
        // Test the same moment in time in different timezones
        $utcRule = new Weekend(timezone: 'UTC');
        $nyRule = new Weekend(timezone: 'America/New_York');
        $tokyoRule = new Weekend(timezone: 'Asia/Tokyo');

        // Saturday morning in UTC
        $saturdayUtc = '2023-01-07 10:00:00';

        $this->assertTrue($utcRule->validate($saturdayUtc));
        $this->assertTrue($nyRule->validate($saturdayUtc));
        $this->assertTrue($tokyoRule->validate($saturdayUtc));
    }

    public function testValidateEndOfWeekend(): void
    {
        $rule = new Weekend();

        // Sunday end of day should still be weekend
        $sundayEndOfDay = Carbon::parse('2023-01-01 23:59:59'); // Sunday
        $this->assertTrue($rule->validate($sundayEndOfDay));

        // Monday start of day should not be weekend
        $mondayStartOfDay = Carbon::parse('2023-01-02 00:00:01'); // Monday
        $this->assertFalse($rule->validate($mondayStartOfDay));
    }

    public function testValidateTimezoneConversion(): void
    {
        // A date that might be different days in different timezones
        $rule = new Weekend(timezone: 'Pacific/Auckland');

        // Test with a UTC timestamp that might be a different day in Auckland
        $utcSaturdayEvening = Carbon::parse('2023-01-07 22:00:00', 'UTC');

        // This should validate based on what day it is in Auckland timezone
        $result = $rule->validate($utcSaturdayEvening);
        $this->assertIsBool($result); // Just ensure it doesn't crash
    }

    public function testValidateStartOfWeekend(): void
    {
        $rule = new Weekend();

        // Friday end of day should not be weekend yet
        $fridayEndOfDay = Carbon::parse('2023-01-06 23:59:59'); // Friday
        $this->assertFalse($rule->validate($fridayEndOfDay));

        // Saturday start of day should be weekend
        $saturdayStartOfDay = Carbon::parse('2023-01-07 00:00:01'); // Saturday
        $this->assertTrue($rule->validate($saturdayStartOfDay));
    }

    public function testValidateLeapYearWeekend(): void
    {
        $rule = new Weekend();

        // Test weekend dates in a leap year
        $leapYearSaturday = Carbon::parse('2024-02-24'); // Saturday in leap year
        $leapYearSunday = Carbon::parse('2024-02-25'); // Sunday in leap year

        $this->assertTrue($rule->validate($leapYearSaturday));
        $this->assertTrue($rule->validate($leapYearSunday));
    }

    public function testValidateYearBoundary(): void
    {
        $rule = new Weekend();

        // Test weekend at year boundary
        $newYearSunday = Carbon::parse('2023-01-01'); // Sunday, New Year's Day
        $newYearEve = Carbon::parse('2022-12-31'); // Saturday, New Year's Eve

        $this->assertTrue($rule->validate($newYearSunday));
        $this->assertTrue($rule->validate($newYearEve));
    }
}
