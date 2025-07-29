<?php

namespace Ninja\Granite\Mapping\Conventions;

use Ninja\Granite\Mapping\Contracts\NamingConvention;

/**
 * Convention for handling common abbreviations in property names.
 */
class AbbreviationConvention implements NamingConvention
{
    /**
     * @var array<string, string> Mapping of abbreviations to full forms
     */
    private array $abbreviations = [
        'dob' => 'date of birth',
        'id' => 'identifier',
        'desc' => 'description',
        'addr' => 'address',
        'num' => 'number',
        'tel' => 'telephone',
        'qty' => 'quantity',
        'amt' => 'amount',
        'ctx' => 'context',
        'pwd' => 'password',
        'img' => 'image',
        'src' => 'source',
        'dest' => 'destination',
        'msg' => 'message',
        'cfg' => 'configuration',
        'req' => 'request',
        'res' => 'response',
        'tmp' => 'temporary',
        'usr' => 'user',
    ];

    public function getName(): string
    {
        return 'abbreviation';
    }

    public function matches(string $name): bool
    {
        $lowerName = mb_strtolower($name);

        foreach ($this->abbreviations as $abbr => $full) {
            if ($lowerName === $abbr || str_starts_with($lowerName, $abbr . '_') ||
                str_ends_with($lowerName, '_' . $abbr) || str_contains($lowerName, '_' . $abbr . '_')) {
                return true;
            }
        }

        return false;
    }

    public function normalize(string $name): string
    {
        $lowerName = mb_strtolower($name);
        $result = $lowerName;

        // Detect naming convention first
        $convention = $this->detectConvention($name);
        $normalized = $convention ? $convention->normalize($name) : $name;
        $words = explode(' ', $normalized);

        // Expand abbreviations
        for ($i = 0; $i < count($words); $i++) {
            $word = mb_strtolower($words[$i]);
            if (isset($this->abbreviations[$word])) {
                $words[$i] = $this->abbreviations[$word];
            }
        }

        return implode(' ', $words);
    }

    public function denormalize(string $normalized): string
    {
        // For this example, we simply use camelCase as the standard
        $words = explode(' ', $normalized);
        $result = mb_strtolower($words[0]);

        for ($i = 1; $i < count($words); $i++) {
            $result .= ucfirst(mb_strtolower($words[$i]));
        }

        return $result;
    }

    public function calculateMatchConfidence(string $sourceName, string $destinationName): float
    {
        $sourceNormalized = $this->normalize($sourceName);
        $destinationNormalized = $this->normalize($destinationName);

        // If both normalized forms are equal after expanding abbreviations
        if ($sourceNormalized === $destinationNormalized) {
            return 0.8; // Good confidence but not perfect
        }

        // Calculate token-based similarity
        $sourceTokens = explode(' ', $sourceNormalized);
        $destTokens = explode(' ', $destinationNormalized);

        $commonCount = count(array_intersect($sourceTokens, $destTokens));
        $totalCount = count(array_unique(array_merge($sourceTokens, $destTokens)));

        if ($commonCount > 0) {
            return $commonCount / $totalCount * 0.7; // Partial similarity
        }

        return 0.0;
    }

    /**
     * Detects which convention a property name uses.
     */
    private function detectConvention(string $name): ?NamingConvention
    {
        $conventions = [
            new CamelCaseConvention(),
            new PascalCaseConvention(),
            new SnakeCaseConvention(),
            new KebabCaseConvention(),
        ];

        foreach ($conventions as $convention) {
            if ($convention->matches($name)) {
                return $convention;
            }
        }

        return null;
    }
}
