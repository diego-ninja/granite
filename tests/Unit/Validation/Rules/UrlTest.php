<?php

declare(strict_types=1);

namespace Tests\Unit\Validation\Rules;

use Ninja\Granite\Validation\Rules\Url;
use PHPUnit\Framework\Attributes\CoversClass;
use stdClass;
use Tests\Helpers\TestCase;

#[CoversClass(Url::class)] class UrlTest extends TestCase
{
    private Url $rule;

    protected function setUp(): void
    {
        $this->rule = new Url();
        parent::setUp();
    }

    public function test_validates_valid_urls(): void
    {
        $validUrls = [
            'http://example.com',
            'https://example.com',
            'https://www.example.com',
            'http://subdomain.example.com',
            'https://example.com/path',
            'https://example.com/path/to/resource',
            'https://example.com/path?query=value',
            'https://example.com/path?query=value&other=param',
            'https://example.com/path#fragment',
            'https://example.com/path?query=value#fragment',
            'ftp://files.example.com',
            'https://user:pass@example.com',
            'https://example.com:8080',
            'https://example.com:8080/path',
            'http://192.168.1.1',
            'https://example.co.uk',
            'https://xn--fsq.xn--0zwm56d', // IDN domain
        ];

        foreach ($validUrls as $url) {
            $this->assertTrue($this->rule->validate($url), "Failed to validate: {$url}");
        }
    }

    public function test_rejects_invalid_urls(): void
    {
        $invalidUrls = [
            'not-a-url',
            'example.com', // missing protocol
            'www.example.com', // missing protocol
            'http://', // missing domain
            'http:///', // invalid format
            'http:// example.com', // space in URL
            'http://ex ample.com', // space in domain
            '',
            ' ',
            'just text',
        ];

        foreach ($invalidUrls as $url) {
            $this->assertFalse($this->rule->validate($url), "Incorrectly validated: {$url}");
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
        $message = $this->rule->message('website');
        $this->assertEquals('website must be a valid URL', $message);
    }

    public function test_returns_custom_message_when_set(): void
    {
        $customMessage = 'Please enter a valid website URL';
        $this->rule->withMessage($customMessage);

        $message = $this->rule->message('website');
        $this->assertEquals($customMessage, $message);
    }

    public function test_handles_international_domain_names(): void
    {
        // These might pass or fail depending on the specific filter_var implementation
        $internationalUrls = [
            'https://測試.tw',
            'https://тест.рф',
        ];

        foreach ($internationalUrls as $url) {
            // We just ensure it doesn't throw an exception
            $result = $this->rule->validate($url);
            $this->assertIsBool($result);
        }
    }

    public function test_rule_implements_validation_rule_interface(): void
    {
        $this->assertInstanceOf(\Ninja\Granite\Validation\ValidationRule::class, $this->rule);
    }

    public function test_extends_abstract_rule(): void
    {
        $this->assertInstanceOf(\Ninja\Granite\Validation\Rules\AbstractRule::class, $this->rule);
    }
}
