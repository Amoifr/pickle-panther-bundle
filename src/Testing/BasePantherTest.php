<?php

declare(strict_types=1);

namespace Amoifr\PicklePantherBundle\Testing;

use Amoifr\PicklePantherBundle\Auth\AuthenticatorInterface;
use Amoifr\PicklePantherBundle\Config\PicklePantherConfig;
use Amoifr\PicklePantherBundle\Report\HtmlReporter;
use Amoifr\PicklePantherBundle\Report\ReporterInterface;
use Amoifr\PicklePantherBundle\Report\StepResult;
use Amoifr\PicklePantherBundle\Runner\ScenarioRunner;
use Amoifr\PicklePantherBundle\Sentence\SentenceRegistry;
use Facebook\WebDriver\WebDriverDimension;
use Psr\Container\ContainerInterface;
use Symfony\Component\Panther\Client;
use Symfony\Component\Panther\PantherTestCase;

/**
 * Base class for scenario-driven Panther tests. Subclass it, then in a test
 * method call:
 *
 *   $this->createScenarioRunner()->runTest(__DIR__.'/Scenario/homepage.yaml');
 *
 * Browser sizing, user agents, captures directory and headless mode are taken
 * from the `pickle_panther` configuration and can be overridden per project.
 */
abstract class BasePantherTest extends PantherTestCase
{
    protected ?Client $client = null;
    private string $currentViewMode = 'desktop';
    private ?PicklePantherConfig $pantherConfig = null;
    private mixed $exceptionHandlerBaseline = null;
    private mixed $errorHandlerBaseline = null;

    protected function setUp(): void
    {
        $this->exceptionHandlerBaseline = $this->peekExceptionHandler();
        $this->errorHandlerBaseline = $this->peekErrorHandler();

        parent::setUp();
        $this->cleanupOldTempDirs();

        $capturesDir = $this->pantherConfig()->capturesDir();
        if (!is_dir($capturesDir)) {
            mkdir($capturesDir, 0777, true);
        }

        $this->client = $this->createConfiguredClient($this->getDesktopOptions());
        $this->client->manage()->window()->setSize($this->desktopDimension());
        $this->currentViewMode = 'desktop';

        // Clear screenshots from a previous run.
        $files = glob($capturesDir.'/*.png') ?: [];
        foreach ($files as $f) {
            @unlink($f);
        }
    }

    protected function tearDown(): void
    {
        $testName = (new \ReflectionClass($this))->getShortName().'::'.$this->name();

        if ($this->hasFailed()) {
            $screenshot = null;
            try {
                $screenshot = $this->takeScreenshotOnFailure();
            } catch (\Throwable $e) {
                echo "\n⚠️ Screenshot error: ".$e->getMessage()."\n";
            }
            HtmlReporter::record(new StepResult(action: $testName, success: false, screenshot: $screenshot));
        } else {
            HtmlReporter::record(new StepResult(action: $testName, success: true));
        }

        if (null !== $this->client) {
            try {
                $this->client->quit();
            } catch (\Throwable) {
                // ignore close errors
            }
            $this->client = null;
        }

        parent::tearDown();

        // Booting the Symfony kernel registers an error/exception handler that is
        // not always unwound; restore the handler stacks down to the snapshot
        // taken in setUp() so PHPUnit's strict per-test handler check does not
        // flag the test as risky.
        $this->restoreHandlers();
    }

    public function getPantherClient(): ?Client
    {
        return $this->client;
    }

    public function getTestContainer(): ContainerInterface
    {
        return self::getContainer();
    }

    /**
     * Assembles a ScenarioRunner wired from the test container.
     */
    public function createScenarioRunner(): ScenarioRunner
    {
        $container = $this->getTestContainer();

        /** @var SentenceRegistry $registry */
        $registry = $container->get(SentenceRegistry::class);
        /** @var ReporterInterface $reporter */
        $reporter = $container->get(ReporterInterface::class);
        $authenticator = $container->has(AuthenticatorInterface::class)
            ? $container->get(AuthenticatorInterface::class)
            : null;
        \assert(null === $authenticator || $authenticator instanceof AuthenticatorInterface);

        return new ScenarioRunner($this, $registry, $this->pantherConfig(), $reporter, $authenticator);
    }

    public function configureMobileView(): void
    {
        if ('mobile' === $this->currentViewMode) {
            return;
        }
        $this->recreateClient($this->getMobileOptions(), $this->mobileDimension());
        $this->currentViewMode = 'mobile';
    }

    public function configureDesktopView(): void
    {
        if ('desktop' === $this->currentViewMode) {
            return;
        }
        $this->recreateClient($this->getDesktopOptions(), $this->desktopDimension());
        $this->currentViewMode = 'desktop';
    }

    public function hasFailed(): bool
    {
        return $this->status()->isFailure() || $this->status()->isError();
    }

    protected function pantherConfig(): PicklePantherConfig
    {
        /** @var PicklePantherConfig $config */
        $config = $this->pantherConfig ??= $this->getTestContainer()->get(PicklePantherConfig::class);

        return $config;
    }

    /**
     * @param array<string, mixed> $options
     */
    private function createConfiguredClient(array $options): Client
    {
        return static::createPantherClient($options, [], $this->managerOptions());
    }

    /**
     * @param array<string, mixed> $options
     */
    private function recreateClient(array $options, WebDriverDimension $dimension): void
    {
        if (null !== $this->client) {
            try {
                $this->client->quit();
            } catch (\Throwable) {
                // ignore close errors
            }
        }
        $this->client = static::createPantherClient($options, [], $this->managerOptions());
        $this->client->manage()->window()->setSize($dimension);
    }

