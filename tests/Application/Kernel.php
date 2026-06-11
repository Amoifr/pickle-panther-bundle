<?php

declare(strict_types=1);

namespace Amoifr\PicklePantherBundle\Tests\Application;

use Amoifr\PicklePantherBundle\Auth\AuthenticatorInterface;
use Amoifr\PicklePantherBundle\PicklePantherBundle;
use Symfony\Bundle\FrameworkBundle\FrameworkBundle;
use Symfony\Bundle\FrameworkBundle\Kernel\MicroKernelTrait;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;
use Symfony\Component\HttpKernel\Kernel as BaseKernel;

/**
 * Minimal application used to functionally test the bundle.
 */
final class Kernel extends BaseKernel
{
    use MicroKernelTrait;

    public function registerBundles(): iterable
    {
        yield new FrameworkBundle();
        yield new PicklePantherBundle();
    }

    public function getProjectDir(): string
    {
        return __DIR__;
    }

    protected function configureContainer(ContainerConfigurator $configurator): void
    {
        $configurator->extension('framework', [
            'secret' => 'pickle-panther-test',
            'test' => true,
            'http_method_override' => false,
            'handle_all_throwables' => true,
            'php_errors' => ['log' => true],
            'router' => ['utf8' => true],
        ]);

        $configurator->extension('pickle_panther', [
            'locale' => 'fr',
            'report' => [
                'output_dir' => __DIR__.'/var/pickle-panther',
            ],
        ]);

        // A project-provided sentence provider (autoconfigured -> auto-tagged).
        $services = $configurator->services()->defaults()->autowire()->autoconfigure()->public();
        $services->set(DemoSentences::class)->share(false);

        // Pluggable authentication: register the spy and alias the interface to it.
        $services->set(DemoAuthenticator::class);
        $services->alias(AuthenticatorInterface::class, DemoAuthenticator::class)->public();
    }
}
