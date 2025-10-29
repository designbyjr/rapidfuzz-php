<?php

declare(strict_types=1);

namespace RapidFuzz\Distance;

final class JaroWinkler
{
    private const DEFAULT_PREFIX_SCALE = 0.1;

    private function __construct()
    {
    }

    public static function similarity(string $a, string $b, float $prefixScale = self::DEFAULT_PREFIX_SCALE): float
    {
        if ($a === $b) {
            return 1.0;
        }

        $aChars = preg_split('//u', $a, -1, PREG_SPLIT_NO_EMPTY) ?: [];
        $bChars = preg_split('//u', $b, -1, PREG_SPLIT_NO_EMPTY) ?: [];

        $lenA = count($aChars);
        $lenB = count($bChars);

        if ($lenA === 0 || $lenB === 0) {
            return 0.0;
        }

        $matchDistance = intdiv(max($lenA, $lenB), 2) - 1;
        if ($matchDistance < 0) {
            $matchDistance = 0;
        }

        $aMatches = array_fill(0, $lenA, false);
        $bMatches = array_fill(0, $lenB, false);

        $matches = 0;

        for ($i = 0; $i < $lenA; $i++) {
            $start = max(0, $i - $matchDistance);
            $end = min($i + $matchDistance + 1, $lenB);

            for ($j = $start; $j < $end; $j++) {
                if ($bMatches[$j]) {
                    continue;
                }

                if ($aChars[$i] !== $bChars[$j]) {
                    continue;
                }

                $aMatches[$i] = true;
                $bMatches[$j] = true;
                $matches++;
                break;
            }
        }

        if ($matches === 0) {
            return 0.0;
        }

        $k = 0;
        $transpositions = 0;

        for ($i = 0; $i < $lenA; $i++) {
            if (!$aMatches[$i]) {
                continue;
            }

            while (!$bMatches[$k]) {
                $k++;
            }

            if ($aChars[$i] !== $bChars[$k]) {
                $transpositions++;
            }

            $k++;
        }

        $transpositions /= 2.0;

        $jaro = (
            ($matches / $lenA) +
            ($matches / $lenB) +
            (($matches - $transpositions) / $matches)
        ) / 3.0;

        $prefixLength = 0;
        $maxPrefix = 4;
        $limit = min($maxPrefix, min($lenA, $lenB));

        for ($i = 0; $i < $limit; $i++) {
            if ($aChars[$i] === $bChars[$i]) {
                $prefixLength++;
                continue;
            }
            break;
        }

        return $jaro + min($prefixScale, 1.0 / $maxPrefix) * $prefixLength * (1.0 - $jaro);
    }

    public static function distance(string $a, string $b, float $prefixScale = self::DEFAULT_PREFIX_SCALE): float
    {
        return 1.0 - self::similarity($a, $b, $prefixScale);
    }

    public static function ratio(string $a, string $b, float $prefixScale = self::DEFAULT_PREFIX_SCALE): float
    {
        return self::similarity($a, $b, $prefixScale) * 100.0;
    }
}
