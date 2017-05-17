<?php

namespace MedlabMG\YoushidoGraphQLExtendedBundle;

use MedlabMG\YoushidoGraphQLExtendedBundle\DependencyInjection\Compiler\GraphQLPass;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\HttpKernel\Bundle\Bundle;

class YoushidoGraphQLExtendedBundle extends Bundle
{
    public function build(ContainerBuilder $container)
    {
        $container->addCompilerPass(new GraphQLPass());
    }

    public function getParent()
    {
        return 'GraphQLBundle';
    }
}
