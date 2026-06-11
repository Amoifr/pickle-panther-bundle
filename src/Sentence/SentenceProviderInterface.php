<?php

declare(strict_types=1);

namespace Amoifr\PicklePantherBundle\Sentence;

use Amoifr\PicklePantherBundle\Runner\PantherContext;

/**
 * A bag of methods annotated with #[Sentence] that the runner can invoke.
 * Any autoconfigured service implementing this interface is automatically
 * tagged `pickle_panther.sentence_provider` and collected into the
 * SentenceRegistry. Extend {@see AbstractSentenceProvider} for the common case.
 */
interface SentenceProviderInterface
{
    public function setContext(PantherContext $context): void;
}
