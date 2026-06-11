<?php

declare(strict_types=1);

namespace Amoifr\PicklePantherBundle;

use Amoifr\PicklePantherBundle\DependencyInjection\Compiler\SentenceProviderPass;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\HttpKernel\Bundle\Bundle;

final class PicklePantherBundle extends Bundle
{
    public function build(ContainerBuilder $container): void
    {
        parent::build($container);
        $container->addCompilerPass(new SentenceProviderPass());
    }
}
