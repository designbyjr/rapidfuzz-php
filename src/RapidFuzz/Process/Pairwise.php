<?php

declare(strict_types=1);

namespace RapidFuzz\Process;

use RapidFuzz\Fuzz;
use RapidFuzz\Support\CachedProcessor;
use RapidFuzz\Support\CachedScorer;
use RapidFuzz\Utils\Processor;

final class Pairwise
{
    private function __construct()
    {
    }

    /**
     * @param iterable<string> $left
     * @param iterable<string> $right
     * @return list<list<float>>
     */
    public static function scoreMatrix(
        iterable $left,
        iterable $right,
        callable|CachedScorer|null $scorer = null,
        callable|CachedProcessor|null $processor = null
    ): array {
        $leftValues = self::materialize($left);
        $rightValues = self::materialize($right);

        $processor = $processor ?? [Processor::class, 'defaultProcess'];
        $cachedProcessor = $processor instanceof CachedProcessor
            ? $processor
            : new CachedProcessor($processor);
        $cachedScorer = $scorer instanceof CachedScorer ? $scorer : new CachedScorer($scorer ?? [Fuzz::class, 'ratio']);

        $cachedProcessor->warm(array_merge($leftValues, $rightValues));

        $matrix = [];
        foreach ($leftValues as $leftIndex => $leftValue) {
            $processedLeft = $cachedProcessor->process($leftValue);
            $row = [];
            foreach ($rightValues as $rightValue) {
                $processedRight = $cachedProcessor->process($rightValue);
                $row[] = $cachedScorer->score($processedLeft, $processedRight);
            }
            $matrix[$leftIndex] = $row;
        }

        return $matrix;
    }

    /**
     * @param iterable<string> $left
     * @param iterable<string> $right
     * @return list<array{left: int, right: int, score: float}>
     */
    public static function scoreMatrixSparse(
        iterable $left,
        iterable $right,
        callable|CachedScorer|null $scorer = null,
        callable|CachedProcessor|null $processor = null,
        float $scoreCutoff = 0.0
    ): array {
        $leftValues = self::materialize($left);
        $rightValues = self::materialize($right);

        $processor = $processor ?? [Processor::class, 'defaultProcess'];
        $cachedProcessor = $processor instanceof CachedProcessor
            ? $processor
            : new CachedProcessor($processor);
        $cachedScorer = $scorer instanceof CachedScorer ? $scorer : new CachedScorer($scorer ?? [Fuzz::class, 'ratio']);

        $cachedProcessor->warm(array_merge($leftValues, $rightValues));

        $entries = [];

        foreach ($leftValues as $leftIndex => $leftValue) {
            $processedLeft = $cachedProcessor->process($leftValue);
            foreach ($rightValues as $rightIndex => $rightValue) {
                $processedRight = $cachedProcessor->process($rightValue);
                $score = $cachedScorer->score($processedLeft, $processedRight);
                if ($score >= $scoreCutoff) {
                    $entries[] = [
                        'left' => $leftIndex,
                        'right' => $rightIndex,
                        'score' => $score,
                    ];
                }
            }
        }

        usort(
            $entries,
            static fn (array $a, array $b): int => $b['score'] <=> $a['score']
        );

        return $entries;
    }

    /**
     * @param iterable<string> $values
     * @return list<string>
     */
    private static function materialize(iterable $values): array
    {
        return is_array($values) ? array_values($values) : iterator_to_array($values, false);
    }
}
