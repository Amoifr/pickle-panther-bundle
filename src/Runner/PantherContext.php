<?php

declare(strict_types=1);

namespace Amoifr\PicklePantherBundle\Runner;

use Amoifr\PicklePantherBundle\Config\PicklePantherConfig;
use Amoifr\PicklePantherBundle\Testing\BasePantherTest;
use Symfony\Component\Panther\Client;

/**
 * Per-run handle shared with sentence providers. {@see getClient()} always
 * resolves the current Panther client from the test case, so it stays correct
 * even after a mobile/desktop switch recreates the client.
 */
final class PantherContext
{
    public function __construct(
        private readonly BasePantherTest $testCase,
        private readonly PicklePantherConfig $config,
    ) {
    }

    public function getClient(): Client
    {
        $client = $this->testCase->getPantherClient();
        if (null === $client) {
            throw new \RuntimeException('Panther client is not initialized (did BasePantherTest::setUp() run?).');
        }

        return $client;
    }

    public function getTestCase(): BasePantherTest
    {
        return $this->testCase;
    }

    public function getConfig(): PicklePantherConfig
    {
        return $this->config;
    }
}
