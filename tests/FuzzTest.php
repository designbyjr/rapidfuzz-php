<?php

declare(strict_types=1);

namespace RapidFuzz\Tests;

use PHPUnit\Framework\TestCase;
use RapidFuzz\Fuzz;
use RapidFuzz\Support\CachedProcessor;
use RapidFuzz\Utils\Processor;

final class FuzzTest extends TestCase
{
    public function testRatio(): void
    {
        self::assertSame(100.0, Fuzz::ratio('rapidfuzz', 'rapidfuzz'));
        self::assertSame(0.0, Fuzz::ratio('', 'rapidfuzz'));
        self::assertSame(0.0, Fuzz::ratio('rapidfuzz', ''));

        $score = Fuzz::ratio('rapid fuzz', 'rapidfuzz');
        self::assertGreaterThan(80.0, $score);
    }

    public function testPartialRatio(): void
    {
        $score = Fuzz::partialRatio('rapid fuzz', 'the rapid fuzz library');
        self::assertGreaterThan(80.0, $score);
    }

    public function testTokenRatios(): void
    {
        $tokenSort = Fuzz::tokenSortRatio('New York Mets', 'New York Jets');
        self::assertGreaterThan(70.0, $tokenSort);

        $tokenSet = Fuzz::tokenSetRatio('apple banana orange', 'banana orange kiwi');
        self::assertGreaterThan(70.0, $tokenSet);

        $tokenRatio = Fuzz::tokenRatio('new york mets', 'mets new york');
        self::assertSame(100.0, $tokenRatio);

        $partialTokenSort = Fuzz::partialTokenSortRatio('the new york mets', 'new york mets baseball');
        self::assertGreaterThan(50.0, $partialTokenSort);

        $partialTokenSet = Fuzz::partialTokenSetRatio('apple banana', 'banana apple orange');
        self::assertGreaterThan(50.0, $partialTokenSet);
    }

    public function testWeightedRatio(): void
    {
        $score = Fuzz::weightedRatio('International Business Machines', 'IBM');
        self::assertGreaterThan(60.0, $score);

        self::assertSame($score, Fuzz::wRatio('International Business Machines', 'IBM'));
    }

    public function testWeightedRatioWithProcessor(): void
    {
        $processor = new CachedProcessor([Processor::class, 'defaultProcess']);

        $score = Fuzz::wRatio('this is a word', 'THIS IS A WORD!!!', $processor);

        self::assertSame(100.0, round($score));
    }

    public function testExtractOne(): void
    {
        [$choice, $score, $index] = Fuzz::extractOne('rapidfuzz', ['rapid fuzz', 'fast fuzzing library']);
        self::assertSame('rapid fuzz', $choice);
        self::assertGreaterThan(70.0, $score);
        self::assertSame(0, $index);
    }

    public function testQuickAndAbbreviationRatios(): void
    {
        $quick = Fuzz::quickRatio('Rapid Fuzz', 'rapidfuzz');
        self::assertGreaterThanOrEqual(75.0, $quick);

        $abbr = Fuzz::tokenAbbreviationRatio('International Business Machines', 'IBM');
        self::assertSame(100.0, $abbr);

        $hamming = Fuzz::hamming('abcd', 'abce');
        self::assertGreaterThanOrEqual(75.0, $hamming);
    }

    public function testRatioWithProcessor(): void
    {
        $processor = [Processor::class, 'defaultProcess'];

        $score = Fuzz::ratio('THIS IS A TEST!!!', 'this is a test', $processor);

        self::assertSame(100.0, $score);
    }
}
