<?php

declare(strict_types=1);

namespace RapidFuzz\Utils;

use RapidFuzz\Support\Normalizer;

final class Processor
{
    private function __construct()
    {
    }

    public static function defaultProcess(string $value): string
    {
        return Normalizer::clean($value);
    }
}
