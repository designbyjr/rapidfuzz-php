# RapidFuzz PHP

A blazing fast fuzzy string matching library for PHP 8.3 inspired by the excellent [RapidFuzz](https://github.com/rapidfuzz/RapidFuzz) project. The goal of this package is to bring production-ready, memory-efficient fuzzy matching primitives to PHP developers with an API that feels familiar to users of the Python ecosystem.

## Highlights

- ✅ **Speed & accuracy** – powered by Unicode-aware dynamic programming plus token, partial, quick, and abbreviation scorers tuned to mirror RapidFuzz's behaviour.
- ✅ **Unicode ready** – normalized matching using `ext-intl` and `ext-mbstring` ensures non-ASCII text behaves as expected.
- ✅ **Scales with your workload** – batch, pairwise, cached, and vectorized helpers unlock the higher-level APIs RapidFuzz users expect.
- ✅ **Production focused** – ships with a Composer manifest, automated testing, and detailed documentation so you can publish to Packagist without additional work.

## Requirements

- PHP 8.3 or later
- `ext-intl` and `ext-mbstring`
- Optional: `ext-parallel` or `amphp/parallel` if you plan to distribute matching across workers (not required for the core library).

Install dependencies via Composer:

```bash
composer install
```

## Getting started

```php
<?php

require __DIR__ . '/vendor/autoload.php';

use RapidFuzz\Fuzz;

$score = Fuzz::ratio('New York Mets', 'New York Jets');
// 77.777...

[$match, $confidence, $index] = Fuzz::extractOne('rapid fuzz', [
    'RapidFuzz PHP',
    'Fast fuzzy library',
    'rapid fuzz toolkit',
]);

printf("Best match: %s (%.2f) at index %d\n", $match, $confidence, $index);
```

## API

### `Fuzz` scorers

| Method | Description |
| --- | --- |
| `Fuzz::ratio` / `Fuzz::qRatio` | Classic Levenshtein similarity between two strings, 0-100 scale. |
| `Fuzz::quickRatio` | Runs `ratio` on normalized text for a fast, allocation-light approximation. |
| `Fuzz::partialRatio` | Finds the best matching substring window between two strings. |
| `Fuzz::tokenRatio` | Tokenizes, sorts, and rejoins tokens before scoring (order insensitive). |
| `Fuzz::tokenSortRatio` | Sorts tokens but preserves multiplicity for word-order agnostic comparisons. |
| `Fuzz::tokenSetRatio` | Splits into shared/unique token groups and scores each combination. |
| `Fuzz::partialTokenSortRatio` / `Fuzz::partialTokenSetRatio` | Partial variants optimised for subsequence matches. |
| `Fuzz::tokenAbbreviationRatio` | Compares the initialisms of both strings (great for acronym matching). |
| `Fuzz::weightedRatio` / `Fuzz::wRatio` | Combines quick, partial, token, abbreviation, and Jaro-Winkler scorers. |
| `Fuzz::hamming` | Computes a Hamming similarity (0-100) for equal-length strings. |
| `Fuzz::extractOne` | Returns `[match, score, index]` for the best candidate in an iterable. |

Every scorer accepts an optional processor callable or `CachedProcessor` instance as the final argument, mirroring the Python API's
`processor=` keyword. This lets you reuse the bundled `Processor::defaultProcess` helper or your own domain-specific normalization.

All scorers accept Unicode strings and leverage the same ICU-backed normalization that RapidFuzz uses via `ext-intl`.

### Distance and string metric modules

RapidFuzz PHP now mirrors the `rapidfuzz.distance` and `rapidfuzz.string_metric` namespaces:

- `RapidFuzz\Distance\Levenshtein` / `RapidFuzz\StringMetric\Levenshtein` expose `distance`, `similarity`, `normalizedSimilarity`, `ratio`, and `editops` helpers.
- `RapidFuzz\Distance\Hamming` provides `distance`, `ratio`, and normalized helpers for equal-length strings.
- `RapidFuzz\Distance\JaroWinkler` and matching string metric wrapper deliver similarity, distance, and ratio calculations.

These modules make it easy to drop the PHP port into code expecting the Python API surface while staying in pure PHP.

## Process helpers

The `RapidFuzz\Process` namespace mirrors the higher-level helpers from RapidFuzz for Python. Everything is scorer-agnostic, so you can bring your own callable for custom domain logic or reuse the bundled scorers.

### `Extractor::extractOne()`, `Extractor::extract()`, and `Extractor::extractIter()`
- Accept optional scorer and processor callables (plain closures or cached helpers).
- Support score cut-offs, result limits, and expose the original index and processed text for each match.
- Provide a generator-based `extractIter()` for streaming through sorted results without allocating large arrays.

```php
use RapidFuzz\Process\Extractor;

[$choice, $score] = Extractor::extractOne(
    'rapid fuzz',
    ['rapid fuzz', 'rapid fuzz toolkit', 'fast fuzzy'],
    [RapidFuzz\Fuzz::class, 'weightedRatio']
);
```

### `Batch::score()`
Runs a single query against a large choice set and returns sorted matches, including the original index and processed representation. The helper automatically memoizes processed strings and scores between iterations to avoid redundant work.

### `Pairwise::scoreMatrix()`
Builds a dense matrix of scores between two lists of strings, reusing caches to keep throughput high when comparing catalogues or datasets.

### `Pairwise::scoreMatrixSparse()`
Produces `[leftIndex, rightIndex, score]` triplets filtered by a score cut-off—ideal for sparse graph or similarity join workloads.

### `Vectorized::scoreMatrix()`
Returns an `SplFixedArray`-backed matrix suitable for downstream numerical work (e.g. exporting to PHP-FFI, machine learning pipelines, or SIMD-aware extensions). It avoids repeated allocations and enables efficient iteration.

### `Vectorized::scoreMatrixSparse()`
Generates sparse triplets for vectorized comparisons while retaining order and score cut-offs.

## Processing, caching & normalization

The utility class `RapidFuzz\Utils\Processor` exposes a `defaultProcess()` helper that applies Unicode normalization, lower-casing, and punctuation trimming using ICU. Pair it with `RapidFuzz\Support\CachedProcessor` to preprocess data once and reuse the cleaned strings for significant memory savings in high-throughput environments. To cache expensive scorer invocations, wrap your callable with `RapidFuzz\Support\CachedScorer`.

```php
use RapidFuzz\Process\Batch;
use RapidFuzz\Support\CachedProcessor;
use RapidFuzz\Support\CachedScorer;

$processor = new CachedProcessor();
$scorer = new CachedScorer([RapidFuzz\Fuzz::class, 'weightedRatio']);

$matches = Batch::score('rapid fuzz', $catalogue, $scorer, $processor);
```

## Performance notes

- The library ships with an optimized Levenshtein implementation that only allocates two rows of the dynamic programming matrix.
- Partial ratios only scan a narrow dynamic window of the longer string to keep memory consumption low.
- Token-based scorers reuse normalized tokens to avoid repetitive allocations, and the abbreviation scorer compares initialisms for RapidFuzz-compatible acronym handling.
- Pairwise and vectorized helpers share cached data structures and can warm caches ahead of time for predictable latency.
- For repeated queries against static data, pre-process and cache tokenized representations.

## Testing

```bash
composer install
composer test
```

## Releasing to Packagist

1. Update the package name in `composer.json` to match your vendor namespace.
2. Tag a semantic version: `git tag v1.0.0 && git push --tags`.
3. Submit the repository to [Packagist](https://packagist.org/) and enable auto-updates.

## License

MIT License. See [LICENSE](LICENSE) for details.
