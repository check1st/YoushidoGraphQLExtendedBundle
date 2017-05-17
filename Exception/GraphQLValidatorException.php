<?php

namespace MedlabMG\YoushidoGraphQLExtendedBundle\Exception;

use MedlabMG\YoushidoGraphQLExtendedBundle\Exception\MedLabValidationEntityException;
use Symfony\Component\Validator\ConstraintViolationListInterface;

class GraphQLValidatorException extends \Exception
{
    /** @var string  */
    private $resolverName;

    /** @var ConstraintViolationListInterface  */
    public $violations;

    /**
     * GraphQLValidatorException constructor.
     */
    public function __construct($resolverName, ConstraintViolationListInterface $violationList, $code = 0, \Exception $previous = null)
    {
        $this->violations = $violationList;
        $this->resolverName = $resolverName;
        parent::__construct((string) $violationList, $code = 0, $previous = null);
    }

    /**
     * @return ConstraintViolationListInterface
     */
    public function getViolationList()
    {
        return $this->violations;
    }

    /**
     * @return string
     */
    public function getResolverName()
    {
        return $this->resolverName;
    }
}