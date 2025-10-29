<?php

declare(strict_types=1);

namespace RapidFuzz\StringMetric;

use RapidFuzz\Distance\JaroWinkler as Distance;

final class JaroWinkler
{
    private function __construct()
    {
    }

    public static function similarity(string $a, string $b, float $prefixScale = 0.1): float
    {
        return Distance::similarity($a, $b, $prefixScale);
    }

    public static function distance(string $a, string $b, float $prefixScale = 0.1): float
    {
        return Distance::distance($a, $b, $prefixScale);
    }

    public static function ratio(string $a, string $b, float $prefixScale = 0.1): float
    {
        return Distance::ratio($a, $b, $prefixScale);
    }
}
