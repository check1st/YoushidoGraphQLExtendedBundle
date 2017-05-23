<?php

namespace MedlabMG\YoushidoGraphQLExtendedBundle\Exception;

use MedlabMG\YoushidoGraphQLExtendedBundle\Exception\MedLabValidationEntityException;
use Symfony\Component\Validator\ConstraintViolationListInterface;

class GraphQLValidatorException extends ValidationEntityException
{
    /** @var string  */
    private $resolverName;

    /**
     * GraphQLValidatorException constructor.
     */
    public function __construct($resolverName, ConstraintViolationListInterface $violationList, $code = 0, \Exception $previous = null)
    {
        $this->resolverName = $resolverName;

        parent::__construct("Error in $resolverName, ". (string) $violationList, $violationList, $code = 0, $previous = null);
    }

    /**
     * @return string
     */
    public function getResolverName()
    {
        return $this->resolverName;
    }
}