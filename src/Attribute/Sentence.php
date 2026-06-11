<?php

declare(strict_types=1);

namespace Amoifr\PicklePantherBundle\Attribute;

/**
 * Maps a near-natural-language sentence (as written in scenario YAML) to the
 * method it annotates. Repeatable so the same method can expose the sentence in
 * several languages, e.g.:
 *
 *   #[Sentence('Visite la page avec l\'[url]', 'fr')]
 *   #[Sentence('Visit the page at [url]', 'en')]
 *   public function visit(string $url): void { ... }
 *
 * Placeholders in square brackets ([name]) document the positional arguments;
 * the runner passes scenario `args` values positionally.
 */
#[\Attribute(\Attribute::TARGET_METHOD | \Attribute::IS_REPEATABLE)]
class Sentence
{
    public function __construct(
        public string $pattern,
        public ?string $locale = null,
    ) {
    }
}
