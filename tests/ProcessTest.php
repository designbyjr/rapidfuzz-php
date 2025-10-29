<?php

declare(strict_types=1);

namespace RapidFuzz\Tests;

use PHPUnit\Framework\TestCase;
use RapidFuzz\Fuzz;
use RapidFuzz\Process\Batch;
use RapidFuzz\Process\Extractor;
use RapidFuzz\Process\Pairwise;
use RapidFuzz\Process\Vectorized;
use RapidFuzz\Support\CachedProcessor;
use RapidFuzz\Support\CachedScorer;

final class ProcessTest extends TestCase
{
    public function testExtractorWithCustomScorer(): void
    {
        $choices = ['rapid fuzz', 'rapidz fuzz', 'completely different'];
        $scorer = static fn (string $left, string $right): float => Fuzz::ratio($left, $right);

        [$choice, $score, $index] = Extractor::extractOne('rapid fuzz', $choices, $scorer);
        self::assertSame('rapid fuzz', $choice);
        self::assertSame(100.0, $score);
        self::assertSame(0, $index);
    }

    public function testBatchScoringSortsByScore(): void
    {
        $choices = ['rapid fuzz', 'fast library', 'fuzzy search'];
        $results = Batch::score('rapid fuzz', $choices, [Fuzz::class, 'weightedRatio']);

        self::assertCount(3, $results);
        self::assertSame('rapid fuzz', $results[0]['choice']);
        self::assertGreaterThanOrEqual($results[1]['score'], $results[0]['score']);
        self::assertSame(0, $results[0]['index']);
        self::assertNotEmpty($results[0]['processed']);
    }

    public function testExtractorIter(): void
    {
        $choices = ['rapid fuzz', 'fast library', 'fuzzy search'];
        $matches = iterator_to_array(Extractor::extractIter('rapid fuzz', $choices), false);

        self::assertSame('rapid fuzz', $matches[0]['choice']);
        self::assertSame(0, $matches[0]['index']);
    }

    public function testPairwiseMatrix(): void
    {
        $left = ['rapid fuzz', 'php'];
        $right = ['rapid fuzz', 'python'];

        $matrix = Pairwise::scoreMatrix($left, $right, [Fuzz::class, 'ratio']);

        self::assertSame(100.0, $matrix[0][0]);
        self::assertIsFloat($matrix[1][1]);
    }

    public function testPairwiseSparseMatrix(): void
    {
        $left = ['rapid fuzz', 'php'];
        $right = ['rapid fuzz', 'python'];

        $entries = Pairwise::scoreMatrixSparse($left, $right, [Fuzz::class, 'ratio'], null, 80.0);

        self::assertNotEmpty($entries);
        self::assertSame(0, $entries[0]['left']);
        self::assertSame(0, $entries[0]['right']);
    }

    public function testVectorizedMatrixUsesSplFixedArray(): void
    {
        $matrix = Vectorized::scoreMatrix(['rapid fuzz'], ['rapid fuzz', 'rapid fuzz library']);

        self::assertInstanceOf(\SplFixedArray::class, $matrix);
        self::assertInstanceOf(\SplFixedArray::class, $matrix[0]);
        self::assertSame(100.0, $matrix[0][0]);
    }

    public function testVectorizedSparseMatrix(): void
    {
        $entries = Vectorized::scoreMatrixSparse(['rapid fuzz'], ['rapid fuzz', 'rapid fuzz library'], null, null, 80.0);

        self::assertNotEmpty($entries);
        self::assertSame(0, $entries[0]['query']);
        self::assertArrayHasKey('score', $entries[0]);
    }

    public function testCachingUtilities(): void
    {
        $processor = new CachedProcessor();
        $processor->process('Rapid Fuzz');
        $processor->process('Rapid Fuzz');
        self::assertSame(1, $processor->size());

        $scorer = new CachedScorer([Fuzz::class, 'ratio']);
        $scorer->score('rapid fuzz', 'rapid fuzz');
        $scorer->score('rapid fuzz', 'rapid fuzz');
        self::assertSame(1, $scorer->size());
    }
}
