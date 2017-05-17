<?php

namespace MedlabMG\YoushidoGraphQLExtendedBundle\JMS\Serializer\Naming;

use JMS\Serializer\Metadata\PropertyMetadata;
use JMS\Serializer\Naming\PropertyNamingStrategyInterface;

class CamelCaseNamingStrategy implements PropertyNamingStrategyInterface
{
    public function translateName(PropertyMetadata $property)
    {
        $name = $property->name;

        return $this->translateNameByString($name);
    }

    public function translateNameByString($name)
    {
        $name = preg_replace_callback("/(?:[\_\.])([a-z])/", function($matches) {
            return strtoupper($matches[1]);
        }, $name);

        $name = lcfirst($name);

        return $name;
    }
}
