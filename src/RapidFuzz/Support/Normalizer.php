<?php

declare(strict_types=1);

namespace RapidFuzz\Support;

/**
 * Normalizes strings for fuzzy matching using Unicode aware lower casing and trimming.
 */
final class Normalizer
{
    private function __construct()
    {
    }

    public static function clean(string $value): string
    {
        if (class_exists('Normalizer')) {
            $value = \Normalizer::isNormalized($value, \Normalizer::FORM_C)
                ? $value
                : \Normalizer::normalize($value, \Normalizer::FORM_C);
        }

        $value = mb_strtolower($value, 'UTF-8');
        $value = preg_replace('/[\p{P}\p{S}]+/u', ' ', $value) ?? $value;
        $value = preg_replace('/\s+/u', ' ', $value) ?? $value;

        return trim($value);
    }
}
