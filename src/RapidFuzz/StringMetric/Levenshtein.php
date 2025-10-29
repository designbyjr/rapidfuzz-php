<?php

declare(strict_types=1);

namespace RapidFuzz\StringMetric;

use RapidFuzz\Distance\Levenshtein as Distance;

final class Levenshtein
{
    private function __construct()
    {
    }

    public static function distance(string $a, string $b): int
    {
        return Distance::distance($a, $b);
    }

    public static function similarity(string $a, string $b): int
    {
        return Distance::similarity($a, $b);
    }

    public static function normalizedDistance(string $a, string $b): float
    {
        return Distance::normalizedDistance($a, $b);
    }

    public static function normalizedSimilarity(string $a, string $b): float
    {
        return Distance::normalizedSimilarity($a, $b);
    }

    public static function ratio(string $a, string $b): float
    {
        return Distance::ratio($a, $b);
    }

    public static function editops(string $a, string $b): array
    {
        return Distance::editops($a, $b);
    }
}
