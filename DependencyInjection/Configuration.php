<?php

namespace Bamiz\UseCaseExecutorBundle\DependencyInjection;

use Symfony\Component\Config\Definition\Builder\TreeBuilder;
use Symfony\Component\Config\Definition\ConfigurationInterface;

/**
 * This is the class that validates and merges configuration from your app/config files
 *
 * To learn more see {@link http://symfony.com/doc/current/cookbook/bundles/extension.html#cookbook-bundles-extension-config-class}
 */
class Configuration implements ConfigurationInterface
{
    /**
     * {@inheritdoc}
     */
    public function getConfigTreeBuilder()
    {
        $treeBuilder = new TreeBuilder();
        $root = $treeBuilder->root('bamiz_use_case_executor');

        $root
            ->children()
                ->scalarNode('default_context')->defaultValue('default')->cannotBeEmpty()->end()
                ->arrayNode('contexts')
                    ->prototype('array')
                        ->children()
                            ->variableNode('input')->defaultNull()->end()
                            ->variableNode('response')->defaultNull()->end()
                        ->end()
                    ->end()
                ->end()
            ->end();

        return $treeBuilder;
    }
}
