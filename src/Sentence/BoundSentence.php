<?php

declare(strict_types=1);

namespace Amoifr\PicklePantherBundle\Sentence;

/**
 * A resolved sentence: the callable to invoke plus the ordered names of its
 * parameters, so the runner can bind scenario `args` either by name or by
 * position.
 */
final readonly class BoundSentence
{
    /**
     * @param list<string> $parameterNames
     */
    public function __construct(
        public \Closure $callable,
        public array $parameterNames,
    ) {
    }

    /**
     * Orders the given (named) scenario args to match the method signature.
     *
     * If every parameter name is present as an arg key, args are bound by name
     * (order-independent — the natural case, since placeholders match parameter
     * names). Otherwise it falls back to positional order (array values), which
     * preserves the original positional contract.
     *
     * @param array<string, string> $args
     *
     * @return list<string>
     */
    public function orderArgs(array $args): array
    {
        if ([] === $this->parameterNames) {
            return [];
        }

        $byName = [];
        foreach ($this->parameterNames as $name) {
            if (!\array_key_exists($name, $args)) {
                return array_values($args);
            }
            $byName[] = $args[$name];
        }

        return $byName;
    }
}
