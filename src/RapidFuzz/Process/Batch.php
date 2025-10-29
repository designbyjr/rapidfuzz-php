<?php

declare(strict_types=1);

namespace RapidFuzz\Process;

use RapidFuzz\Support\CachedProcessor;
use RapidFuzz\Support\CachedScorer;
use RapidFuzz\Utils\Processor;

final class Batch
{
    private function __construct()
    {
    }

    /**
     * Scores a single query against many choices using optional caching.
     *
     * @param iterable<string> $choices
     * @return list<array{choice: string, score: float, index: int, processed: string}>
     */
    public static function score(
        string $query,
        iterable $choices,
        callable|CachedScorer|null $scorer = null,
        callable|CachedProcessor|null $processor = null,
        float $scoreCutoff = 0.0
    ): array {
        $cachedProcessor = $processor instanceof CachedProcessor
            ? $processor
            : new CachedProcessor($processor ?? [Processor::class, 'defaultProcess']);
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

        return $results;
    }
}
