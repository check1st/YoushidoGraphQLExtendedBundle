<?php

namespace MedlabMG\YoushidoGraphQLExtendedBundle\DependencyInjection;

use JMS\Serializer\Exception\InvalidArgumentException;
use Symfony\Component\Config\Definition\Builder\NodeBuilder;
use Symfony\Component\Config\Definition\Builder\TreeBuilder;
use Symfony\Component\Config\Definition\ConfigurationInterface;
use Symfony\Component\Config\Definition\Exception\InvalidTypeException;

class Configuration implements ConfigurationInterface
{
    public function getConfigTreeBuilder()
    {
        $tb = new TreeBuilder();

        $tb
            ->root('youshido_graphql_extended')
                ->children()
                    ->scalarNode('entity_path_default')->defaultValue("AppBundle\\Entity\\")->end()
                    ->scalarNode('types_path_default')->defaultValue("AppBundle\\GraphQL\\Type")->end()
                ->end()
            ->end()
        ;

        return $tb;
    }

}
