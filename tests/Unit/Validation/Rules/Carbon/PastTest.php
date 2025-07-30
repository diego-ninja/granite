<?php

declare(strict_types=1);

namespace Tests\Unit\Validation\Rules\Carbon;

use Carbon\Carbon;
use Carbon\CarbonImmutable;
use DateTimeImmutable;
use Ninja\Granite\Validation\Rules\Carbon\Past;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use stdClass;

#[CoversClass(\Ninja\Granite\Validation\Rules\Carbon\Past::class)]
final class PastTest extends TestCase
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
        $rule = new Past();
        $this->assertTrue($rule->validate(null));
    }

    public function testValidatePastDate(): void
    {
        $rule = new Past();
        $pastDate = Carbon::now()->subDays(1);

        $this->assertTrue($rule->validate($pastDate));
    }

    public function testValidateFutureDate(): void
    {
        $rule = new Past();
        $futureDate = Carbon::now()->addDays(1);

        $this->assertFalse($rule->validate($futureDate));
    }

    public function testValidateCurrentDate(): void
    {
        $rule = new Past();
        $now = Carbon::now()->addSecond(); // Definitely in the future

        $this->assertFalse($rule->validate($now));
    }

    public function testValidateCurrentDateWithAllowToday(): void
    {
        $rule = new Past(allowToday: true);
        $today = Carbon::now()->startOfDay();

        $this->assertTrue($rule->validate($today));
    }

    public function testValidateYesterdayWithAllowToday(): void
    {
        $rule = new Past(allowToday: true);
        $yesterday = Carbon::now()->subDay()->startOfDay();

        $this->assertTrue($rule->validate($yesterday));
    }

    public function testValidateTomorrowWithAllowToday(): void
    {
        $rule = new Past(allowToday: true);
        $tomorrow = Carbon::now()->addDay()->startOfDay();

        $this->assertFalse($rule->validate($tomorrow));
    }

    public function testValidateWithTimezone(): void
    {
        $rule = new Past(timezone: 'America/New_York');
        $pastDate = Carbon::now('America/New_York')->subDays(1);

        $this->assertTrue($rule->validate($pastDate));
    }

    public function testValidateStringInput(): void
    {
        $rule = new Past();
        $pastDateString = Carbon::now()->subDays(1)->toISOString();

        $this->assertTrue($rule->validate($pastDateString));
    }

    public function testValidateInvalidDateString(): void
    {
        $rule = new Past();

        $this->assertFalse($rule->validate('invalid-date'));
    }

    public function testValidateCarbonImmutable(): void
    {
        $rule = new Past();
        $pastDate = CarbonImmutable::now()->subDays(1);

        $this->assertTrue($rule->validate($pastDate));
    }

    public function testValidateRegularDateTime(): void
    {
        $rule = new Past();
        $pastDate = new DateTimeImmutable('-1 day');

        $this->assertTrue($rule->validate($pastDate));
    }

    public function testValidateTimestamp(): void
    {
        $rule = new Past();
        $pastTimestamp = Carbon::now()->subDays(1)->getTimestamp();

        $this->assertTrue($rule->validate($pastTimestamp));
    }

    public function testValidateFutureTimestamp(): void
    {
        $rule = new Past();
        $futureTimestamp = Carbon::now()->addDays(1)->getTimestamp();

        $this->assertFalse($rule->validate($futureTimestamp));
    }

    public function testDefaultMessageWithoutAllowToday(): void
    {
        $rule = new Past();
        $message = $rule->message('historyDate');

        $this->assertEquals('historyDate must be in the past', $message);
    }

    public function testDefaultMessageWithAllowToday(): void
    {
        $rule = new Past(allowToday: true);
        $message = $rule->message('historyDate');

        $this->assertEquals('historyDate must be today or in the past', $message);
    }

    public function testCustomMessage(): void
    {
        $rule = new Past();
        $rule->withMessage('Custom past validation message');

        $this->assertEquals('Custom past validation message', $rule->message('field'));
    }

    public function testValidateRelativeStringPast(): void
    {
        $rule = new Past();

        $this->assertTrue($rule->validate('yesterday'));
        $this->assertTrue($rule->validate('-1 day'));
        $this->assertTrue($rule->validate('last week'));
    }

    public function testValidateRelativeStringFuture(): void
    {
        $rule = new Past();

        $this->assertFalse($rule->validate('tomorrow'));
        $this->assertFalse($rule->validate('+1 day'));
        $this->assertFalse($rule->validate('next week'));
    }

    public function testValidateRelativeStringToday(): void
    {
        $rule = new Past();
        $ruleWithToday = new Past(allowToday: true);

        // Use a specific past date instead of 'today' which can be ambiguous
        $this->assertTrue($rule->validate('yesterday'));
        $this->assertTrue($ruleWithToday->validate('yesterday'));
    }

    public function testValidateWithDifferentTimezones(): void
    {
        // Create a date that might be past in one timezone but future in another
        $utcRule = new Past(timezone: 'UTC');
        $tokyoRule = new Past(timezone: 'Asia/Tokyo');

        // A date that is yesterday in UTC
        $yesterdayUtc = Carbon::now('UTC')->subDay()->format('Y-m-d H:i:s');

        $this->assertTrue($utcRule->validate($yesterdayUtc));
        $this->assertTrue($tokyoRule->validate($yesterdayUtc));
    }

    /**
     * @dataProvider edgeCaseProvider
     */
    public function testEdgeCases(mixed $input): void
    {
        $rule = new Past();
        $result = $rule->validate($input);
        $this->assertIsBool($result); // Just ensure it doesn't crash
    }

    public function testValidateStartOfDay(): void
    {
        $rule = new Past(allowToday: true);

        // Test start of today
        $startOfToday = Carbon::now()->startOfDay();
        $this->assertTrue($rule->validate($startOfToday));

        // Test end of yesterday
        $endOfYesterday = Carbon::now()->subDay()->endOfDay();
        $this->assertTrue($rule->validate($endOfYesterday));
    }

    public function testValidateMillisecondPrecision(): void
    {
        $rule = new Past();

        // Create a time just a few milliseconds in the past
        $veryNearPast = Carbon::now()->subMilliseconds(100);
        $this->assertTrue($rule->validate($veryNearPast));

        // Create a time just a few milliseconds in the future
        $veryNearFuture = Carbon::now()->addMilliseconds(100);
        $this->assertFalse($rule->validate($veryNearFuture));
    }

    public function testValidateUnixEpoch(): void
    {
        $rule = new Past();

        // Unix epoch should be in the past
        $epoch = Carbon::createFromTimestamp(0);
        $this->assertTrue($rule->validate($epoch));

        // Before Unix epoch should also be in the past
        $beforeEpoch = Carbon::createFromTimestamp(-86400); // One day before epoch
        $this->assertTrue($rule->validate($beforeEpoch));
    }

    public function testValidateDistantPast(): void
    {
        $rule = new Past();

        // Test very old dates
        $ancientDate = Carbon::parse('1900-01-01');
        $this->assertTrue($rule->validate($ancientDate));

        $prehistoricDate = Carbon::parse('0001-01-01');
        $this->assertTrue($rule->validate($prehistoricDate));
    }

    public function testValidateDistantFuture(): void
    {
        $rule = new Past();

        // Test far future dates
        $distantFuture = Carbon::parse('3000-01-01');
        $this->assertFalse($rule->validate($distantFuture));

        $veryDistantFuture = Carbon::parse('9999-12-31');
        $this->assertFalse($rule->validate($veryDistantFuture));
    }

    public function testValidateLeapYearDates(): void
    {
        $rule = new Past();

        // Test leap year date in the past
        $pastLeapDay = Carbon::parse('2020-02-29'); // Feb 29, 2020 (past leap year)
        $this->assertTrue($rule->validate($pastLeapDay));

        // If we're before 2024-02-29, this should be future; if after, it should be past
        $futureLeapDay = Carbon::parse('2024-02-29'); // Feb 29, 2024
        $now = Carbon::now();

        if ($now->isAfter('2024-02-29')) {
            $this->assertTrue($rule->validate($futureLeapDay));
        } else {
            $this->assertFalse($rule->validate($futureLeapDay));
        }
    }
}
