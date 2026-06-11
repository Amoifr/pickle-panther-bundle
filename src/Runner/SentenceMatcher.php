<?php

declare(strict_types=1);

namespace Amoifr\PicklePantherBundle\Runner;

/**
 * Extracts inline argument values from an action written with values inside the
 * brackets instead of placeholder names.
 *
 * Given the registered pattern:
 *     "Click the element [selector] with JavaScript"
 * the action:
 *     "Click the element [#go-page2] with JavaScript"
 * yields ['selector' => '#go-page2'].
 *
 * Each `[name]` in the pattern becomes a non-greedy capture between literal
 * brackets. Because the capture stops at the first closing bracket, inline
 * values must not themselves contain "]" (e.g. CSS attribute selectors like
 * [data-x="y"]) — use the explicit `args:` form for those.
 */
final class SentenceMatcher
{
    /**
     * @return array<string, string>|null the captured args, or null if the
     *                                     action does not match the pattern
     */
    public static function extract(string $pattern, string $action): ?array
    {
        $tokens = preg_split('/\[(\w+)\]/', $pattern, -1, \PREG_SPLIT_DELIM_CAPTURE);
        if (false === $tokens || \count($tokens) < 2) {
            return null; // pattern has no placeholders -> nothing to inline
        }

        $regex = '~^';
        foreach ($tokens as $i => $token) {
            $regex .= 0 === $i % 2
                ? preg_quote($token, '~')
                : '\[(?<'.$token.'>.*?)\]';
        }
        $regex .= '$~u';

        if (1 !== preg_match($regex, $action, $matches)) {
            return null;
        }

        $args = [];
        foreach ($matches as $key => $value) {
            if (\is_string($key)) {
                $args[$key] = $value;
            }
        }

        return $args;
    }
}
