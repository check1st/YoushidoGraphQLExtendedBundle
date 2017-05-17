<?php


namespace MedlabMG\YoushidoGraphQLExtendedBundle\DependencyInjection;


use Symfony\Component\Config\FileLocator;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Extension\PrependExtensionInterface;
use Symfony\Component\DependencyInjection\Loader\YamlFileLoader;
use Symfony\Component\HttpKernel\DependencyInjection\ConfigurableExtension;
use Symfony\Component\HttpKernel\DependencyInjection\Extension;

class YoushidoGraphQLExtendedExtension extends Extension implements PrependExtensionInterface
{
    public function load(array $configs, ContainerBuilder $container)
    {
        $config = $this->processConfiguration(
            $this->getConfiguration($configs, $container),
            $configs
        );

        $container->setParameter('medlab.graphql.entity_path_default', $config['entity_path_default']);

        $loader = new YamlFileLoader($container, new FileLocator(array(__DIR__.'/../Resources/config/')));
        $loader->load('config.yml');
    }

    /**
     * {@inheritdoc}
     */
    public function getConfiguration(array $config, ContainerBuilder $container)
    {
        return new Configuration();
    }


    /**
     * Allow an extension to prepend the extension configurations.
     *
     * @param ContainerBuilder $container
     */
    public function prepend(ContainerBuilder $container)
    {
        // get all bundles
        $bundles = $container->getParameter('kernel.bundles');

        // determine if GraphQLBundle is registered
        if (isset($bundles['GraphQLBundle'])) {

            $update   = ['security' => ['guard' => ['field' => false, 'operation' => true ] ] ];

            $container->prependExtensionConfig('graph_ql', $update);
        }
    }
}