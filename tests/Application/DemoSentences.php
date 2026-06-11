<?php

declare(strict_types=1);

namespace Amoifr\PicklePantherBundle\Tests\Application;

use Amoifr\PicklePantherBundle\Attribute\Sentence;
use Amoifr\PicklePantherBundle\Sentence\AbstractSentenceProvider;

/**
 * Example project-specific sentence provider.
 */
final class DemoSentences extends AbstractSentenceProvider
{
    #[Sentence('Vérifie que le titre de la page est [title]', 'fr')]
    #[Sentence('Verify that the page title is [title]', 'en')]
    public function assertPageTitle(string $title): void
    {
        $this->testCase()->assertSame($title, $this->client()->getTitle());
    }
}
