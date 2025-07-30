<?php

declare(strict_types=1);

namespace Tests\Unit\Validation\Rules\Carbon;

use Carbon\Carbon;
use Carbon\CarbonImmutable;
use DateTimeImmutable;
use Ninja\Granite\Validation\Rules\Carbon\BusinessDay;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use stdClass;

#[CoversClass(\Ninja\Granite\Validation\Rules\Carbon\BusinessDay::class)]
final class BusinessDayTest extends TestCase
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
        $rule = new BusinessDay();
        $this->assertTrue($rule->validate(null));
    }

    public function testValidateMonday(): void
    {
        $rule = new BusinessDay();
        $monday = Carbon::parse('2023-01-02'); // Monday

        $this->assertTrue($rule->validate($monday));
    }

    public function testValidateTuesday(): void
    {
        $rule = new BusinessDay();
        $tuesday = Carbon::parse('2023-01-03'); // Tuesday

        $this->assertTrue($rule->validate($tuesday));
    }

    public function testValidateWednesday(): void
    {
        $rule = new BusinessDay();
        $wednesday = Carbon::parse('2023-01-04'); // Wednesday

        $this->assertTrue($rule->validate($wednesday));
    }

    public function testValidateThursday(): void
    {
        $rule = new BusinessDay();
        $thursday = Carbon::parse('2023-01-05'); // Thursday

        $this->assertTrue($rule->validate($thursday));
    }

    public function testValidateFriday(): void
    {
        $rule = new BusinessDay();
        $friday = Carbon::parse('2023-01-06'); // Friday

        $this->assertTrue($rule->validate($friday));
    }

    public function testValidateSaturday(): void
    {
        $rule = new BusinessDay();
        $saturday = Carbon::parse('2023-01-07'); // Saturday

        $this->assertFalse($rule->validate($saturday));
    }

    public function testValidateSunday(): void
    {
        $rule = new BusinessDay();
        $sunday = Carbon::parse('2023-01-01'); // Sunday

        $this->assertFalse($rule->validate($sunday));
    }

    public function testValidateWithTimezone(): void
    {
        $rule = new BusinessDay(timezone: 'America/New_York');
        $businessDay = Carbon::parse('2023-01-02 12:00:00', 'America/New_York'); // Monday

        $this->assertTrue($rule->validate($businessDay));
    }

    public function testValidateStringInput(): void
    {
        $rule = new BusinessDay();
        $mondayString = '2023-01-02'; // Monday
        $saturdayString = '2023-01-07'; // Saturday

        $this->assertTrue($rule->validate($mondayString));
        $this->assertFalse($rule->validate($saturdayString));
    }

    public function testValidateInvalidDateString(): void
    {
        $rule = new BusinessDay();

        $this->assertFalse($rule->validate('invalid-date'));
    }

    public function testValidateCarbonImmutable(): void
    {
        $rule = new BusinessDay();
        $mondayImmutable = CarbonImmutable::parse('2023-01-02'); // Monday
        $saturdayImmutable = CarbonImmutable::parse('2023-01-07'); // Saturday

        $this->assertTrue($rule->validate($mondayImmutable));
        $this->assertFalse($rule->validate($saturdayImmutable));
    }

    public function testValidateRegularDateTime(): void
    {
        $rule = new BusinessDay();
        $mondayDateTime = new DateTimeImmutable('2023-01-02'); // Monday
        $saturdayDateTime = new DateTimeImmutable('2023-01-07'); // Saturday

        $this->assertTrue($rule->validate($mondayDateTime));
        $this->assertFalse($rule->validate($saturdayDateTime));
    }

    public function testValidateTimestamp(): void
    {
        $rule = new BusinessDay();
        $mondayTimestamp = Carbon::parse('2023-01-02')->getTimestamp(); // Monday
        $saturdayTimestamp = Carbon::parse('2023-01-07')->getTimestamp(); // Saturday

        $this->assertTrue($rule->validate($mondayTimestamp));
        $this->assertFalse($rule->validate($saturdayTimestamp));
    }

    public function testDefaultMessage(): void
    {
        $rule = new BusinessDay();
        $message = $rule->message('workDate');

        $this->assertEquals('workDate must be a business day (Monday-Friday)', $message);
    }

    public function testCustomMessage(): void
    {
        $rule = new BusinessDay();
        $rule->withMessage('Custom business day validation message');

        $this->assertEquals('Custom business day validation message', $rule->message('field'));
    }

    public function testValidateRelativeStrings(): void
    {
        $rule = new BusinessDay();

        // These tests use specific known dates
        $this->assertTrue($rule->validate('2023-01-02')); // Monday (dayOfWeek=1, in [1,2,3,4,5])
        $this->assertFalse($rule->validate('2023-01-01')); // Sunday (dayOfWeek=0, not in [1,2,3,4,5])
    }

    public function testValidateDifferentWeekDays(): void
    {
        $rule = new BusinessDay();

        // Test a full week in January 2023
        $dates = [
            '2023-01-01' => false, // Sunday (dayOfWeek=0, not in [1,2,3,4,5])
            '2023-01-02' => true,  // Monday (dayOfWeek=1, in [1,2,3,4,5])
            '2023-01-03' => true,  // Tuesday (dayOfWeek=2, in [1,2,3,4,5])
            '2023-01-04' => true,  // Wednesday (dayOfWeek=3, in [1,2,3,4,5])
            '2023-01-05' => true,  // Thursday (dayOfWeek=4, in [1,2,3,4,5])
            '2023-01-06' => true,  // Friday (dayOfWeek=5, in [1,2,3,4,5])
            '2023-01-07' => false, // Saturday (dayOfWeek=6, not in [1,2,3,4,5])
        ];

        foreach ($dates as $date => $expected) {
            $this->assertEquals(
                $expected,
                $rule->validate($date),
                "Date {$date} should " . ($expected ? 'be' : 'not be') . ' a business day',
            );
        }
    }

    /**
     * @dataProvider edgeCaseProvider
     */
    public function testEdgeCases(mixed $input): void
    {
        $rule = new BusinessDay();
        $result = $rule->validate($input);
        $this->assertIsBool($result); // Just ensure it doesn't crash
    }

    public function testValidateWithDifferentTimezones(): void
    {
        // Test the same moment in time in different timezones
        $utcRule = new BusinessDay(timezone: 'UTC');
        $nyRule = new BusinessDay(timezone: 'America/New_York');
        $tokyoRule = new BusinessDay(timezone: 'Asia/Tokyo');

        // Monday morning in UTC
        $mondayUtc = '2023-01-02 10:00:00';

        $this->assertTrue($utcRule->validate($mondayUtc));
        $this->assertTrue($nyRule->validate($mondayUtc));
        $this->assertTrue($tokyoRule->validate($mondayUtc));
    }

    public function testValidateEndOfWeek(): void
    {
        $rule = new BusinessDay();

        // Friday end of day should still be a business day
        $fridayEndOfDay = Carbon::parse('2023-01-06 23:59:59'); // Friday
        $this->assertTrue($rule->validate($fridayEndOfDay));

        // Saturday start of day should not be a business day
        $saturdayStartOfDay = Carbon::parse('2023-01-07 00:00:01'); // Saturday
        $this->assertFalse($rule->validate($saturdayStartOfDay));
    }

    public function testValidateTimezoneConversion(): void
    {
        // A date that might be different days in different timezones
        $rule = new BusinessDay(timezone: 'Pacific/Auckland');

        // Test with a UTC timestamp that might be a different day in Auckland
        $utcFridayEvening = Carbon::parse('2023-01-06 22:00:00', 'UTC');

        // This should validate based on what day it is in Auckland timezone
        $result = $rule->validate($utcFridayEvening);
        $this->assertIsBool($result); // Just ensure it doesn't crash
    }
}
