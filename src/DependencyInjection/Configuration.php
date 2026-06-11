<?php

declare(strict_types=1);

namespace Amoifr\PicklePantherBundle\DependencyInjection;

use Symfony\Component\Config\Definition\Builder\TreeBuilder;
use Symfony\Component\Config\Definition\ConfigurationInterface;

final class Configuration implements ConfigurationInterface
{
    public function getConfigTreeBuilder(): TreeBuilder
    {
        $treeBuilder = new TreeBuilder('pickle_panther');
        $root = $treeBuilder->getRootNode();

        $root
            ->children()
                // Default DSL language. Matching is locale-agnostic (a method may
                // declare its #[Sentence] pattern in several languages); this only
                // sets a default used for documentation/report purposes.
                ->enumNode('locale')->values(['fr', 'en'])->defaultValue('fr')->end()
                // Where project scenario YAML files live (informational; tests pass
                // explicit paths to the runner).
                ->scalarNode('scenarios_dir')
                    ->defaultValue('%kernel.project_dir%/tests/E2E/Scenario')
                ->end()
                ->arrayNode('report')
                    ->addDefaultsIfNotSet()
                    ->children()
                        ->booleanNode('enabled')->defaultTrue()->end()
                        ->scalarNode('output_dir')
                            ->defaultValue('%kernel.project_dir%/var/pickle-panther')
                        ->end()
                    ->end()
                ->end()
                ->arrayNode('browser')
                    ->addDefaultsIfNotSet()
                    ->children()
                        ->booleanNode('headless')->defaultTrue()->end()
                        ->arrayNode('chrome_args')
                            ->scalarPrototype()->end()
                            ->defaultValue([])
                        ->end()
                        ->arrayNode('desktop')
                            ->addDefaultsIfNotSet()
                            ->children()
                                ->integerNode('width')->defaultValue(1920)->end()
                                ->integerNode('height')->defaultValue(1080)->end()
                                ->scalarNode('user_agent')
                                    ->defaultValue('Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/126.0.0.0 Safari/537.36')
                                ->end()
                            ->end()
                        ->end()
                        ->arrayNode('mobile')
                            ->addDefaultsIfNotSet()
                            ->children()
                                ->integerNode('width')->defaultValue(375)->end()
                                ->integerNode('height')->defaultValue(812)->end()
                                ->floatNode('pixel_ratio')->defaultValue(3.0)->end()
                                ->scalarNode('user_agent')
                                    ->defaultValue('Mozilla/5.0 (iPhone; CPU iPhone OS 16_0 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/16.0 Mobile/15E148 Safari/604.1')
                                ->end()
                            ->end()
                        ->end()
                    ->end()
                ->end()
                // Optional generic form-login configuration. When present, the bundle
                // registers a FormLoginAuthenticator wired on these values. Projects
                // with a custom login flow can instead define their own service
                // aliased to AuthenticatorInterface.
                ->arrayNode('auth')
                    ->info('Generic form-login settings consumed by FormLoginAuthenticator.')
                    ->children()
                        ->scalarNode('login_path')->defaultValue('/login')->end()
                        ->scalarNode('logout_path')->defaultValue('/logout')->end()
                        ->scalarNode('form_selector')->defaultValue('form')->end()
                        ->scalarNode('email_field')->defaultValue('_username')->end()
                        ->scalarNode('password_field')->defaultValue('_password')->end()
                        ->arrayNode('roles')
                            ->info('Map of role name (used in scenario "identified"/"identifié") to credentials.')
                            ->useAttributeAsKey('name')
                            ->arrayPrototype()
                                ->children()
                                    ->scalarNode('email')->isRequired()->end()
                                    ->scalarNode('password')->isRequired()->end()
                                ->end()
                            ->end()
                        ->end()
                    ->end()
                ->end()
            ->end();

        return $treeBuilder;
    }
}
