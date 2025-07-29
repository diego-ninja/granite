<?php

// tests/Unit/Validation/Rules/IpAddressTest.php

declare(strict_types=1);

namespace Tests\Unit\Validation\Rules;

use Ninja\Granite\Validation\Rules\IpAddress;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use stdClass;
use Tests\Helpers\TestCase;

#[CoversClass(IpAddress::class)]
class IpAddressTest extends TestCase
{
    private IpAddress $rule;

    protected function setUp(): void
    {
        $this->rule = new IpAddress();
        parent::setUp();
    }

    public static function validIpAddressesProvider(): array
    {
        return [
            // IPv4 addresses
            'localhost' => ['127.0.0.1'],
            'private class A' => ['10.0.0.1'],
            'private class B' => ['172.16.0.1'],
            'private class C' => ['192.168.1.1'],
            'google dns' => ['8.8.8.8'],
            'cloudflare dns' => ['1.1.1.1'],
            'broadcast' => ['255.255.255.255'],
            'zero address' => ['0.0.0.0'],

            // IPv6 addresses
            'ipv6 localhost' => ['::1'],
            'ipv6 any' => ['::'],
            'ipv6 full' => ['2001:0db8:85a3:0000:0000:8a2e:0370:7334'],
            'ipv6 compressed' => ['2001:db8:85a3::8a2e:370:7334'],
            'ipv6 link local' => ['fe80::1'],
            'ipv6 multicast' => ['ff02::1'],
            'google ipv6 dns' => ['2001:4860:4860::8888'],
            'ipv6 short' => ['2001:db8::1'],
        ];
    }

    public static function invalidIpAddressesProvider(): array
    {
        return [
            // Invalid IPv4
            'out of range octet' => ['256.1.1.1'],
            'negative octet' => ['192.168.-1.1'],
            'missing octet' => ['192.168.1'],
            'extra octet' => ['192.168.1.1.1'],
            'non-numeric' => ['a.b.c.d'],
            'with port' => ['192.168.1.1:80'],
            'with protocol' => ['http://192.168.1.1'],

            // Invalid IPv6
            'invalid hex' => ['2001:0db8:85a3::8a2e:370g:7334'],
            'triple colon' => ['2001:0db8:::8a2e:370:7334'],
            'multiple double colon' => ['2001:db8::8a2e::7334'],
            'too many groups' => ['2001:0db8:85a3:0000:0000:8a2e:0370:7334:extra'],
            'ipv6 with port' => ['[::1]:80'],

            // Non-IP strings
            'domain name' => ['example.com'],
            'empty string' => [''],
            'whitespace' => [' '],
            'text' => ['not-an-ip'],

            // Non-string types
            'integer' => [123],
            'float' => [3.14],
            'boolean true' => [true],
            'boolean false' => [false],
            'array' => [[]],
            'object' => [new stdClass()],
        ];
    }

    public function test_validates_ipv4_addresses(): void
    {
        $validIpv4 = [
            '192.168.1.1',
            '10.0.0.1',
            '172.16.0.1',
            '127.0.0.1',
            '8.8.8.8',
            '255.255.255.255',
            '0.0.0.0',
            '1.1.1.1',
            '208.67.222.222',
        ];

        foreach ($validIpv4 as $ip) {
            $this->assertTrue($this->rule->validate($ip), "Failed to validate IPv4: {$ip}");
        }
    }

    public function test_validates_ipv6_addresses(): void
    {
        $validIpv6 = [
            '2001:0db8:85a3:0000:0000:8a2e:0370:7334',
            '2001:db8:85a3::8a2e:370:7334',
            '::1',
            '::',
            'fe80::1',
            '2001:db8::1',
            'ff02::1',
            '2001:0db8:0000:0042:0000:8a2e:0370:7334',
            '2001:db8:0:42::8a2e:370:7334',
            'fe80::200:f8ff:fe21:67cf',
        ];

        foreach ($validIpv6 as $ip) {
            $this->assertTrue($this->rule->validate($ip), "Failed to validate IPv6: {$ip}");
        }
    }

