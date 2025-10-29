<?php

declare(strict_types=1);

namespace RapidFuzz;

use RapidFuzz\Distance\Hamming;
use RapidFuzz\Distance\JaroWinkler;
use RapidFuzz\Distance\Levenshtein;
use RapidFuzz\Support\CachedProcessor;
use RapidFuzz\Support\Normalizer;

final class Fuzz
{
    private function __construct()
    {
    }

    public static function ratio(string $s1, string $s2, callable|CachedProcessor|null $processor = null): float
    {
        [$left, $right] = self::applyProcessor($s1, $s2, $processor);

        return Levenshtein::ratio($left, $right);
    }

    public static function qRatio(string $s1, string $s2, callable|CachedProcessor|null $processor = null): float
    {
        return self::ratio($s1, $s2, $processor);
    }

    public static function quickRatio(string $s1, string $s2, callable|CachedProcessor|null $processor = null): float
    {
        [$left, $right] = self::applyProcessor($s1, $s2, $processor);

        return Levenshtein::ratio(
            Normalizer::clean($left),
            Normalizer::clean($right)
        );
    }

    public static function partialRatio(string $s1, string $s2, callable|CachedProcessor|null $processor = null): float
    {
        [$left, $right] = self::applyProcessor($s1, $s2, $processor);

        if ($left === $right) {
            return 100.0;
        }

        [$shorter, $longer] = self::sortByLength($left, $right);
        $shortLen = self::length($shorter);
        $longLen = self::length($longer);

        if ($shortLen === 0) {
            return $longLen === 0 ? 100.0 : 0.0;
        }

        if (mb_strpos($longer, $shorter, 0, 'UTF-8') !== false) {
            return 100.0;
        }

        $window = max($shortLen, (int) round($shortLen * 1.2));
        $best = 0.0;

        for ($start = 0; $start <= $longLen - $shortLen; $start++) {
            $candidateLength = min($window, $longLen - $start);
            $candidate = grapheme_substr($longer, $start, $candidateLength) ?? '';
            $score = self::ratio($shorter, $candidate);
            if ($score > $best) {
                $best = $score;
                if ($best >= 99.5) {
                    break;
                }
            }
        }

        return $best;
    }

    public static function tokenSortRatio(string $s1, string $s2, callable|CachedProcessor|null $processor = null): float
    {
        [$left, $right] = self::applyProcessor($s1, $s2, $processor);

        $a = self::sortTokens(Normalizer::clean($left));
        $b = self::sortTokens(Normalizer::clean($right));

        return self::ratio($a, $b);
    }

    public static function tokenRatio(string $s1, string $s2, callable|CachedProcessor|null $processor = null): float
    {
        [$left, $right] = self::applyProcessor($s1, $s2, $processor);

        $tokens1 = self::tokens(Normalizer::clean($left));
        $tokens2 = self::tokens(Normalizer::clean($right));

        return self::ratio(implode(' ', $tokens1), implode(' ', $tokens2));
    }

    public static function tokenSetRatio(string $s1, string $s2, callable|CachedProcessor|null $processor = null): float
    {
        [$left, $right] = self::applyProcessor($s1, $s2, $processor);

        $tokens1 = self::uniqueTokens(Normalizer::clean($left));
        $tokens2 = self::uniqueTokens(Normalizer::clean($right));

        $intersection = array_values(array_intersect($tokens1, $tokens2));
        $diff1 = array_values(array_diff($tokens1, $tokens2));
        $diff2 = array_values(array_diff($tokens2, $tokens1));

        sort($intersection, SORT_STRING);
        sort($diff1, SORT_STRING);
        sort($diff2, SORT_STRING);

        $sortedIntersection = implode(' ', $intersection);
        $combined1 = trim($sortedIntersection . ' ' . implode(' ', $diff1));
        $combined2 = trim($sortedIntersection . ' ' . implode(' ', $diff2));

        $partial1 = self::ratio($sortedIntersection, $combined1);
        $partial2 = self::ratio($sortedIntersection, $combined2);
        $overall = self::ratio($combined1, $combined2);

        return max($partial1, $partial2, $overall);
    }

    public static function partialTokenSortRatio(string $s1, string $s2, callable|CachedProcessor|null $processor = null): float
    {
        [$left, $right] = self::applyProcessor($s1, $s2, $processor);

        return self::partialRatio(
            self::sortTokens(Normalizer::clean($left)),
            self::sortTokens(Normalizer::clean($right))
        );
    }

    public static function partialTokenSetRatio(string $s1, string $s2, callable|CachedProcessor|null $processor = null): float
    {
        [$left, $right] = self::applyProcessor($s1, $s2, $processor);

        $set1 = implode(' ', self::uniqueTokens(Normalizer::clean($left)));
        $set2 = implode(' ', self::uniqueTokens(Normalizer::clean($right)));

        return self::partialRatio($set1, $set2);
    }

