<?php

declare(strict_types=1);

namespace RapidFuzz\Process;

use RapidFuzz\Fuzz;
use RapidFuzz\Support\CachedProcessor;
use RapidFuzz\Support\CachedScorer;
use RapidFuzz\Utils\Processor;

final class Extractor
{
    private function __construct()
    {
    }

    /**
     * @param iterable<string> $choices
     * @return list<array{choice: string, score: float, index: int, processed: string}>
     */
    public static function extract(
        string $query,
        iterable $choices,
        callable|CachedScorer|null $scorer = null,
        callable|CachedProcessor|null $processor = null,
        float $scoreCutoff = 0.0,
        int $limit = PHP_INT_MAX
    ): array {
        $results = iterator_to_array(
            self::extractIter($query, $choices, $scorer, $processor, $scoreCutoff),
            false
        );

        if ($limit < count($results)) {
            $results = array_slice($results, 0, $limit);
        }

        return $results;
    }

    /**
     * @param iterable<string> $choices
     * @return \Generator<int, array{choice: string, score: float, index: int, processed: string}>
     */
    public static function extractIter(
        string $query,
        iterable $choices,
        callable|CachedScorer|null $scorer = null,
        callable|CachedProcessor|null $processor = null,
        float $scoreCutoff = 0.0
    ): \Generator {
        $scorer = $scorer ?? [Fuzz::class, 'weightedRatio'];
        $processor = $processor ?? [Processor::class, 'defaultProcess'];

        $cachedProcessor = $processor instanceof CachedProcessor
            ? $processor
            : new CachedProcessor($processor);
        $cachedScorer = $scorer instanceof CachedScorer ? $scorer : new CachedScorer($scorer);

        $processedQuery = $cachedProcessor->process($query);

        $results = [];
        $index = 0;
        foreach ($choices as $choice) {
            $processedChoice = $cachedProcessor->process($choice);
            $score = $cachedScorer->score($processedQuery, $processedChoice);
            if ($score >= $scoreCutoff) {
                $results[] = [
                    'choice' => $choice,
                    'score' => $score,
                    'index' => $index,
                    'processed' => $processedChoice,
                ];
            }
            $index++;
        }

        usort(
            $results,
            static fn (array $left, array $right): int => $right['score'] <=> $left['score']
        );

        foreach ($results as $result) {
            yield $result;
        }
    }

    /**
     * @param iterable<string> $choices
     * @return array{0: string, 1: float, 2: int}
     */
    public static function extractOne(
        string $query,
        iterable $choices,
        callable|CachedScorer|null $scorer = null,
        callable|CachedProcessor|null $processor = null,
        float $scoreCutoff = 0.0
    ): array {
        $bestChoice = '';
        $bestScore = $scoreCutoff;
        $bestIndex = -1;

        foreach (self::extractIter($query, $choices, $scorer, $processor, $scoreCutoff) as $result) {
            if ($result['score'] > $bestScore) {
                $bestScore = $result['score'];
                $bestChoice = $result['choice'];
                $bestIndex = $result['index'];
            }
        }

        return [$bestChoice, $bestScore, $bestIndex];
    }
}