    public function test_rejects_invalid_ipv4_addresses(): void
    {
        $invalidIpv4 = [
            '256.1.1.1',        // Out of range
            '192.168.1.256',    // Out of range
            '192.168.1',        // Missing octet
            '192.168.1.1.1',    // Extra octet
            '192.168.01.1',     // Leading zero (depends on implementation)
            '192.168.-1.1',     // Negative number
            '192.168.1.1.1',    // Too many octets
            'a.b.c.d',          // Non-numeric
            '192.168.1.',       // Trailing dot
            '.192.168.1.1',     // Leading dot
            '192..168.1.1',     // Double dot
        ];

        foreach ($invalidIpv4 as $ip) {
            $this->assertFalse($this->rule->validate($ip), "Incorrectly validated invalid IPv4: {$ip}");
        }
    }

    public function test_rejects_invalid_ipv6_addresses(): void
    {
        $invalidIpv6 = [
            '2001:0db8:85a3::8a2e:370g:7334',  // Invalid hex character
            '2001:0db8:85a3:::8a2e:370:7334',  // Triple colon
            '2001:0db8:85a3::8a2e::7334',      // Multiple double colons
            '2001:0db8:85a3:0000:0000:8a2e:0370:7334:extra', // Too many groups
            'gggg::1',                          // Invalid hex
            '2001:db8:85a3:0000:0000:8a2e:0370', // Missing group
            '::12345',                          // Group too long
            '2001:db8:85a3:0000:0000:8a2e:0370:7334:',  // Trailing colon
        ];

        foreach ($invalidIpv6 as $ip) {
            $this->assertFalse($this->rule->validate($ip), "Incorrectly validated invalid IPv6: {$ip}");
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
        $this->assertFalse($this->rule->validate(3.14));
    }

    public function test_rejects_empty_and_whitespace(): void
    {
        $this->assertFalse($this->rule->validate(''));
        $this->assertFalse($this->rule->validate(' '));
        $this->assertFalse($this->rule->validate("\t"));
        $this->assertFalse($this->rule->validate("\n"));
        $this->assertFalse($this->rule->validate('   '));
    }

    public function test_rejects_non_ip_strings(): void
    {
        $this->assertFalse($this->rule->validate('not-an-ip'));
        $this->assertFalse($this->rule->validate('example.com'));
        $this->assertFalse($this->rule->validate('192.168.1.1.com'));
        $this->assertFalse($this->rule->validate('http://192.168.1.1'));
        $this->assertFalse($this->rule->validate('192.168.1.1:8080'));
        $this->assertFalse($this->rule->validate('[2001:db8::1]'));
    }

    public function test_validates_localhost_addresses(): void
    {
        $this->assertTrue($this->rule->validate('127.0.0.1'));
        $this->assertTrue($this->rule->validate('::1'));
        $this->assertTrue($this->rule->validate('0.0.0.0'));
    }

    public function test_validates_private_network_addresses(): void
    {
        // Private IPv4 ranges
        $this->assertTrue($this->rule->validate('10.0.0.1'));
        $this->assertTrue($this->rule->validate('172.16.0.1'));
        $this->assertTrue($this->rule->validate('192.168.1.1'));

        // Link-local IPv6
        $this->assertTrue($this->rule->validate('fe80::1'));
    }

    public function test_validates_public_addresses(): void
    {
        $this->assertTrue($this->rule->validate('8.8.8.8'));          // Google DNS
        $this->assertTrue($this->rule->validate('1.1.1.1'));          // Cloudflare DNS
        $this->assertTrue($this->rule->validate('208.67.222.222'));   // OpenDNS
        $this->assertTrue($this->rule->validate('2001:4860:4860::8888')); // Google IPv6 DNS
    }

    public function test_validates_edge_case_addresses(): void
    {
        $this->assertTrue($this->rule->validate('0.0.0.0'));
        $this->assertTrue($this->rule->validate('255.255.255.255'));
        $this->assertTrue($this->rule->validate('::'));
        $this->assertTrue($this->rule->validate('::1'));
    }

    public function test_rejects_addresses_with_ports(): void
    {
        $this->assertFalse($this->rule->validate('192.168.1.1:80'));
        $this->assertFalse($this->rule->validate('127.0.0.1:8080'));
        $this->assertFalse($this->rule->validate('[::1]:80'));
        $this->assertFalse($this->rule->validate('[2001:db8::1]:443'));
    }

    public function test_rejects_addresses_with_protocols(): void
    {
        $this->assertFalse($this->rule->validate('http://192.168.1.1'));
        $this->assertFalse($this->rule->validate('https://127.0.0.1'));
        $this->assertFalse($this->rule->validate('ftp://10.0.0.1'));
        $this->assertFalse($this->rule->validate('tcp://192.168.1.1'));
    }

    public function test_ignores_all_data_parameter(): void
    {
        $allData = ['other_field' => 'value', 'server' => '192.168.1.1'];

        $this->assertTrue($this->rule->validate('127.0.0.1', $allData));
        $this->assertFalse($this->rule->validate('invalid-ip', $allData));
    }

    public function test_returns_default_message(): void
    {
        $message = $this->rule->message('serverIp');
        $this->assertEquals('serverIp must be a valid IP address', $message);
    }

    public function test_returns_default_message_for_different_properties(): void
    {
        $this->assertEquals('ip must be a valid IP address', $this->rule->message('ip'));
        $this->assertEquals('client_ip must be a valid IP address', $this->rule->message('client_ip'));
        $this->assertEquals('remote_addr must be a valid IP address', $this->rule->message('remote_addr'));
    }

    public function test_returns_custom_message_when_set(): void
    {
        $customMessage = 'Please provide a valid IP address';
        $this->rule->withMessage($customMessage);

        $message = $this->rule->message('serverIp');
        $this->assertEquals($customMessage, $message);
    }

    public function test_with_message_returns_same_instance(): void
    {
        $result = $this->rule->withMessage('Custom message');

        $this->assertSame($this->rule, $result);
    }

    #[DataProvider('validIpAddressesProvider')]
    public function test_validates_various_ip_formats(string $ip): void
    {
        $this->assertTrue($this->rule->validate($ip), "Failed to validate IP: {$ip}");
    }

    #[DataProvider('invalidIpAddressesProvider')]
    public function test_rejects_invalid_ip_formats(mixed $value): void
    {
        $this->assertFalse($this->rule->validate($value), "Incorrectly validated invalid IP: " . var_export($value, true));
    }

    public function test_rule_implements_validation_rule_interface(): void
    {
        $this->assertInstanceOf(\Ninja\Granite\Validation\ValidationRule::class, $this->rule);
    }

    public function test_extends_abstract_rule(): void
    {
        $this->assertInstanceOf(\Ninja\Granite\Validation\Rules\AbstractRule::class, $this->rule);
    }

    public function test_validates_compressed_ipv6_formats(): void
    {
        // Test various IPv6 compression scenarios
        $compressedFormats = [
            '2001:db8::1',
            '2001:db8::',
            '::2001:db8:1',
            '::ffff:192.0.2.1', // IPv4-mapped IPv6
            '::ffff:0:192.0.2.1',
        ];

        foreach ($compressedFormats as $ip) {
            $this->assertTrue($this->rule->validate($ip), "Failed to validate compressed IPv6: {$ip}");
        }
    }

    public function test_performance_with_many_addresses(): void
    {
        $addresses = [
            '192.168.1.1',
            '10.0.0.1',
            '2001:db8::1',
            '::1',
            'invalid-ip',
            '256.1.1.1',
        ];

        $start = microtime(true);

        for ($i = 0; $i < 1000; $i++) {
            foreach ($addresses as $address) {
                $this->rule->validate($address);
            }
        }

        $elapsed = microtime(true) - $start;

        // Should complete 6000 validations in reasonable time (less than 100ms)
        $this->assertLessThan(0.1, $elapsed, "IP validation took too long: {$elapsed}s");
    }
}
