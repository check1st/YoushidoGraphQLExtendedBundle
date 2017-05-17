<?php

namespace MedlabMG\YoushidoGraphQLExtendedBundle\DependencyInjection\Compiler;

use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Reference;

class GraphQLPass implements CompilerPassInterface
{
    public function process(ContainerBuilder $container)
    {
        // always first check if the primary service is defined
        if (!$container->has('medlab.graphql.query_configurator')) {
            return;
        }

        $definitionVoterConfigurator = $container->findDefinition('medlab.graphql.voter.generic_annotation');
        $definitionQueryConfigurator = $container->findDefinition('medlab.graphql.query_configurator');
        $definitionMutationConfigurator = $container->findDefinition('medlab.graphql.mutation_configurator');

        // find all service IDs with the app.mail_transport tag
        $taggedServices = $container->findTaggedServiceIds('graphql.query');

        foreach ($taggedServices as $id => $tags) {
            // add the transport service to the ChainTransport service
            $definitionQueryConfigurator->addMethodCall('addQueryClass', array(new Reference($id)));
            $definitionVoterConfigurator->addMethodCall('addQueryClass', array(new Reference($id)));
        }

        $taggedServices = $container->findTaggedServiceIds('graphql.mutation');

        foreach ($taggedServices as $id => $tags) {
            // add the transport service to the ChainTransport service
            $definitionMutationConfigurator->addMethodCall('addMutationClass', array(new Reference($id)));
            $definitionVoterConfigurator->addMethodCall('addMutationClass', array(new Reference($id)));
        }

        $definitionQueryConfigurator->addMethodCall('postBuild');
        $definitionMutationConfigurator->addMethodCall('postBuild');
    }
}
