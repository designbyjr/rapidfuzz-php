<?php

declare(strict_types=1);

namespace RapidFuzz\Distance;

use InvalidArgumentException;

final class Levenshtein
{
    private function __construct()
    {
    }

    public static function distance(string $a, string $b): int
    {
        if ($a === $b) {
            return 0;
        }

        $aChars = preg_split('//u', $a, -1, PREG_SPLIT_NO_EMPTY) ?: [];
        $bChars = preg_split('//u', $b, -1, PREG_SPLIT_NO_EMPTY) ?: [];

        $lenA = count($aChars);
        $lenB = count($bChars);

        if ($lenA === 0) {
            return $lenB;
        }

        if ($lenB === 0) {
            return $lenA;
        }

        if ($lenA > $lenB) {
            [$aChars, $bChars, $lenA, $lenB] = [$bChars, $aChars, $lenB, $lenA];
        }

        $previous = range(0, $lenA);

        for ($i = 1; $i <= $lenB; $i++) {
            $current = [$i];
            $bChar = $bChars[$i - 1];

            for ($j = 1; $j <= $lenA; $j++) {
                $cost = $aChars[$j - 1] === $bChar ? 0 : 1;
                $insertion = $current[$j - 1] + 1;
                $deletion = $previous[$j] + 1;
                $substitution = $previous[$j - 1] + $cost;

                $current[$j] = min($insertion, $deletion, $substitution);
            }

            $previous = $current;
        }

        return $previous[$lenA];
    }

    public static function similarity(string $a, string $b): int
    {
        $maxLen = max(self::length($a), self::length($b));

        return $maxLen - self::distance($a, $b);
    }

    public static function normalizedDistance(string $a, string $b): float
    {
        $maxLen = max(self::length($a), self::length($b));
        if ($maxLen === 0) {
            return 0.0;
        }

        return self::distance($a, $b) / $maxLen;
    }

    public static function normalizedSimilarity(string $a, string $b): float
    {
        $maxLen = max(self::length($a), self::length($b));
        if ($maxLen === 0) {
            return 1.0;
        }

        return 1.0 - (self::distance($a, $b) / $maxLen);
    }

    public static function ratio(string $a, string $b): float
    {
        return self::normalizedSimilarity($a, $b) * 100.0;
    }

    public static function editops(string $a, string $b): array
    {
        $aChars = preg_split('//u', $a, -1, PREG_SPLIT_NO_EMPTY) ?: [];
        $bChars = preg_split('//u', $b, -1, PREG_SPLIT_NO_EMPTY) ?: [];

        $lenA = count($aChars);
        $lenB = count($bChars);

        $matrix = array_fill(0, $lenA + 1, array_fill(0, $lenB + 1, 0));

        for ($i = 0; $i <= $lenA; $i++) {
            $matrix[$i][0] = $i;
        }
        for ($j = 0; $j <= $lenB; $j++) {
            $matrix[0][$j] = $j;
        }

        for ($i = 1; $i <= $lenA; $i++) {
            for ($j = 1; $j <= $lenB; $j++) {
                $cost = $aChars[$i - 1] === $bChars[$j - 1] ? 0 : 1;
                $matrix[$i][$j] = min(
                    $matrix[$i - 1][$j] + 1,
                    $matrix[$i][$j - 1] + 1,
                    $matrix[$i - 1][$j - 1] + $cost
                );
            }
        }

        $ops = [];
        $i = $lenA;
        $j = $lenB;

        while ($i > 0 || $j > 0) {
            if ($i > 0 && $matrix[$i][$j] === $matrix[$i - 1][$j] + 1) {
                $ops[] = ['op' => 'delete', 'src_pos' => $i - 1, 'dest_pos' => $j];
                $i--;
                continue;
            }
            if ($j > 0 && $matrix[$i][$j] === $matrix[$i][$j - 1] + 1) {
                $ops[] = ['op' => 'insert', 'src_pos' => $i, 'dest_pos' => $j - 1];
                $j--;
                continue;
            }
            $cost = $aChars[$i - 1] === $bChars[$j - 1] ? 0 : 1;
            if ($matrix[$i][$j] === $matrix[$i - 1][$j - 1] + $cost) {
                if ($cost === 1) {
                    $ops[] = ['op' => 'replace', 'src_pos' => $i - 1, 'dest_pos' => $j - 1];
                }
                $i--;
                $j--;
                continue;
            }

            throw new InvalidArgumentException('Unable to reconstruct edit operations');
        }

        return array_reverse($ops);
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
