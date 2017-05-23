<?php

namespace MedlabMG\YoushidoGraphQLExtendedBundle\Exception;


use Symfony\Component\Validator\ConstraintViolationListInterface;

class ValidationEntityException extends \Exception
{
    public $violations;

    /**
     * MedLabValidationEntityException constructor.
     * @param $violations
     */
    public function __construct($message = "", ConstraintViolationListInterface $violations, $code = 0, \Exception $previous = null)
    {
        $this->violations = $violations;
        parent::__construct($message, $code, $previous);
    }

    /**
     * @return ConstraintViolationListInterface
     */
    public function getViolationList()
    {
        return $this->violations;
    }
}