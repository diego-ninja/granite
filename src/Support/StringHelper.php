<?php

namespace Ninja\Granite\Support;

/**
 * String helper utilities for cross-version PHP compatibility.
 */
final readonly class StringHelper
{
    /**
     * Multibyte-safe trim function.
     * Uses mb_trim() if available (PHP 8.4+), otherwise falls back to trim().
     *
     * @param string $string The string to trim
     * @param string $characters Characters to trim (same as trim() parameter)
     * @return string The trimmed string
     */
    public static function mbTrim(string $string, string $characters = " \n\r\t\v\0"): string
    {
        if (function_exists('mb_trim')) {
            return mb_trim($string, $characters);
        }

        return trim($string, $characters);
    }
}
