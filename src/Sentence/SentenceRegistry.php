<?php

declare(strict_types=1);

namespace Amoifr\PicklePantherBundle\Sentence;

use Amoifr\PicklePantherBundle\Attribute\Sentence;
use Amoifr\PicklePantherBundle\Runner\PantherContext;

/**
 * Aggregates every registered sentence provider and builds the action map that
 * the runner uses to resolve a scenario sentence to a callable.
 */
final class SentenceRegistry
{
    /**
     * @param iterable<SentenceProviderInterface> $providers
     */
    public function __construct(
        private readonly iterable $providers,
    ) {
    }

    /**
     * Binds the per-run context to every provider and returns the action map:
     * sentence pattern => callable(...$args).
     *
     * Matching is locale-agnostic: every #[Sentence] pattern declared by any
     * provider (in any language) is indexed, so FR and EN scenarios both work.
     *
     * @return array<string, BoundSentence>
     */
    public function buildActionMap(PantherContext $context): array
    {
        $map = [];

        foreach ($this->providers as $provider) {
            $provider->setContext($context);

            $reflection = new \ReflectionObject($provider);
            foreach ($reflection->getMethods(\ReflectionMethod::IS_PUBLIC) as $method) {
                $attributes = $method->getAttributes(Sentence::class, \ReflectionAttribute::IS_INSTANCEOF);
                if ([] === $attributes) {
                    continue;
                }

                $parameterNames = array_map(
                    static fn (\ReflectionParameter $p): string => $p->getName(),
                    $method->getParameters(),
                );
                $bound = new BoundSentence($method->getClosure($provider), $parameterNames);

                foreach ($attributes as $attribute) {
                    $pattern = $attribute->newInstance()->pattern;
                    if (isset($map[$pattern])) {
                        throw new \LogicException(sprintf(
                            'Duplicate sentence "%s" declared by %s::%s().',
                            $pattern,
                            $provider::class,
                            $method->getName(),
                        ));
                    }
                    $map[$pattern] = $bound;
                }
            }
        }

        return $map;
    }
}
