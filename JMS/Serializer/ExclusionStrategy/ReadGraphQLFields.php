<?php

namespace MedlabMG\YoushidoGraphQLExtendedBundle\JMS\Serializer\ExclusionStrategy;

use JMS\Serializer\Context;
use JMS\Serializer\Exclusion\ExclusionStrategyInterface;
use JMS\Serializer\Metadata\ClassMetadata;
use JMS\Serializer\Metadata\PropertyMetadata;
use MedlabMG\YoushidoGraphQLExtendedBundle\JMS\Serializer\Naming\CamelCaseNamingStrategy;
use Youshido\GraphQL\Parser\Ast\Field;

class ReadGraphQLFields implements ExclusionStrategyInterface
{
    private $fields;
    private $formatStrategy;

    /**
     * ReadGraphQLFields constructor.
     * @param Field[] $fields
     */
    public function __construct($fields)
    {
        $fieldsToSave = [];

        $this->getArrayFields($fields, $fieldsToSave);

        $this->fields = $fieldsToSave;
        $this->formatStrategy = new CamelCaseNamingStrategy();
    }

    /**
     * @param Field[] $fields
     */
    private function getArrayFields($fields, &$fieldsToSave)
    {
        foreach ($fields as $field) {

            if ($field->getFields()) {
                $fieldsToSave[$field->getName()] = [];
                $this->getArrayFields($field->getFields(), $fieldsToSave[$field->getName()]);
            } else {
                $fieldsToSave[$field->getName()] = true;
            }

        }
    }

    /**
     * Whether the class should be skipped.
     *
     * @param ClassMetadata $metadata
     *
     * @return boolean
     */
    public function shouldSkipClass(ClassMetadata $metadata, Context $context)
    {
        return false;
    }

    /**
     * Whether the property should be skipped.
     *
     * @param PropertyMetadata $property
     *
     * @return boolean
     */
    public function shouldSkipProperty(PropertyMetadata $property, Context $context)
    {

        $arrayDepth = $this->fields;

        if ($context->getCurrentPath()) {

            foreach ($context->getCurrentPath() as $fieldName) {
                $arrayDepth = $arrayDepth[
                $this->formatStrategy->translateNameByString($fieldName)
                ];
            }
        }

        $nameLikeGraphQL = $this->formatStrategy->translateName($property);

        if (!isset($arrayDepth[$nameLikeGraphQL])) {
            return true;
        }

        return false;
    }
}