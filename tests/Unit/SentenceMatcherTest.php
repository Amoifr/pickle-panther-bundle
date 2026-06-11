<?php

declare(strict_types=1);

namespace Amoifr\PicklePantherBundle\Tests\Unit;

use Amoifr\PicklePantherBundle\Runner\SentenceMatcher;
use PHPUnit\Framework\TestCase;

final class SentenceMatcherTest extends TestCase
{
    public function testExtractsSingleInlineValue(): void
    {
        $args = SentenceMatcher::extract(
            'Click the element [selector] with JavaScript',
            'Click the element [#go-page2] with JavaScript',
        );

        self::assertSame(['selector' => '#go-page2'], $args);
    }

    public function testExtractsMultipleInlineValuesInOrder(): void
    {
        $args = SentenceMatcher::extract(
            'Type [value] in field [selector]',
            'Type [hello] in field [#email]',
        );

        // Order follows the placeholders as they appear in the pattern.
        self::assertSame(['value' => 'hello', 'selector' => '#email'], $args);
        self::assertSame(['hello', '#email'], array_values($args));
    }

    public function testReturnsNullWhenLiteralPartsDiffer(): void
    {
        self::assertNull(SentenceMatcher::extract(
            'Click the element [selector] with JavaScript',
            'Type [hello] in field [#email]',
        ));
    }

    public function testReturnsNullForPatternWithoutPlaceholders(): void
    {
        self::assertNull(SentenceMatcher::extract(
            'Wait for the page and its scripts to be fully loaded',
            'Wait for the page and its scripts to be fully loaded',
        ));
    }

    public function testRegexSpecialCharactersInLiteralPartsAreEscaped(): void
    {
        $args = SentenceMatcher::extract(
            "Verify that the URL contains [fragment] (exact)",
            "Verify that the URL contains [/page2] (exact)",
        );

        self::assertSame(['fragment' => '/page2'], $args);
    }
}