    public static function tokenAbbreviationRatio(string $s1, string $s2, callable|CachedProcessor|null $processor = null): float
    {
        [$left, $right] = self::applyProcessor($s1, $s2, $processor);

        return self::ratio(self::initialism($left), self::initialism($right));
    }

    public static function weightedRatio(string $s1, string $s2, callable|CachedProcessor|null $processor = null): float
    {
        [$left, $right] = self::applyProcessor($s1, $s2, $processor);

        $base = self::ratio($left, $right);
        $quick = self::quickRatio($left, $right) * 0.9;
        $partial = self::partialRatio($left, $right) * 0.9;
        $token = self::tokenRatio($left, $right) * 0.97;
        $tokenSort = self::tokenSortRatio($left, $right) * 0.95;
        $tokenSet = self::tokenSetRatio($left, $right) * 0.95;
        $partialTokenSort = self::partialTokenSortRatio($left, $right) * 0.95;
        $partialTokenSet = self::partialTokenSetRatio($left, $right) * 0.95;
        $initials = self::tokenAbbreviationRatio($left, $right) * 0.9;
        $jaro = JaroWinkler::ratio($left, $right) * 0.9;

        return max(
            $base,
            $quick,
            $partial,
            $token,
            $tokenSort,
            $tokenSet,
            $partialTokenSort,
            $partialTokenSet,
            $initials,
            $jaro
        );
    }

    public static function wRatio(string $s1, string $s2, callable|CachedProcessor|null $processor = null): float
    {
        return self::weightedRatio($s1, $s2, $processor);
    }

    /**
     * @param iterable<string> $choices
     * @return array{0: string, 1: float, 2: int}
     */
    public static function extractOne(string $query, iterable $choices): array
    {
        $bestChoice = '';
        $bestScore = -INF;
        $bestIndex = -1;
        $index = 0;

        foreach ($choices as $choice) {
            $score = self::weightedRatio($query, $choice);
            if ($score > $bestScore) {
                $bestScore = $score;
                $bestChoice = $choice;
                $bestIndex = $index;
            }
            $index++;
        }

        return [$bestChoice, $bestScore, $bestIndex];
    }

    /**
     * @return array{string, string}
     */
    private static function sortByLength(string $a, string $b): array
    {
        return self::length($a) <= self::length($b) ? [$a, $b] : [$b, $a];
    }

    private static function sortTokens(string $value): string
    {
        if ($value === '') {
            return '';
        }

        $tokens = preg_split('/\s+/u', $value) ?: [];
        sort($tokens, SORT_STRING);

        return implode(' ', $tokens);
    }

    /**
     * @return list<string>
     */
    private static function tokens(string $value): array
    {
        if ($value === '') {
            return [];
        }

        $tokens = preg_split('/\s+/u', $value) ?: [];
        sort($tokens, SORT_STRING);

        return array_values($tokens);
    }

    /**
     * @return list<string>
     */
    private static function uniqueTokens(string $value): array
    {
        if ($value === '') {
            return [];
        }

        $tokens = preg_split('/\s+/u', $value) ?: [];
        $tokens = array_values(array_unique($tokens));
        sort($tokens, SORT_STRING);

        return $tokens;
    }

    private static function initialism(string $value): string
    {
        $value = Normalizer::clean($value);
        if ($value === '') {
            return '';
        }

        $tokens = preg_split('/\s+/u', $value) ?: [];
        if (count($tokens) === 1) {
            return $tokens[0];
        }

        $letters = array_map(
            static fn (string $token): string => grapheme_substr($token, 0, 1) ?? '',
            $tokens
        );

        return implode('', $letters);
    }

    private static function length(string $value): int
    {
        $length = grapheme_strlen($value);

        if ($length === false) {
            return mb_strlen($value, 'UTF-8');
        }

        return $length;
    }

    public static function hamming(string $s1, string $s2, callable|CachedProcessor|null $processor = null): float
    {
        [$left, $right] = self::applyProcessor($s1, $s2, $processor);

        return Hamming::ratio($left, $right);
    }

    /**
     * @param callable|CachedProcessor|null $processor
     * @return array{0: string, 1: string}
     */
    private static function applyProcessor(
        string $left,
        string $right,
        callable|CachedProcessor|null $processor
    ): array {
        if ($processor === null) {
            return [$left, $right];
        }

        if ($processor instanceof CachedProcessor) {
            return [
                $processor->process($left),
                $processor->process($right),
            ];
        }

        return [
            $processor($left),
            $processor($right),
        ];
    }
}
