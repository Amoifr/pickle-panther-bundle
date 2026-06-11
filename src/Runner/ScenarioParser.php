<?php

declare(strict_types=1);

namespace Amoifr\PicklePantherBundle\Runner;

use Symfony\Component\Yaml\Yaml;

/**
 * Parses a scenario YAML file into a normalized structure, accepting both the
 * French and English key vocabularies.
 *
 * @phpstan-type NormalizedStep array{action: string, title: ?string, args: array<string, string>}
 * @phpstan-type NormalizedScenario array{
 *     name: string,
 *     description: string,
 *     context: array{browser: ?string, identity: ?string},
 *     steps: list<NormalizedStep>
 * }
 */
final class ScenarioParser
{
    /**
     * @return list<NormalizedScenario>
     */
    public function parseFile(string $file): array
    {
        if (!is_file($file)) {
            throw new \InvalidArgumentException(sprintf('Scenario file "%s" does not exist.', $file));
        }

        $data = Yaml::parseFile($file);
        if (!\is_array($data) || !isset($data['scenarios']) || !\is_array($data['scenarios'])) {
            throw new \InvalidArgumentException(sprintf('Scenario file "%s" must contain a top-level "scenarios" list.', $file));
        }

        $scenarios = [];
        foreach ($data['scenarios'] as $raw) {
            $scenarios[] = $this->normalizeScenario($raw, $file);
        }

        return $scenarios;
    }

    /**
     * @param array<string, mixed> $raw
     *
     * @return NormalizedScenario
     */
    private function normalizeScenario(array $raw, string $file): array
    {
        $name = $this->first($raw, ['nom', 'name']);
        if (!\is_string($name) || '' === $name) {
            throw new \InvalidArgumentException(sprintf('A scenario in "%s" is missing its name (nom/name).', $file));
        }

        $context = $this->first($raw, ['contexte', 'context']);
        $browser = null;
        $identity = null;
        if (\is_array($context)) {
            $browser = $this->scalarOrNull($this->first($context, ['navigateur', 'browser']));
            $identity = $this->scalarOrNull($this->first($context, ['identifié', 'identifie', 'identified']));
        }

        $rawSteps = $this->first($raw, ['etapes', 'étapes', 'steps']);
        if (!\is_array($rawSteps)) {
            throw new \InvalidArgumentException(sprintf('Scenario "%s" in "%s" has no steps (etapes/steps).', $name, $file));
        }

        $steps = [];
        foreach ($rawSteps as $rawStep) {
            $action = \is_array($rawStep) ? ($rawStep['action'] ?? null) : null;
            if (!\is_string($action) || '' === $action) {
                throw new \InvalidArgumentException(sprintf('A step of scenario "%s" in "%s" is missing its "action".', $name, $file));
            }
            /** @var array<string, string> $args */
            $args = \is_array($rawStep['args'] ?? null) ? $rawStep['args'] : [];

            $steps[] = [
                'action' => $action,
                'title' => $this->scalarOrNull($this->first($rawStep, ['titre', 'title'])),
                'args' => $args,
            ];
        }

        return [
            'name' => $name,
            'description' => (string) ($this->scalarOrNull($this->first($raw, ['description'])) ?? ''),
            'context' => ['browser' => $browser, 'identity' => $identity],
            'steps' => $steps,
        ];
    }

    /**
     * @param array<string, mixed> $data
     * @param list<string>         $keys
     */
    private function first(array $data, array $keys): mixed
    {
        foreach ($keys as $key) {
            if (\array_key_exists($key, $data)) {
                return $data[$key];
            }
        }

        return null;
    }

    private function scalarOrNull(mixed $value): ?string
    {
        return (null === $value || '' === $value) ? null : (string) $value;
    }
}
