<?php

declare(strict_types=1);

namespace RapidFuzz\Support;

use RapidFuzz\Utils\Processor;

/**
 * Caches processed strings to avoid repeated normalization work during batch scoring.
 */
final class CachedProcessor
{
    /**
     * @var callable
     */
    private $processor;

    /**
     * @var array<string, string>
     */
    private array $cache = [];

    public function __construct(?callable $processor = null)
    {
        $this->processor = $processor ?? [Processor::class, 'defaultProcess'];
    }

    public function process(string $value): string
    {
        if (isset($this->cache[$value])) {
            return $this->cache[$value];
        }

        $result = ($this->processor)($value);
        $this->cache[$value] = $result;

        return $result;
    }

    /**
     * Pre-warms the cache with the provided values.
     *
     * @param iterable<string> $values
     */
    public function warm(iterable $values): void
    {
        foreach ($values as $value) {
            $this->process($value);
        }
    }

    /**
     * @return int
     */
    public function size(): int
    {
        return count($this->cache);
    }
}
