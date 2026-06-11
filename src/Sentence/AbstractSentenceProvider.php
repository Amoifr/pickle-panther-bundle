<?php

declare(strict_types=1);

namespace Amoifr\PicklePantherBundle\Sentence;

use Amoifr\PicklePantherBundle\Runner\PantherContext;
use Amoifr\PicklePantherBundle\Testing\BasePantherTest;
use Symfony\Component\Panther\Client;

/**
 * Base class for sentence providers. Gives concrete providers access to the
 * current Panther client and the running test case (for PHPUnit assertions),
 * plus a couple of shared DOM helpers.
 */
abstract class AbstractSentenceProvider implements SentenceProviderInterface
{
    protected PantherContext $context;

    public function setContext(PantherContext $context): void
    {
        $this->context = $context;
    }

    protected function client(): Client
    {
        return $this->context->getClient();
    }

    protected function testCase(): BasePantherTest
    {
        return $this->context->getTestCase();
    }

    /**
     * Scrolls the element into view and reports whether it is effectively
     * visible (rendered with a non-zero box and not hidden by CSS).
     */
    protected function isSelectorVisible(string $selector): bool
    {
        return (bool) $this->client()->executeScript("
            const element = document.querySelector('".addslashes($selector)."');
            if (!element) return false;

            element.scrollIntoView({ behavior: 'instant', block: 'center' });

            const style = window.getComputedStyle(element);
            const rect = element.getBoundingClientRect();

            return (
                style.display !== 'none' &&
                style.visibility !== 'hidden' &&
                style.opacity !== '0' &&
                rect.width > 0 &&
                rect.height > 0
            );
        ");
    }
}
