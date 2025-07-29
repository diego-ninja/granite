<?php

// tests/Unit/Validation/Rules/EmailTest.php

declare(strict_types=1);

namespace Tests\Unit\Validation\Rules;

use Ninja\Granite\Validation\Rules\Email;
use PHPUnit\Framework\Attributes\CoversClass;
use stdClass;
use Tests\Helpers\TestCase;

#[CoversClass(Email::class)] class EmailTest extends TestCase
{
    private Email $rule;

    protected function setUp(): void
    {
        $this->rule = new Email();
        parent::setUp();
    }

    public function test_validates_valid_email_addresses(): void
    {
        $validEmails = [
            'test@example.com',
            'user.name@domain.org',
            'firstname+lastname@company.co.uk',
            'email@subdomain.example.com',
            'firstname_lastname@example.com',
            'email@example-one.com',
            '1234567890@example.com',
            'email@example.name',
            'email@example.museum',
            '_______@example.com',
            'test.email.with+symbol@example.com',
        ];

        foreach ($validEmails as $email) {
            $this->assertTrue($this->rule->validate($email), "Failed to validate: {$email}");
        }
    }

    public function test_rejects_invalid_email_addresses(): void
    {
        $invalidEmails = [
            'plainaddress',
            '@missingdomain.com',
            'missing-at-sign.com',
            'missing@.com',
            'missing@domain',
            'spaces in@email.com',
            'email@',
            '.email@domain.com',
            'email.@domain.com',
            'email..double.dot@domain.com',
            'email@domain..com',
            'email@domain.com.',
            '',
            ' ',
            'not-an-email',
            'almost@but@not@valid.com',
        ];

        foreach ($invalidEmails as $email) {
            $this->assertFalse($this->rule->validate($email), "Incorrectly validated: {$email}");
        }
    }

    public function test_validates_null_as_valid(): void
    {
        $this->assertTrue($this->rule->validate(null));
    }

    public function test_rejects_non_string_values(): void
    {
        $this->assertFalse($this->rule->validate(123));
        $this->assertFalse($this->rule->validate(true));
        $this->assertFalse($this->rule->validate([]));
        $this->assertFalse($this->rule->validate(new stdClass()));
    }

    public function test_returns_default_message(): void
    {
        $message = $this->rule->message('email');
        $this->assertEquals('email must be a valid email address', $message);
    }

    public function test_returns_custom_message_when_set(): void
    {
        $customMessage = 'Please enter a valid email address';
        $this->rule->withMessage($customMessage);

        $message = $this->rule->message('email');
        $this->assertEquals($customMessage, $message);
    }
}
