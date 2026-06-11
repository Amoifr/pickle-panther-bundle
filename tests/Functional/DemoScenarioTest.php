<?php

declare(strict_types=1);

namespace Amoifr\PicklePantherBundle\Tests\Functional;

use Amoifr\PicklePantherBundle\Testing\BasePantherTest;
use Amoifr\PicklePantherBundle\Tests\Application\DemoAuthenticator;

/**
 * End-to-end run of the demo scenarios through a real browser. Skipped
 * automatically when no compatible Chrome/chromedriver pair is available.
 */
final class DemoScenarioTest extends BasePantherTest
{
    protected function setUp(): void
    {
        try {
            parent::setUp();
        } catch (\Throwable $e) {
            self::markTestSkipped('Panther/Chrome is not available in this environment: '.$e->getMessage());
        }
    }

    public function testFrenchScenario(): void
    {
        DemoAuthenticator::$authenticated = [];

        $this->createScenarioRunner()->runTest(
            __DIR__.'/../Application/Scenario/demo_fr.yaml'
        );

        // The authenticated scenario must have driven the pluggable authenticator.
        self::assertContains('admin', DemoAuthenticator::$authenticated);
    }

    public function testEnglishScenario(): void
    {
        $this->createScenarioRunner()->runTest(
            __DIR__.'/../Application/Scenario/demo_en.yaml'
        );

        self::assertStringContainsString('page2', (string) $this->getPantherClient()?->getCurrentURL());
    }
}
