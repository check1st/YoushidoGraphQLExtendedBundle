<?php

/**
 * At this moment we are using manually mapping
 */

namespace MedlabMG\YoushidoGraphQLExtendedBundle\Form;

use MedlabMG\YoushidoGraphQLExtendedBundle\Resolver\ParamBag;
use Symfony\Component\PropertyAccess\PropertyAccess;
use Youshido\GraphQL\Type\InputObject\AbstractInputObjectType;

abstract class AbstractFormGraphQL extends AbstractInputObjectType
{
    /**
     * This function auto set values to a object and if It doesn't found throw an error
     *
     * @param ParamBag $paramBag
     * @param $obj
     * @param array $excludeArrayKeys
     */
    static protected function setParamsValuesAuto(ParamBag $paramBag, $obj, $excludeArrayKeys = [])
    {
        $accessor = PropertyAccess::createPropertyAccessor();

        foreach ($paramBag->all() as $key => $value) {

            if (in_array($key, $excludeArrayKeys)) {
                continue;
            }
            $accessor->setValue($obj, $key, $value);
        }
    }
}