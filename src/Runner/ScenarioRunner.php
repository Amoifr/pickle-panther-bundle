<?php

declare(strict_types=1);

namespace Amoifr\PicklePantherBundle\Runner;

use Amoifr\PicklePantherBundle\Auth\AuthenticatorInterface;
use Amoifr\PicklePantherBundle\Config\PicklePantherConfig;
use Amoifr\PicklePantherBundle\Report\ReporterInterface;
use Amoifr\PicklePantherBundle\Report\StepResult;
use Amoifr\PicklePantherBundle\Sentence\BoundSentence;
use Amoifr\PicklePantherBundle\Sentence\SentenceRegistry;
use Amoifr\PicklePantherBundle\Testing\BasePantherTest;

/**
 * Executes a scenario YAML file: resolves each sentence to a provider method,
 * applies per-scenario context (browser size, authentication), runs the steps,
 * captures screenshots and feeds the reporter.
 */
final class ScenarioRunner
{
    /** @var array<string, BoundSentence> */
    private array $actionMap;

    private readonly ScenarioParser $parser;
    private readonly string $capturesDir;

    public function __construct(
        private readonly BasePantherTest $testCase,
        SentenceRegistry $registry,
        private readonly PicklePantherConfig $config,
        private readonly ReporterInterface $reporter,
        private readonly ?AuthenticatorInterface $authenticator = null,
    ) {
        $this->parser = new ScenarioParser();
        $this->capturesDir = $config->capturesDir();
        $context = new PantherContext($testCase, $config);
        $this->actionMap = $registry->buildActionMap($context);
    }

    public function runTest(string $scenarioFile): void
    {
        $scenarios = $this->parser->parseFile($scenarioFile);
        $this->validate($scenarios, $scenarioFile);

        $fileName = basename($scenarioFile);
        foreach ($scenarios as $scenario) {
            $this->executeScenario($scenario, $fileName);
        }
    }

    /**
     * @param list<array{name: string, description: string, context: array{browser: ?string, identity: ?string}, steps: list<array{action: string, title: ?string, args: array<string, string>}>}> $scenarios
     */
    private function validate(array $scenarios, string $file): void
    {
        foreach ($scenarios as $scenario) {
            foreach ($scenario['steps'] as $step) {
                if (null === $this->resolve($step['action'], $step['args'])) {
                    throw new \InvalidArgumentException(sprintf(
                        "Unknown sentence \"%s\" in %s.\nAvailable sentences:\n  - %s",
                        $step['action'],
                        $file,
                        implode("\n  - ", array_keys($this->actionMap)),
                    ));
                }
            }
        }
    }

    /**
     * Resolves an action to a callable and its ordered arguments.
     *
     * Two forms are supported:
     *  - exact pattern ("... [selector] ..."), with values supplied in `args`
     *    (bound by name when keys match parameters, else positionally);
     *  - inline values ("... [#go-page2] ..."), bound to the method parameters
     *    positionally in placeholder order.
     *
     * @param array<string, string> $args
     *
     * @return array{0: BoundSentence, 1: list<string>, 2: array<string, string>}|null
     *                                          [sentence, ordered call args, args for the report]
     */
    private function resolve(string $action, array $args): ?array
    {
        if (isset($this->actionMap[$action])) {
            $bound = $this->actionMap[$action];

            return [$bound, $bound->orderArgs($args), $args];
        }

        foreach ($this->actionMap as $pattern => $bound) {
            $inline = SentenceMatcher::extract($pattern, $action);
            if (null !== $inline) {
                return [$bound, array_values($inline), $inline];
            }
        }

        return null;
    }

    /**
     * @param array{name: string, description: string, context: array{browser: ?string, identity: ?string}, steps: list<array{action: string, title: ?string, args: array<string, string>}>} $scenario
     */
    private function executeScenario(array $scenario, string $fileName): void
    {
        echo 'Scénario : '.$scenario['name']."\n";

        $browser = $scenario['context']['browser'];
        $identity = $scenario['context']['identity'];
        $this->applyContext($browser, $identity);

        foreach ($scenario['steps'] as $step) {
            $action = $step['action'];
            $args = $step['args'];
            $title = $step['title'];

            echo '- '.($title ?? $action)."\n";

            // Already proven resolvable by validate().
            [$bound, $callArgs, $reportArgs] = $this->resolve($action, $args);

            try {
                ($bound->callable)(...$callArgs);

                $screenshot = null;
                if ($this->isDebugMode()) {
                    $screenshot = $this->takeScreenshot($action, 'success');
                }

                $this->reporter->addStep(new StepResult(
                    action: $action,
                    success: true,
                    screenshot: $screenshot,
                    args: $reportArgs,
                    scenarioFile: $fileName,
                    scenarioName: $scenario['name'],
                    scenarioDescription: $scenario['description'],
                    browser: $browser,
                    identity: $identity,
                    title: $title,
                ));
            } catch (\Throwable $e) {
                $screenshot = $this->takeScreenshot($action, 'error');

                $this->reporter->addStep(new StepResult(
                    action: $action.' - '.$e->getMessage(),
                    success: false,
                    screenshot: $screenshot,
                    args: $reportArgs,
                    scenarioFile: $fileName,
                    scenarioName: $scenario['name'],
                    scenarioDescription: $scenario['description'],
                    browser: $browser,
                    identity: $identity,
                    title: $title,
                ));

                $this->testCase->fail("Failed step '$action' : ".$e->getMessage());
            }
        }
    }

    private function applyContext(?string $browser, ?string $identity): void
    {
        if ('mobile' === $browser) {
            $this->testCase->configureMobileView();
        } elseif ('desktop' === $browser) {
            $this->testCase->configureDesktopView();
        }

        if (null !== $identity) {
            if (null === $this->authenticator) {
                throw new \LogicException(sprintf(
                    'Scenario requests identity "%s" but no authenticator is registered. '
                    .'Configure pickle_panther.auth or register a service aliased to %s.',
                    $identity,
                    AuthenticatorInterface::class,
                ));
            }
            $client = $this->testCase->getPantherClient();
            if (null !== $client) {
                $this->authenticator->authenticate($identity, $client);
            }
        }
    }

    private function isDebugMode(): bool
    {
        return '1' === ($_ENV['E2E_DEBUG'] ?? getenv('E2E_DEBUG'));
    }

    private function takeScreenshot(string $stepName, string $type): ?string
    {
        try {
            if (!is_dir($this->capturesDir)) {
                mkdir($this->capturesDir, 0777, true);
            }

            $testClass = (new \ReflectionClass($this->testCase))->getShortName();
            $filename = sprintf(
                '%s/%s_%s_%s_%s_%s.png',
                $this->capturesDir,
                $testClass,
                $this->testCase->name(),
                preg_replace('/[^a-z0-9_-]/i', '_', $stepName),
                $type,
                date('Ymd_His'),
            );

            $client = $this->testCase->getPantherClient();
            if (null === $client) {
                return null;
            }
            $client->takeScreenshot($filename);

            return $filename;
        } catch (\Throwable $e) {
            echo '⚠️ Unable to take screenshot: '.$e->getMessage()."\n";

            return null;
        }
    }
}
