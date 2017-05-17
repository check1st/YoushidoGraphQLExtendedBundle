<?php


namespace MedlabMG\YoushidoGraphQLExtendedBundle\Configuration;


use JMS\DiExtraBundle\Annotation as DI;
use Youshido\GraphQL\Type\Object\AbstractObjectType;

class MutationConfiguratorService extends AbstractObjectType
{
    private $mutationClasses = [];

    public function build($config)
    {
        if (!$this->mutationClasses) {
            return null; // ignore if its empty dependencies
        }

        ksort($this->mutationClasses);

        $config->addFields($this->mutationClasses);
    }

    public function postBuild()
    {
        $this->build($this->config);
    }

    public function addMutationClass($mutationClass)
    {
        $this->mutationClasses [$mutationClass->getName()]= $mutationClass;
    }
}
