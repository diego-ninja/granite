<?php

declare(strict_types=1);

namespace Tests\Unit\Validation\Rules\Carbon;

use Carbon\Carbon;
use Carbon\CarbonImmutable;
use DateTimeImmutable;
use Ninja\Granite\Validation\Rules\Carbon\Future;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use stdClass;

#[CoversClass(\Ninja\Granite\Validation\Rules\Carbon\Future::class)]
final class FutureTest extends TestCase
{
    /**
     * @return array<string, array{mixed, bool}>
     */
    public static function edgeCaseProvider(): array
    {
        return [
            'empty string' => ['', false],
            'zero timestamp' => [0, false],
            'negative timestamp' => [-1, false],
            'boolean true' => [true, false],
            'boolean false' => [false, false],
            'array' => [[], false],
            'object' => [new stdClass(), false],
        ];
    }
    public function testValidateNullValue(): void
    {
        $rule = new Future();
        $this->assertTrue($rule->validate(null));
    }

    public function testValidateFutureDate(): void
    {
        $rule = new Future();
        $futureDate = Carbon::now()->addDays(1);

        $this->assertTrue($rule->validate($futureDate));
    }

    public function testValidatePastDate(): void
    {
        $rule = new Future();
        $pastDate = Carbon::now()->subDays(1);

        $this->assertFalse($rule->validate($pastDate));
    }

    public function testValidateCurrentDate(): void
    {
        $rule = new Future();
        $now = Carbon::now();

        $this->assertFalse($rule->validate($now));
    }

    public function testValidateCurrentDateWithAllowToday(): void
    {
        $rule = new Future(allowToday: true);
        $today = Carbon::now()->startOfDay();

        $this->assertTrue($rule->validate($today));
    }

    public function testValidateTomorrowWithAllowToday(): void
    {
        $rule = new Future(allowToday: true);
        $tomorrow = Carbon::now()->addDay()->startOfDay();

        $this->assertTrue($rule->validate($tomorrow));
    }

    public function testValidateYesterdayWithAllowToday(): void
    {
        $rule = new Future(allowToday: true);
        $yesterday = Carbon::now()->subDay()->startOfDay();

        $this->assertFalse($rule->validate($yesterday));
    }

    public function testValidateWithTimezone(): void
    {
        $rule = new Future(timezone: 'America/New_York');
        $futureDate = Carbon::now('America/New_York')->addDays(1);

        $this->assertTrue($rule->validate($futureDate));
    }

    public function testValidateStringInput(): void
    {
        $rule = new Future();
        $futureDateString = Carbon::now()->addDays(1)->toISOString();

        $this->assertTrue($rule->validate($futureDateString));
    }

    public function testValidateInvalidDateString(): void
    {
        $rule = new Future();

        $this->assertFalse($rule->validate('invalid-date'));
    }

    public function testValidateCarbonImmutable(): void
    {
        $rule = new Future();
        $futureDate = CarbonImmutable::now()->addDays(1);

        $this->assertTrue($rule->validate($futureDate));
    }

    public function testValidateRegularDateTime(): void
    {
        $rule = new Future();
        $futureDate = new DateTimeImmutable('+1 day');

        $this->assertTrue($rule->validate($futureDate));
    }

    public function testValidateTimestamp(): void
    {
        $rule = new Future();
        $futureTimestamp = Carbon::now()->addDays(1)->getTimestamp();

        $this->assertTrue($rule->validate($futureTimestamp));
    }

    public function testValidatePastTimestamp(): void
    {
        $rule = new Future();
        $pastTimestamp = Carbon::now()->subDays(1)->getTimestamp();

        $this->assertFalse($rule->validate($pastTimestamp));
    }

    public function testDefaultMessageWithoutAllowToday(): void
    {
        $rule = new Future();
        $message = $rule->message('eventDate');

        $this->assertEquals('eventDate must be in the future', $message);
    }

    public function testDefaultMessageWithAllowToday(): void
    {
        $rule = new Future(allowToday: true);
        $message = $rule->message('eventDate');

        $this->assertEquals('eventDate must be today or in the future', $message);
    }

    public function testCustomMessage(): void
    {
        $rule = new Future();
        $rule->withMessage('Custom future validation message');

        $this->assertEquals('Custom future validation message', $rule->message('field'));
    }

    public function testValidateRelativeStringFuture(): void
    {
        $rule = new Future();

        $this->assertTrue($rule->validate('tomorrow'));
        $this->assertTrue($rule->validate('+1 day'));
        $this->assertTrue($rule->validate('next week'));
    }

    public function testValidateRelativeStringPast(): void
    {
        $rule = new Future();

        $this->assertFalse($rule->validate('yesterday'));
        $this->assertFalse($rule->validate('-1 day'));
        $this->assertFalse($rule->validate('last week'));
    }

    public function testValidateRelativeStringToday(): void
    {
        $rule = new Future();
        $ruleWithToday = new Future(allowToday: true);

        $this->assertFalse($rule->validate('today'));
        $this->assertTrue($ruleWithToday->validate('today'));
    }

    public function testValidateWithDifferentTimezones(): void
    {
        // Create a date that might be future in one timezone but past in another
        $utcRule = new Future(timezone: 'UTC');
        $tokyoRule = new Future(timezone: 'Asia/Tokyo');

        // A date that is tomorrow in UTC
        $tomorrowUtc = Carbon::now('UTC')->addDay()->format('Y-m-d H:i:s');

        $this->assertTrue($utcRule->validate($tomorrowUtc));
        $this->assertTrue($tokyoRule->validate($tomorrowUtc));
    }

    /**
     * @dataProvider edgeCaseProvider
     */
    public function testEdgeCases(mixed $input, bool $expected): void
    {
        $rule = new Future();
        $this->assertEquals($expected, $rule->validate($input));
    }

    public function testValidateEndOfDay(): void
    {
        $rule = new Future(allowToday: true);

        // Test end of today
        $endOfToday = Carbon::now()->endOfDay();
        $this->assertTrue($rule->validate($endOfToday));

        // Test start of today
        $startOfToday = Carbon::now()->startOfDay();
        $this->assertTrue($rule->validate($startOfToday));
    }

    public function testValidateMillisecondPrecision(): void
    {
        $rule = new Future();

        // Create a time just a few milliseconds in the future
        $veryNearFuture = Carbon::now()->addMilliseconds(100);
        $this->assertTrue($rule->validate($veryNearFuture));

        // Create a time just a few milliseconds in the past
        $veryNearPast = Carbon::now()->subMilliseconds(100);
        $this->assertFalse($rule->validate($veryNearPast));
    }
}
