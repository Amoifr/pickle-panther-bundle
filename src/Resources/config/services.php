<?php

declare(strict_types=1);

namespace Symfony\Component\DependencyInjection\Loader\Configurator;

use Amoifr\PicklePantherBundle\Auth\FormLoginAuthenticator;
use Amoifr\PicklePantherBundle\Config\PicklePantherConfig;
use Amoifr\PicklePantherBundle\DependencyInjection\Compiler\SentenceProviderPass;
use Amoifr\PicklePantherBundle\Report\HtmlReporter;
use Amoifr\PicklePantherBundle\Report\ReporterInterface;
use Amoifr\PicklePantherBundle\Sentence\AdminSentences;
use Amoifr\PicklePantherBundle\Sentence\CommonSentences;
use Amoifr\PicklePantherBundle\Sentence\SentenceRegistry;

return static function (ContainerConfigurator $container): void {
    $services = $container->services()
        ->defaults()
            ->autowire()
            ->autoconfigure();

    // Resolved configuration object (arguments injected by the extension).
    $services->set(PicklePantherConfig::class)->public();

    // Sentence providers shipped by the bundle. Non-shared so per-run context
    // does not leak between tests; tagged for collection by the compiler pass.
    $services->set(CommonSentences::class)
        ->share(false)
        ->public()
        ->tag(SentenceProviderPass::TAG);

    $services->set(AdminSentences::class)
        ->share(false)
        ->public()
        ->tag(SentenceProviderPass::TAG);

    // Providers list is replaced by SentenceProviderPass.
    $services->set(SentenceRegistry::class)
        ->public()
        ->args([[]]);

    // HTML reporter (default ReporterInterface).
    $services->set(HtmlReporter::class)->public();
    $services->alias(ReporterInterface::class, HtmlReporter::class)->public();

    // Generic form-login authenticator: defined here but only kept (and aliased
    // to AuthenticatorInterface) by the extension when `pickle_panther.auth` is set.
    $services->set(FormLoginAuthenticator::class)->public();
};
