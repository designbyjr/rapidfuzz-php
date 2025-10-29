<?php

declare(strict_types=1);

namespace RapidFuzz\Distance;

use InvalidArgumentException;

final class Hamming
{
    private function __construct()
    {
    }

    public static function distance(string $a, string $b): int
    {
        $aChars = preg_split('//u', $a, -1, PREG_SPLIT_NO_EMPTY) ?: [];
        $bChars = preg_split('//u', $b, -1, PREG_SPLIT_NO_EMPTY) ?: [];

        if (count($aChars) !== count($bChars)) {
            throw new InvalidArgumentException('Hamming distance requires strings of equal length');
        }

        $distance = 0;

        foreach ($aChars as $index => $char) {
            if ($char !== $bChars[$index]) {
                $distance++;
            }
        }

        return $distance;
    }

    public static function normalizedDistance(string $a, string $b): float
    {
        $length = max(1, self::length($a));

        return self::distance($a, $b) / $length;
    }

    public static function similarity(string $a, string $b): int
    {
        return self::length($a) - self::distance($a, $b);
    }

    public static function normalizedSimilarity(string $a, string $b): float
    {
        $length = max(1, self::length($a));

        return 1.0 - (self::distance($a, $b) / $length);
    }

    public static function ratio(string $a, string $b): float
    {
        return self::normalizedSimilarity($a, $b) * 100.0;
    }

    private static function length(string $value): int
    {
        $len = grapheme_strlen($value);
        if ($len === false) {
            return mb_strlen($value, 'UTF-8');
        }

        return $len;
    }
}
