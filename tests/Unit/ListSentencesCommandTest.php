<?php

declare(strict_types=1);

namespace Amoifr\PicklePantherBundle\Tests\Unit;

use Amoifr\PicklePantherBundle\Tests\Application\Kernel;
use PHPUnit\Framework\TestCase;
use Symfony\Bundle\FrameworkBundle\Console\Application;
use Symfony\Component\Console\Tester\CommandTester;

/**
 * The command introspects the registered sentence providers, so the test app's
 * DemoSentences (a tagged project provider) and the bundle's own sentences must
 * both appear in the generated documentation.
 */
final class ListSentencesCommandTest extends TestCase
{
    private mixed $exceptionHandlerBaseline = null;
    private mixed $errorHandlerBaseline = null;

    protected function setUp(): void
    {
        $this->exceptionHandlerBaseline = $this->peekExceptionHandler();
        $this->errorHandlerBaseline = $this->peekErrorHandler();
    }

    protected function tearDown(): void
    {
        // Booting the kernel registers error/exception handlers that are not
        // unwound; restore the stacks to the baseline so PHPUnit's strict
        // per-test handler check does not flag the test as risky.
        for ($i = 0; $i < 20 && $this->peekExceptionHandler() !== $this->exceptionHandlerBaseline; ++$i) {
            restore_exception_handler();
        }
        for ($i = 0; $i < 20 && $this->peekErrorHandler() !== $this->errorHandlerBaseline; ++$i) {
            restore_error_handler();
        }
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

    private function commandTester(): CommandTester
    {
        $kernel = new Kernel('test', true);
        $kernel->boot();

        $application = new Application($kernel);
        $application->setAutoExit(false);

        return new CommandTester($application->find('pickle-panther:sentences'));
    }

    public function testListsBundleAndProjectSentences(): void
    {
        $tester = $this->commandTester();
        $tester->execute([]);

        $tester->assertCommandIsSuccessful();
        $output = $tester->getDisplay();

        // A bundle common sentence and the test app's project provider.
        self::assertStringContainsString("Visite la page avec l'[url]", $output);
        self::assertStringContainsString('## DemoSentences', $output);
        self::assertStringContainsString('Vérifie que le titre de la page est [title]', $output);
    }

    public function testLocaleFilterAndFileOutput(): void
    {
        $tester = $this->commandTester();

        $file = sys_get_temp_dir().'/pickle-sentences-'.uniqid().'.md';
        $tester->execute(['--locale' => 'en', '--output' => $file]);
        $tester->assertCommandIsSuccessful();

        self::assertFileExists($file);
        $content = (string) file_get_contents($file);
        self::assertStringContainsString('Visit the page at [url]', $content);
        self::assertStringNotContainsString("Visite la page avec l'[url]", $content);

        unlink($file);
    }
}