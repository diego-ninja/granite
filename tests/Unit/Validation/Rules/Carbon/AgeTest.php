<?php

declare(strict_types=1);

namespace Tests\Unit\Validation\Rules\Carbon;

use Carbon\Carbon;
use Carbon\CarbonImmutable;
use DateTimeImmutable;
use Ninja\Granite\Validation\Rules\Carbon\Age;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use stdClass;

#[CoversClass(Age::class)]
final class AgeTest extends TestCase
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
        $rule = new Age();
        $this->assertTrue($rule->validate(null));
    }

    public function testValidateValidAge(): void
    {
        $birthDate = Carbon::now()->subYears(25);
        $rule = new Age(minAge: 18, maxAge: 65);

        $this->assertTrue($rule->validate($birthDate));
    }

    public function testValidateMinAgeOnly(): void
    {
        $rule = new Age(minAge: 18);

        // Valid: 25 years old
        $validAge = Carbon::now()->subYears(25);
        $this->assertTrue($rule->validate($validAge));

        // Invalid: 16 years old
        $invalidAge = Carbon::now()->subYears(16);
        $this->assertFalse($rule->validate($invalidAge));
    }

    public function testValidateMaxAgeOnly(): void
    {
        $rule = new Age(maxAge: 65);

        // Valid: 30 years old
        $validAge = Carbon::now()->subYears(30);
        $this->assertTrue($rule->validate($validAge));

        // Invalid: 70 years old
        $invalidAge = Carbon::now()->subYears(70);
        $this->assertFalse($rule->validate($invalidAge));
    }

    public function testValidateAgeTooYoung(): void
    {
        $rule = new Age(minAge: 18, maxAge: 65);
        $tooYoung = Carbon::now()->subYears(16);

        $this->assertFalse($rule->validate($tooYoung));
    }

    public function testValidateAgeTooOld(): void
    {
        $rule = new Age(minAge: 18, maxAge: 65);
        $tooOld = Carbon::now()->subYears(70);

        $this->assertFalse($rule->validate($tooOld));
    }

    public function testValidateExactMinAge(): void
    {
        $rule = new Age(minAge: 18);
        $exactAge = Carbon::now()->subYears(18);

        $this->assertTrue($rule->validate($exactAge));
    }

    public function testValidateExactMaxAge(): void
    {
        $rule = new Age(maxAge: 65, timezone: 'UTC');
        $exactAge = Carbon::now('UTC')->subYears(65); // Ensure it's actually 65 years old

        $this->assertTrue($rule->validate($exactAge));
    }

    public function testValidateWithTimezone(): void
    {
        $rule = new Age(minAge: 18, timezone: 'America/New_York');
        $birthDate = Carbon::now('America/New_York')->subYears(25);

        $this->assertTrue($rule->validate($birthDate));
    }

    public function testValidateStringInput(): void
    {
        $rule = new Age(minAge: 18, maxAge: 65);
        $birthDateString = Carbon::now()->subYears(25)->toISOString();

        $this->assertTrue($rule->validate($birthDateString));
    }

    public function testValidateInvalidDateString(): void
    {
        $rule = new Age(minAge: 18);

        $this->assertFalse($rule->validate('invalid-date'));
    }

    public function testValidateCarbonImmutable(): void
    {
        $rule = new Age(minAge: 18, maxAge: 65);
        $birthDate = CarbonImmutable::now()->subYears(25);

        $this->assertTrue($rule->validate($birthDate));
    }

    public function testValidateRegularDateTime(): void
    {
        $rule = new Age(minAge: 18, maxAge: 65);
        $birthDate = new DateTimeImmutable('-25 years');

        $this->assertTrue($rule->validate($birthDate));
    }

    public function testValidateTimestamp(): void
    {
        $rule = new Age(minAge: 18, maxAge: 65);
        $birthTimestamp = Carbon::now()->subYears(25)->getTimestamp();

        $this->assertTrue($rule->validate($birthTimestamp));
    }

    public function testDefaultMessageWithBothAges(): void
    {
        $rule = new Age(minAge: 18, maxAge: 65);
        $message = $rule->message('birthDate');

        $this->assertEquals(
            'birthDate must represent an age between 18 and 65 years',
            $message,
        );
    }

    public function testDefaultMessageWithMinAgeOnly(): void
    {
        $rule = new Age(minAge: 18);
        $message = $rule->message('birthDate');

        $this->assertEquals(
            'birthDate must represent an age of at least 18 years',
            $message,
        );
    }

    public function testDefaultMessageWithMaxAgeOnly(): void
    {
        $rule = new Age(maxAge: 65);
        $message = $rule->message('birthDate');

        $this->assertEquals(
            'birthDate must represent an age of at most 65 years',
            $message,
        );
    }

    public function testDefaultMessageWithoutAges(): void
    {
        $rule = new Age();
        $message = $rule->message('birthDate');

        $this->assertEquals(
            'birthDate must be a valid birth date',
            $message,
        );
    }

    public function testCustomMessage(): void
    {
        $rule = new Age(minAge: 18, maxAge: 65);
        $rule->withMessage('Custom age validation message');

        $this->assertEquals('Custom age validation message', $rule->message('field'));
    }

    /**
     * @dataProvider edgeCaseProvider
     */
    public function testEdgeCases(mixed $input): void
    {
        $rule = new Age(minAge: 18, maxAge: 65);
        $result = $rule->validate($input);
        $this->assertIsBool($result); // Just ensure it doesn't crash
    }

    public function testValidateWithLeapYearBirthDate(): void
    {
        // Test born on Feb 29, 2000 (leap year), validate on non-leap year
        $rule = new Age(minAge: 18, maxAge: 65);

        // Create a leap year birth date
        $leapYearBirth = Carbon::create(2000, 2, 29);

        // Should handle leap year dates correctly
        $this->assertTrue($rule->validate($leapYearBirth));
    }
}