    /**
     * @return list<string>
     */
    protected function commonChromeArgs(): array
    {
        $args = [
            '--no-sandbox',
            '--disable-dev-shm-usage',
            '--disable-gpu',
            '--disable-setuid-sandbox',
            '--autoplay-policy=document-user-activation-required',
            '--mute-audio',
            '--disable-background-media-suspend',
            '--disable-backgrounding-occluded-windows',
            '--disable-renderer-backgrounding',
            '--disable-software-rasterizer',
            '--disable-features=VizDisplayCompositor',
            '--disable-extensions',
            '--disable-blink-features=AutomationControlled',
        ];

        if ($this->pantherConfig()->browser['headless']) {
            $args[] = '--headless=new';
        }

        return array_merge($args, $this->pantherConfig()->browser['chrome_args']);
    }

    /**
     * @return array<string, int>
     */
    protected function commonChromePrefs(): array
    {
        return [
            'profile.default_content_setting_values.media_stream' => 2,
            'profile.default_content_setting_values.notifications' => 2,
            'profile.default_content_setting_values.plugins' => 2,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    protected function getDesktopOptions(): array
    {
        $desktop = $this->pantherConfig()->browser['desktop'];

        return [
            'browser' => static::CHROME,
            'capabilities' => [
                'goog:chromeOptions' => [
                    'args' => array_merge($this->commonChromeArgs(), [
                        sprintf('--window-size=%d,%d', $desktop['width'], $desktop['height']),
                        '--force-device-scale-factor=1',
                        '--user-agent='.$desktop['user_agent'],
                    ]),
                    'prefs' => $this->commonChromePrefs(),
                ],
            ],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    protected function getMobileOptions(): array
    {
        $mobile = $this->pantherConfig()->browser['mobile'];

        return [
            'browser' => static::CHROME,
            'capabilities' => [
                'goog:chromeOptions' => [
                    'args' => array_merge($this->commonChromeArgs(), [
                        sprintf('--window-size=%d,%d', $mobile['width'], $mobile['height']),
                        sprintf('--force-device-scale-factor=%s', $mobile['pixel_ratio']),
                        '--user-agent='.$mobile['user_agent'],
                    ]),
                    'prefs' => $this->commonChromePrefs(),
                    'mobileEmulation' => [
                        'deviceMetrics' => [
                            'width' => $mobile['width'],
                            'height' => $mobile['height'],
                            'pixelRatio' => $mobile['pixel_ratio'],
                        ],
                        'userAgent' => $mobile['user_agent'],
                    ],
                ],
            ],
        ];
    }

    /**
     * ProcessManager options (3rd arg of createPantherClient) — this is where the
     * real WebDriver session capabilities go. Auto-accepts native JS alerts so a
     * pending alert cannot block every subsequent WebDriver command.
     *
     * @return array<string, mixed>
     */
    protected function managerOptions(): array
    {
        return [
            'capabilities' => [
                'unhandledPromptBehavior' => 'accept',
            ],
        ];
    }

    private function desktopDimension(): WebDriverDimension
    {
        $desktop = $this->pantherConfig()->browser['desktop'];

        return new WebDriverDimension($desktop['width'], $desktop['height']);
    }

    private function mobileDimension(): WebDriverDimension
    {
        $mobile = $this->pantherConfig()->browser['mobile'];

        return new WebDriverDimension($mobile['width'], $mobile['height']);
    }

    private function takeScreenshotOnFailure(): ?string
    {
        if (null === $this->client) {
            return null;
        }
        $dir = $this->pantherConfig()->capturesDir();
        if (!is_dir($dir)) {
            mkdir($dir, 0777, true);
        }
        $filename = sprintf('%s/%s_%s_%s.png', $dir, (new \ReflectionClass($this))->getShortName(), $this->name(), date('Ymd_His'));
        $this->client->takeScreenshot($filename);

        return $filename;
    }

    private function peekExceptionHandler(): mixed
    {
        $current = set_exception_handler(null);
        restore_exception_handler();

        return $current;
    }

    private function peekErrorHandler(): mixed
    {
        $current = set_error_handler(static fn (): bool => false);
        restore_error_handler();

        return $current;
    }

    private function restoreHandlers(): void
    {
        for ($i = 0; $i < 20 && $this->peekExceptionHandler() !== $this->exceptionHandlerBaseline; ++$i) {
            restore_exception_handler();
        }
        for ($i = 0; $i < 20 && $this->peekErrorHandler() !== $this->errorHandlerBaseline; ++$i) {
            restore_error_handler();
        }
    }

    private function cleanupOldTempDirs(): void
    {
        $dirs = glob(sys_get_temp_dir().'/panther-*') ?: [];
        foreach ($dirs as $dir) {
            if (is_dir($dir) && (time() - (int) filemtime($dir)) > 3600) {
                $this->recursiveRemoveDirectory($dir);
            }
        }
    }

    private function recursiveRemoveDirectory(string $directory): void
    {
        if (!file_exists($directory)) {
            return;
        }
        $entries = scandir($directory);
        if (false === $entries) {
            return;
        }
        foreach (array_diff($entries, ['.', '..']) as $file) {
            $path = $directory.'/'.$file;
            is_dir($path) ? $this->recursiveRemoveDirectory($path) : unlink($path);
        }
        rmdir($directory);
    }
}
