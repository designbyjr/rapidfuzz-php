<?php

declare(strict_types=1);

namespace RapidFuzz\Process;

use SplFixedArray;
use RapidFuzz\Fuzz;
use RapidFuzz\Support\CachedProcessor;
use RapidFuzz\Support\CachedScorer;
use RapidFuzz\Utils\Processor;

final class Vectorized
{
    private function __construct()
    {
    }

    /**
     * Produces a dense score matrix using SplFixedArray to minimise allocations.
     *
     * @param iterable<string> $queries
     * @param iterable<string> $choices
     * @return SplFixedArray<int, SplFixedArray<int, float>>
     */
    public static function scoreMatrix(
        iterable $queries,
        iterable $choices,
        callable|CachedScorer|null $scorer = null,
        callable|CachedProcessor|null $processor = null
    ): SplFixedArray {
        $queries = is_array($queries) ? array_values($queries) : iterator_to_array($queries, false);
        $choices = is_array($choices) ? array_values($choices) : iterator_to_array($choices, false);

        $processor = $processor ?? [Processor::class, 'defaultProcess'];
        $cachedProcessor = $processor instanceof CachedProcessor
            ? $processor
            : new CachedProcessor($processor);
        $cachedScorer = $scorer instanceof CachedScorer ? $scorer : new CachedScorer($scorer ?? [Fuzz::class, 'ratio']);

        $rowCount = count($queries);
        $columnCount = count($choices);

        $matrix = new SplFixedArray($rowCount);

        for ($rowIndex = 0; $rowIndex < $rowCount; $rowIndex++) {
            $processedQuery = $cachedProcessor->process($queries[$rowIndex]);
            $row = new SplFixedArray($columnCount);
            for ($columnIndex = 0; $columnIndex < $columnCount; $columnIndex++) {
                $processedChoice = $cachedProcessor->process($choices[$columnIndex]);
                $row[$columnIndex] = $cachedScorer->score($processedQuery, $processedChoice);
            }
            $matrix[$rowIndex] = $row;
        }

        return $matrix;
    }

    /**
     * @param iterable<string> $queries
     * @param iterable<string> $choices
     * @return array<int, array{choice: int, query: int, score: float}>
     */
    public static function scoreMatrixSparse(
        iterable $queries,
        iterable $choices,
        callable|CachedScorer|null $scorer = null,
        callable|CachedProcessor|null $processor = null,
        float $scoreCutoff = 0.0
    ): array {
        $queries = is_array($queries) ? array_values($queries) : iterator_to_array($queries, false);
        $choices = is_array($choices) ? array_values($choices) : iterator_to_array($choices, false);

        $processor = $processor ?? [Processor::class, 'defaultProcess'];
        $cachedProcessor = $processor instanceof CachedProcessor
            ? $processor
            : new CachedProcessor($processor);
        $cachedScorer = $scorer instanceof CachedScorer ? $scorer : new CachedScorer($scorer ?? [Fuzz::class, 'ratio']);

        $entries = [];

        foreach ($queries as $queryIndex => $query) {
            $processedQuery = $cachedProcessor->process($query);
            foreach ($choices as $choiceIndex => $choice) {
                $processedChoice = $cachedProcessor->process($choice);
                $score = $cachedScorer->score($processedQuery, $processedChoice);
                if ($score >= $scoreCutoff) {
                    $entries[] = [
                        'query' => $queryIndex,
                        'choice' => $choiceIndex,
                        'score' => $score,
                    ];
                }
            }
        }

        usort(
            $entries,
            static fn (array $left, array $right): int => $right['score'] <=> $left['score']
        );

        return $entries;
    }
}
