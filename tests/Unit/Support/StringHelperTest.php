<?php

namespace Tests\Unit\Support;

use Ninja\Granite\Support\StringHelper;
use PHPUnit\Framework\Attributes\CoversClass;
use Tests\Helpers\TestCase;

#[CoversClass(StringHelper::class)]
class StringHelperTest extends TestCase
{
    public function test_mb_trim_with_default_characters(): void
    {
        $result = StringHelper::mbTrim("  hello world  ");
        $this->assertSame("hello world", $result);
    }

    public function test_mb_trim_with_custom_characters(): void
    {
        $result = StringHelper::mbTrim("__hello world__", "_");
        $this->assertSame("hello world", $result);
    }

    public function test_mb_trim_with_empty_string(): void
    {
        $result = StringHelper::mbTrim("");
        $this->assertSame("", $result);
    }

    public function test_mb_trim_with_whitespace_only(): void
    {
        $result = StringHelper::mbTrim("   \n\r\t  ");
        $this->assertSame("", $result);
    }

    public function test_mb_trim_with_no_trimming_needed(): void
    {
        $result = StringHelper::mbTrim("hello world");
        $this->assertSame("hello world", $result);
    }

    public function test_mb_trim_with_multibyte_characters(): void
    {
        $result = StringHelper::mbTrim("  héllo wørld  ");
        $this->assertSame("héllo wørld", $result);
    }
}
