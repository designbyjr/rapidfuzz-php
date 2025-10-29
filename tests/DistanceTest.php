<?php

declare(strict_types=1);

namespace RapidFuzz\Tests;

use PHPUnit\Framework\TestCase;
use RapidFuzz\Distance\Hamming;
use RapidFuzz\Distance\JaroWinkler;
use RapidFuzz\Distance\Levenshtein;

final class DistanceTest extends TestCase
{
    public function testLevenshteinDistance(): void
    {
        self::assertSame(3, Levenshtein::distance('kitten', 'sitting'));
        self::assertEqualsWithDelta(57.14285714285714, Levenshtein::ratio('kitten', 'sitting'), 1e-9);

        $ops = Levenshtein::editops('abc', 'yabd');
        self::assertNotEmpty($ops);
        self::assertSame('insert', $ops[0]['op']);
    }

    public function testHammingDistance(): void
    {
        self::assertSame(1, Hamming::distance('abcd', 'abce'));
        self::assertEqualsWithDelta(75.0, Hamming::ratio('abcd', 'abce'), 1e-9);
    }

    public function testJaroWinkler(): void
    {
        $similarity = JaroWinkler::similarity('dixon', 'dicksonx');
        self::assertGreaterThan(0.7, $similarity);
        self::assertLessThan(1.0, JaroWinkler::distance('dixon', 'dicksonx'));
    }
}
