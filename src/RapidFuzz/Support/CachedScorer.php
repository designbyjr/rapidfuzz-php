<?php

declare(strict_types=1);

namespace RapidFuzz\Support;

use RapidFuzz\Fuzz;

/**
 * Wraps a scorer callable with memoization to reuse computed scores across batches.
 */
final class CachedScorer
{
    /**
     * @var callable
     */
    private $scorer;

    /**
     * @var array<string, float>
     */
    private array $cache = [];

    public function __construct(?callable $scorer = null)
    {
        $this->scorer = $scorer ?? [Fuzz::class, 'weightedRatio'];
    }

    public function __invoke(string $s1, string $s2): float
    {
        return $this->score($s1, $s2);
    }

    public function score(string $s1, string $s2): float
    {
        $key = $this->pairKey($s1, $s2);

        if (isset($this->cache[$key])) {
            return $this->cache[$key];
        }

        $value = ($this->scorer)($s1, $s2);
        $this->cache[$key] = $value;

        return $value;
    }

    /**
     * @param iterable<string> $left
     * @param iterable<string> $right
     */
    public function warm(iterable $left, iterable $right): void
    {
        foreach ($left as $first) {
            foreach ($right as $second) {
                $this->score($first, $second);
            }
        }
    }

    public function size(): int
    {
        return count($this->cache);
    }

    private function pairKey(string $left, string $right): string
    {
        return $left . "\0" . $right;
    }
}
