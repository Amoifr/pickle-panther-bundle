<?php

declare(strict_types=1);

namespace Amoifr\PicklePantherBundle\Report;

/**
 * Immutable record of a single executed step, collected by the reporter.
 */
final readonly class StepResult
{
    /**
     * @param array<string, string> $args keyed by placeholder name, for rendering
     */
    public function __construct(
        public string $action,
        public bool $success,
        public ?string $screenshot = null,
        public array $args = [],
        public ?string $scenarioFile = null,
        public ?string $scenarioName = null,
        public ?string $scenarioDescription = null,
        public ?string $browser = null,
        public ?string $identity = null,
        public ?string $title = null,
    ) {
    }
}
