<?php

declare(strict_types=1);

namespace RapidFuzz\Tests;

use PHPUnit\Framework\TestCase;
use RapidFuzz\StringMetric\JaroWinkler;
use RapidFuzz\StringMetric\Levenshtein;

final class StringMetricTest extends TestCase
{
    public function testLevenshteinHelpersMirrorDistanceModule(): void
    {
        self::assertSame(1, Levenshtein::distance('abc', 'abd'));
        self::assertEqualsWithDelta(66.66666666666666, Levenshtein::ratio('abc', 'abd'), 1e-9);
        self::assertCount(1, Levenshtein::editops('abc', 'abd'));
    }

    public function testJaroWinklerHelpers(): void
    {
        $ratio = JaroWinkler::ratio('martha', 'marhta');
        self::assertGreaterThan(90.0, $ratio);
        self::assertSame(
            JaroWinkler::distance('martha', 'marhta'),
            1.0 - (JaroWinkler::similarity('martha', 'marhta'))
        );
    }
}
