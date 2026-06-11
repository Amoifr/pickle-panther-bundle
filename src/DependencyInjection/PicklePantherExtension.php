<?php

declare(strict_types=1);

namespace Amoifr\PicklePantherBundle\DependencyInjection;

use Amoifr\PicklePantherBundle\Auth\AuthenticatorInterface;
use Amoifr\PicklePantherBundle\Auth\FormLoginAuthenticator;
use Amoifr\PicklePantherBundle\Config\PicklePantherConfig;
use Amoifr\PicklePantherBundle\DependencyInjection\Compiler\SentenceProviderPass;
use Amoifr\PicklePantherBundle\Sentence\SentenceProviderInterface;
use Symfony\Component\Config\FileLocator;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Loader\PhpFileLoader;
use Symfony\Component\HttpKernel\DependencyInjection\Extension;

final class PicklePantherExtension extends Extension
{
    /**
     * @param array<mixed> $configs
     */
    public function load(array $configs, ContainerBuilder $container): void
    {
        $loader = new PhpFileLoader($container, new FileLocator(__DIR__.'/../Resources/config'));
        $loader->load('services.php');

        $container->registerForAutoconfiguration(SentenceProviderInterface::class)
            ->addTag(SentenceProviderPass::TAG);

        $configuration = new Configuration();
        $config = $this->processConfiguration($configuration, $configs);

        $configDefinition = $container->getDefinition(PicklePantherConfig::class);
        $configDefinition->setArguments([
            '$locale' => $config['locale'],
            '$scenariosDir' => $config['scenarios_dir'],
            '$reportEnabled' => $config['report']['enabled'],
            '$outputDir' => $config['report']['output_dir'],
            '$browser' => $config['browser'],
            '$auth' => $config['auth'] ?? null,
        ]);

        // Register a generic form-login authenticator only when the project opted
        // into the `auth` config block. A project with a custom login flow defines
        // its own service aliased to AuthenticatorInterface (which overrides this).
        if (isset($config['auth'])) {
            $authDefinition = $container->getDefinition(FormLoginAuthenticator::class);
            $authDefinition->setArgument('$config', $config['auth']);
            $container->setAlias(AuthenticatorInterface::class, FormLoginAuthenticator::class)
                ->setPublic(true);
        } else {
            $container->removeDefinition(FormLoginAuthenticator::class);
        }
    }

    public function getAlias(): string
    {
        return 'pickle_panther';
    }
}
