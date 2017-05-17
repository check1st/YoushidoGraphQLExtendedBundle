<?php


namespace MedlabMG\YoushidoGraphQLExtendedBundle\Configuration;


use JMS\DiExtraBundle\Annotation as DI;
use Youshido\GraphQL\Type\Object\AbstractObjectType;

class QueryConfiguratorService extends AbstractObjectType
{
    private $queryClasses = [];

    public function build($config)
    {
        if (!$this->queryClasses) {
            return null; // ignore if its empty dependencies
        }

        ksort($this->queryClasses);

        $config->addFields($this->queryClasses);
    }

    public function postBuild()
    {
        $this->build($this->config);
    }

    public function addQueryClass($queryClass)
    {
        $this->queryClasses[$queryClass->getName()]= $queryClass;
    }

}
