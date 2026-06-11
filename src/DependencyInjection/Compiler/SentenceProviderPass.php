<?php

declare(strict_types=1);

namespace Amoifr\PicklePantherBundle\DependencyInjection\Compiler;

use Amoifr\PicklePantherBundle\Sentence\SentenceRegistry;
use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Reference;

/**
 * Collects every service tagged `pickle_panther.sentence_provider` and injects
 * them into the SentenceRegistry.
 */
final class SentenceProviderPass implements CompilerPassInterface
{
    public const TAG = 'pickle_panther.sentence_provider';

    public function process(ContainerBuilder $container): void
    {
        if (!$container->hasDefinition(SentenceRegistry::class)) {
            return;
        }

        $references = [];
        foreach (array_keys($container->findTaggedServiceIds(self::TAG)) as $id) {
            // Sentence providers carry per-run mutable state (the PantherContext),
            // so each must be a non-shared service to avoid cross-test bleed.
            $container->getDefinition($id)->setShared(false)->setPublic(true);
            $references[] = new Reference($id);
        }

        $container->getDefinition(SentenceRegistry::class)
            ->setArgument('$providers', $references);
    }
}
