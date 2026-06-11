<?php

declare(strict_types=1);

namespace Amoifr\PicklePantherBundle\Attribute;

/**
 * Backwards-compatible alias for {@see Sentence}, easing migration from a
 * legacy `#[Description('...')]` attribute. New code should use #[Sentence(...)].
 */
#[\Attribute(\Attribute::TARGET_METHOD | \Attribute::IS_REPEATABLE)]
final class Description extends Sentence
{
    public function __construct(string $fr)
    {
        parent::__construct($fr, 'fr');
    }
}
