<?php

declare(strict_types=1);

namespace Amoifr\PicklePantherBundle\Tests\Unit;

use Amoifr\PicklePantherBundle\Auth\AuthenticatorInterface;
use Amoifr\PicklePantherBundle\Auth\FormLoginAuthenticator;
use Amoifr\PicklePantherBundle\Config\PicklePantherConfig;
use Amoifr\PicklePantherBundle\DependencyInjection\Compiler\SentenceProviderPass;
use Amoifr\PicklePantherBundle\DependencyInjection\PicklePantherExtension;
use Amoifr\PicklePantherBundle\Report\HtmlReporter;
use Amoifr\PicklePantherBundle\Report\ReporterInterface;
use Amoifr\PicklePantherBundle\Runner\PantherContext;
use Amoifr\PicklePantherBundle\Sentence\SentenceRegistry;
use Amoifr\PicklePantherBundle\Testing\BasePantherTest;
use PHPUnit\Framework\TestCase;
use Symfony\Component\DependencyInjection\ContainerBuilder;

final class ConfigurationWiringTest extends TestCase
{
    /**
     * @param array<string, mixed> $config
     */
    private function compile(array $config): ContainerBuilder
    {
        $container = new ContainerBuilder();
        $container->setParameter('kernel.project_dir', sys_get_temp_dir().'/pickle-project');

        $extension = new PicklePantherExtension();
        $container->registerExtension($extension);
        $container->loadFromExtension('pickle_panther', $config);
        $container->addCompilerPass(new SentenceProviderPass());

        $container->compile();

        return $container;
    }

    public function testReporterAndSentenceProvidersAreWired(): void
    {
        $container = $this->compile([]);

        self::assertInstanceOf(HtmlReporter::class, $container->get(ReporterInterface::class));

        $config = $container->get(PicklePantherConfig::class);
        self::assertInstanceOf(PicklePantherConfig::class, $config);

        /** @var SentenceRegistry $registry */
        $registry = $container->get(SentenceRegistry::class);
        $context = new PantherContext($this->dummyTestCase(), $config);
        $map = $registry->buildActionMap($context);

        // Common sentences, in both languages, plus a generic admin one.
        self::assertArrayHasKey("Visite la page avec l'[url]", $map);
        self::assertArrayHasKey('Visit the page at [url]', $map);
        self::assertArrayHasKey('Click the admin menu link containing the text [text]', $map);
    }

    public function testNoAuthenticatorWithoutAuthConfig(): void
    {
        $container = $this->compile([]);

        self::assertFalse($container->has(AuthenticatorInterface::class));
        self::assertFalse($container->has(FormLoginAuthenticator::class));
    }

    public function testFormLoginAuthenticatorWiredWhenAuthConfigured(): void
    {
        $container = $this->compile([
            'auth' => [
                'login_path' => '/connexion',
                'roles' => [
                    'admin' => ['email' => 'admin@example.com', 'password' => 'secret'],
                ],
            ],
        ]);

        $authenticator = $container->get(AuthenticatorInterface::class);
        self::assertInstanceOf(FormLoginAuthenticator::class, $authenticator);
    }

    public function testDebugDefaultsToFalseAndCanBeEnabled(): void
    {
        $default = $this->compile([])->get(PicklePantherConfig::class);
        self::assertInstanceOf(PicklePantherConfig::class, $default);
        self::assertFalse($default->debug);

        $enabled = $this->compile(['debug' => true])->get(PicklePantherConfig::class);
        self::assertInstanceOf(PicklePantherConfig::class, $enabled);
        self::assertTrue($enabled->debug);
    }

    private function dummyTestCase(): BasePantherTest
    {
        return new class('dummy') extends BasePantherTest {};
    }
}
